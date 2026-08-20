<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Shared across all three guards (Backend_schema.md §11.1) — routes/web.php,
 * routes/agent.php, routes/staff.php each bind their own `guard` route
 * default (Route::defaults('guard', ...)) to the same controller, so the
 * credential-check/rate-limit/logging logic isn't tripled.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Maps each session guard to the user_type it must match. A credential
     * match on the wrong guard is still 'bad_credentials' — never a
     * distinct message — otherwise the login form leaks which portal an
     * email belongs to.
     */
    private const GUARD_USER_TYPES = [
        'web' => 'applicant',
        'agent' => 'agent',
        'staff' => 'staff',
    ];

    public function create(string $guard = 'web'): View
    {
        return view('auth.login');
    }

    /**
     * Backend_schema.md §11.3: rate limit before evaluating; one login_attempts
     * row per attempt (recorded even for unknown emails, with a null user_id);
     * one generic failure message for every rejection reason, with a
     * dummy-hash comparison on unknown emails so response timing doesn't
     * distinguish "no such user" from "wrong password".
     */
    public function store(LoginRequest $request, string $guard = 'web'): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $email = $request->string('email')->toString();
        $user = User::query()->where('email', $email)->first();
        $failureReason = $this->determineFailureReason($user, $guard, $request->string('password')->toString());

        LoginAttempt::query()->create([
            'email' => $email,
            'user_id' => $user?->id,
            'successful' => $failureReason === null,
            'failure_reason' => $failureReason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'guard' => $guard,
        ]);

        if ($failureReason !== null) {
            $request->hitRateLimiters();

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->clearRateLimiters();

        Auth::guard($guard)->login($user);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return redirect()->intended('/');
    }

    public function destroy(Request $request, string $guard = 'web'): RedirectResponse
    {
        Auth::guard($guard)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function determineFailureReason(?User $user, string $guard, string $password): ?string
    {
        $hashToCheck = $user !== null ? $user->password : '$2y$12$UnknownUserDummyHashUnknownUserDummyHashUnknownUserDu';
        $passwordMatches = Hash::check($password, $hashToCheck);

        if (! $user || ! $passwordMatches || $user->user_type !== self::GUARD_USER_TYPES[$guard]) {
            return 'bad_credentials';
        }

        if ($user->status === 'suspended') {
            return 'suspended';
        }

        if ($user->email_verified_at === null) {
            return 'unverified';
        }

        return null;
    }
}
