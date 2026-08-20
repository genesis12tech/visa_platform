<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Non-enumeration, consistent with registration (PRD PUB-05): the response
 * is identical whether or not the email exists, so the return value of
 * Password::sendResetLink() is deliberately never surfaced to the caller.
 */
class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, string $broker = 'applicants'): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        Password::broker($broker)->sendResetLink($request->only('email'));

        return back()->with('status', 'reset-link-sent');
    }
}
