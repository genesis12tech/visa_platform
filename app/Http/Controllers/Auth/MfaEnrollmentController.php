<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MfaRecoveryCode;
use App\Models\UserMfaMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * Backend_schema.md §11.4. The pending secret lives only in the session
 * until confirmed — nothing is written to user_mfa_methods until the user
 * proves possession of it by entering a real code, so an abandoned
 * enrolment attempt never leaves a half-configured MFA method behind.
 */
class MfaEnrollmentController extends Controller
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user('staff');

        if ($user->mfa_enabled_at !== null) {
            return redirect('/');
        }

        $secret = $request->session()->get('mfa_pending_secret');

        if ($secret === null) {
            $secret = $this->google2fa->generateSecretKey();
            $request->session()->put('mfa_pending_secret', $secret);
        }

        $qrCodeInline = $this->google2fa->getQRCodeInline(config('app.name'), $user->email, $secret);

        return view('auth.mfa-enroll', ['secret' => $secret, 'qrCodeInline' => $qrCodeInline]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user('staff');
        $secret = $request->session()->get('mfa_pending_secret');

        abort_if($secret === null, 419);

        if (! $this->google2fa->verifyKey($secret, $request->string('code'), 1)) {
            throw ValidationException::withMessages(['code' => 'That code is incorrect. Please try again.']);
        }

        UserMfaMethod::query()->create([
            'user_id' => $user->id,
            'type' => 'totp',
            'secret_encrypted' => $secret,
            'confirmed_at' => now(),
        ]);

        $recoveryCodes = MfaRecoveryCode::generateBatchFor($user, 10);

        $user->forceFill(['mfa_enabled_at' => now()])->save();

        $request->session()->forget('mfa_pending_secret');
        $request->session()->flash('recovery_codes', array_column($recoveryCodes, 0));

        return redirect()->route('staff.mfa.recovery-codes');
    }

    public function showRecoveryCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('recovery_codes');

        if ($codes === null) {
            return redirect('/');
        }

        return view('auth.mfa-recovery-codes', ['codes' => $codes]);
    }
}
