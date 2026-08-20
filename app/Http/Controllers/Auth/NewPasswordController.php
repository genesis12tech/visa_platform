<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Notifications\PasswordWasReset;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Each guard's login route is named differently (routes/web.php,
     * routes/agent.php, routes/staff.php) — the broker name (matching
     * config/auth.php's password broker keys) is the only thing this
     * controller already knows, so it maps from that.
     */
    private const BROKER_LOGIN_ROUTES = [
        'applicants' => 'login',
        'agents' => 'agent.login',
        'staff' => 'staff.login',
    ];

    public function create(Request $request): View
    {
        return view('auth.reset-password', ['token' => $request->route('token')]);
    }

    /**
     * FR-ID-11: expiring single-use token (enforced by the broker itself —
     * a used or expired token fails the same generic way as an invalid
     * one), and the account owner is notified after the change completes.
     */
    public function store(ResetPasswordRequest $request, string $broker = 'applicants'): RedirectResponse
    {
        $status = Password::broker($broker)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();

                event(new PasswordReset($user));

                $user->notify(new PasswordWasReset);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return redirect()->route(self::BROKER_LOGIN_ROUTES[$broker])->with('status', 'password-reset');
    }
}
