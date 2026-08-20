<?php

namespace App\Policies;

use App\Models\MfaRecoveryCode;
use App\Models\User;

class MfaRecoveryCodePolicy
{
    public function view(User $user, MfaRecoveryCode $code): bool
    {
        return $user->id === $code->user_id;
    }

    public function delete(User $user, MfaRecoveryCode $code): bool
    {
        return $user->id === $code->user_id || $user->can('user.manage');
    }
}
