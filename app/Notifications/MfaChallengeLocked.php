<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Backend_schema.md §11.4: 5 failed MFA challenge attempts locks further
 * attempts and emails the account owner — a repeated wrong-code streak
 * after a correct password is the strongest signal available that someone
 * other than the account owner has the password.
 */
class MfaChallengeLocked extends Notification implements ShouldQueue
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
            ->subject('Multiple failed sign-in verification attempts')
            ->line('There were 5 failed two-factor verification attempts on your account after a correct password was entered.')
            ->line('Further attempts have been temporarily blocked.')
            ->line('If this was not you, your password may be compromised — contact support immediately.');
    }
}
