<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * FR-ID-11: the account owner is notified when their password is reset —
 * distinct from the reset-link email itself (Illuminate\Auth\Notifications\ResetPassword),
 * which goes out before the change; this confirms after it happened, so a
 * password reset the owner didn't request is visible to them either way.
 */
class PasswordWasReset extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your password has been changed')
            ->line('This is a confirmation that your password was just changed.')
            ->line('If you did not make this change, contact support immediately.');
    }
}
