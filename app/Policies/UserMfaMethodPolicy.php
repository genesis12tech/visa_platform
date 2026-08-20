<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserMfaMethod;

/**
 * The owner is the only one who ever needs to view/manage their own MFA
 * method under normal use. Admin gets delete-only (never view) — enough to
 * reset a locked-out staff member's MFA for re-enrolment, without ever
 * needing to see the secret itself.
 */
class UserMfaMethodPolicy
{
    public function view(User $user, UserMfaMethod $method): bool
    {
        return $user->id === $method->user_id;
    }

    public function update(User $user, UserMfaMethod $method): bool
    {
        return $user->id === $method->user_id;
    }

    public function delete(User $user, UserMfaMethod $method): bool
    {
        return $user->id === $method->user_id || $user->can('user.manage');
    }
}
