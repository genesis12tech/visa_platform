<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backend_schema.md §11.4: any staff user without mfa_enabled_at can reach
 * ONLY the enrolment routes, in every non-local environment. Applied to the
 * whole 'staff-portal' middleware group (bootstrap/app.php) rather than
 * per-route, so a route added later without remembering to gate it can't
 * accidentally bypass MFA — it no-ops for guests and for the enrolment
 * routes themselves.
 */
class EnsureMfaEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        $user = $request->user('staff');

        if ($user === null || $user->mfa_enabled_at !== null) {
            return $next($request);
        }

        if ($request->routeIs('staff.mfa.*')) {
            return $next($request);
        }

        return redirect()->route('staff.mfa.enroll');
    }
}
