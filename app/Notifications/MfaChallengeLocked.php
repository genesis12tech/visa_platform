<?php

namespace App\Notifications;

/**
 * Backend_schema.md §11.4: 5 failed MFA challenge attempts locks further
 * attempts and emails the account owner — a repeated wrong-code streak
 * after a correct password is the strongest signal available that someone
 * other than the account owner has the password.
 */
class MfaChallengeLocked extends TemplatedNotification
{
    public function eventKey(): string
    {
        return 'mfa.challenge_locked';
    }

    public function data(object $notifiable): array
    {
        return [];
    }
}
