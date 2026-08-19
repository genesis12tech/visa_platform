<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisaType;

/**
 * Config policy (Backend_schema.md §12.3): admin write, everyone read — visa
 * type data is public-facing catalogue information.
 */
class VisaTypePolicy
{
    public function view(?User $user, VisaType $visaType): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('config.manage');
    }

    public function update(User $user, VisaType $visaType): bool
    {
        return $user->can('config.manage');
    }

    public function delete(User $user, VisaType $visaType): bool
    {
        return $user->can('config.manage');
    }
}
