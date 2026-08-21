<?php

namespace App\Notifications;

/**
 * Replaces Laravel's default Illuminate\Auth\Notifications\ResetPassword
 * (wired via User::sendPasswordResetNotification()). Unlike email
 * verification, this genuinely needs the caller's guard-correct host —
 * applicant/agent/staff each reset on their own domain — so the URL is
 * always computed eagerly by the caller, during the original HTTP request,
 * never lazily here where a queue worker has no request to infer it from.
 */
class ResetPasswordNotification extends TemplatedNotification
{
    public function __construct(private readonly string $resetUrl)
    {
        parent::__construct();
    }

    public function eventKey(): string
    {
        return 'user.password_reset_link';
    }

    public function data(object $notifiable): array
    {
        return ['reset_url' => $this->resetUrl];
    }
}
