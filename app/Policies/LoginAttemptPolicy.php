<?php

namespace App\Policies;

use App\Models\User;

/**
 * login_attempts is a system-written security log — never created, updated,
 * or deleted through the application layer, so only read access is gated
 * here, via the same audit.view permission that governs audit_logs (S2.8).
 */
class LoginAttemptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit.view');
    }

    public function view(User $user): bool
    {
        return $user->can('audit.view');
    }
}
