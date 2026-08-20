<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Backend_schema.md §11.2 / PRD PUB-05: the response is identical whether
     * or not the email already exists — an unverified existing account gets
     * the verification email resent instead of a new account being created,
     * and an already-verified one is left untouched. Either way the caller
     * only ever sees "check your email", never an account-existence signal.
     */
    public function store(RegisterUserRequest $request): RedirectResponse
    {
        $existing = User::query()->where('email', $request->string('email'))->first();

        if ($existing) {
            if (! $existing->hasVerifiedEmail()) {
                $existing->sendEmailVerificationNotification();
            }
        } else {
            $user = User::query()->create([
                'name' => $request->string('name'),
                'email' => $request->string('email'),
                'password' => $request->string('password'),
            ]);

            event(new Registered($user));

            Auth::guard('web')->login($user);
        }

        return redirect()->route('verification.notice');
    }
}
