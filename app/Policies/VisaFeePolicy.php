<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisaFee;

/**
 * Config policy (Backend_schema.md §12.3): admin write, everyone read.
 */
class VisaFeePolicy
{
    public function view(?User $user, VisaFee $visaFee): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('config.manage');
    }

    public function update(User $user, VisaFee $visaFee): bool
    {
        return $user->can('config.manage');
    }

    public function delete(User $user, VisaFee $visaFee): bool
    {
        return $user->can('config.manage');
    }
}
