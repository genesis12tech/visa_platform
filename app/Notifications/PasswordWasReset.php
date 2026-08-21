<?php

namespace App\Notifications;

/**
 * FR-ID-11: the account owner is notified when their password is reset —
 * distinct from the reset-link email itself (ResetPasswordNotification),
 * which goes out before the change; this confirms after it happened, so a
 * password reset the owner didn't request is visible to them either way.
 */
class PasswordWasReset extends TemplatedNotification
{
    public function eventKey(): string
    {
        return 'user.password_was_reset';
    }

    public function data(object $notifiable): array
    {
        return [];
    }
}
