<?php

namespace App\Policies;

use App\Models\User;

/**
 * audit_logs is a system-written compliance log — never created, updated,
 * or deleted through the application layer, so only read access is gated
 * here, via the same audit.view permission as login_attempts.
 */
class AuditLogPolicy
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
