<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backend_schema.md §11.1: three cookie names, one per guard. Must run
 * before Illuminate\Session\Middleware\StartSession so the overridden name
 * is what actually gets used — see the 'web'/'agent-portal'/'staff-portal'
 * middleware group definitions in bootstrap/app.php for how that ordering
 * is guaranteed.
 */
class SetGuardSessionCookie
{
    public function handle(Request $request, Closure $next, string $cookieName): Response
    {
        config(['session.cookie' => $cookieName]);

        return $next($request);
    }
}
