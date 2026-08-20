<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Backend_schema.md §11.2: verifying activates the account and assigns
     * the applicant role — a user is not a fully-fledged 'applicant' role
     * holder until this point, even though user_type defaults to 'applicant'
     * on the users row itself.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended('/');
        }

        if ($request->user()->markEmailAsVerified()) {
            $request->user()->update(['status' => 'active']);
            $request->user()->assignRole('applicant');

            event(new Verified($request->user()));
        }

        return redirect()->intended('/')->with('verified', true);
    }
}
