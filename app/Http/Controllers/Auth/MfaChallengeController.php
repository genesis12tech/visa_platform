<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Notifications\MfaChallengeLocked;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * Backend_schema.md §11.4 challenge step: 6-digit TOTP (±1 window drift) or
 * one single-use recovery code; 5 failures locks further attempts and
 * emails the account owner — the strongest available signal that someone
 * other than the owner has the correct password.
 */
class MfaChallengeController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 900;

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('mfa_pending_user_id') === null) {
            return redirect()->route('staff.login');
        }

        return view('auth.mfa-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $userId = $request->session()->get('mfa_pending_user_id');

        if ($userId === null) {
            return redirect()->route('staff.login');
        }

        $user = Staff::query()->findOrFail($userId);
        $key = 'mfa-challenge:'.$userId;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => 'Too many failed attempts. Please try again later.',
            ]);
        }

        $code = $request->string('code')->toString();

        if (! $this->verifyTotp($user, $code) && ! $this->consumeRecoveryCode($user, $code)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            if (RateLimiter::attempts($key) === self::MAX_ATTEMPTS) {
                $user->notify(new MfaChallengeLocked);
            }

            throw ValidationException::withMessages(['code' => 'That code is incorrect.']);
        }

        RateLimiter::clear($key);
        $request->session()->forget('mfa_pending_user_id');

        Auth::guard('staff')->login($user);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->auditLogger->log('auth.login', ['actor' => $user, 'metadata' => ['guard' => 'staff']]);

        return redirect()->intended('/');
    }

    private function verifyTotp(Staff $user, string $code): bool
    {
        $method = $user->mfaMethod;

        if ($method === null || ! $this->google2fa->verifyKey($method->secret_encrypted, $code, 1)) {
            return false;
        }

        $method->forceFill(['last_used_at' => now()])->save();

        return true;
    }

    private function consumeRecoveryCode(Staff $user, string $code): bool
    {
        $match = $user->mfaRecoveryCodes()
            ->whereNull('used_at')
            ->get()
            ->first(fn ($recovery) => Hash::check($code, $recovery->code_hash));

        if ($match === null) {
            return false;
        }

        $match->forceFill(['used_at' => now()])->save();

        return true;
    }
}
