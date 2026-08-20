<?php

use App\Http\Middleware\SetGuardSessionCookie;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Backend_schema.md §11.1: three guards, three cookie names. The
            // applicant portal ('web' guard) is unconstrained by domain —
            // it's the default guard, serving whatever host the app itself
            // is reached on. Agent/staff only get their routes registered
            // when their domain is actually configured (config/app.php),
            // so they're never accidentally served on the wrong host.
            //
            // Domain-constrained routes MUST be registered before the
            // unconstrained applicant routes: Laravel matches routes in
            // registration order, and an unconstrained route matches any
            // Host header, so registering it first would shadow the
            // domain-specific ones entirely — they'd never be reached.
            //
            // All three go through the `then` callback rather than the
            // `web:` shorthand deliberately: that shorthand always wraps
            // its file in the literal built-in 'web' group with no way to
            // substitute a custom one, which would make the applicant
            // guard's cookie name unfixable without mutating 'web' itself
            // (and mutating 'web' breaks agent/staff — see the middleware
            // group comment below).
            if (config('app.agent_domain')) {
                Route::domain(config('app.agent_domain'))
                    ->middleware('agent-portal')
                    ->group(__DIR__.'/../routes/agent.php');
            }

            if (config('app.staff_domain')) {
                Route::domain(config('app.staff_domain'))
                    ->middleware('staff-portal')
                    ->group(__DIR__.'/../routes/staff.php');
            }

            Route::middleware('applicant-portal')
                ->group(__DIR__.'/../routes/web.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Backend_schema.md §11.1: three cookie names, one per guard. Each
        // group lists its cookie-setter before the nested 'web' reference
        // so it runs before StartSession (Laravel expands nested group
        // references in place, preserving array order) — deliberately NOT
        // done via prependToGroup('web', ...): 'web' gets expanded fresh
        // every time it's nested inside another group, so a prepend on
        // 'web' itself would re-fire in every group that references it,
        // always last, clobbering whichever guard-specific name was set
        // first.
        $middleware->group('applicant-portal', [
            SetGuardSessionCookie::class.':visa_applicant_session',
            'web',
        ]);

        $middleware->group('agent-portal', [
            SetGuardSessionCookie::class.':visa_agent_session',
            'web',
        ]);

        $middleware->group('staff-portal', [
            SetGuardSessionCookie::class.':visa_staff_session',
            'web',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
