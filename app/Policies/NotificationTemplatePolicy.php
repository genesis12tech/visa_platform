<?php

namespace App\Policies;

use App\Models\NotificationTemplate;
use App\Models\User;

/**
 * Config policy (Backend_schema.md §12.3): admin write, everyone read.
 */
class NotificationTemplatePolicy
{
    public function view(?User $user, NotificationTemplate $notificationTemplate): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('config.manage');
    }

    public function update(User $user, NotificationTemplate $notificationTemplate): bool
    {
        return $user->can('config.manage');
    }

    public function delete(User $user, NotificationTemplate $notificationTemplate): bool
    {
        return $user->can('config.manage');
    }
}
