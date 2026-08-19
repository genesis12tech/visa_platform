<?php

namespace App\Policies;

use App\Models\ServiceLocation;
use App\Models\User;

/**
 * Config policy (Backend_schema.md §12.3): admin write, everyone read.
 */
class ServiceLocationPolicy
{
    public function view(?User $user, ServiceLocation $serviceLocation): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('config.manage');
    }

    public function update(User $user, ServiceLocation $serviceLocation): bool
    {
        return $user->can('config.manage');
    }

    public function delete(User $user, ServiceLocation $serviceLocation): bool
    {
        return $user->can('config.manage');
    }
}
