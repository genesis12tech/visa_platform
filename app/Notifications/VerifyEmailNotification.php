<?php

namespace App\Notifications;

/**
 * Replaces Laravel's default Illuminate\Auth\Notifications\VerifyEmail
 * (wired via User::sendEmailVerificationNotification()). The signed URL is
 * computed by the caller, during the original HTTP request — not lazily in
 * data(), which would run inside a queue worker with no request context to
 * infer the correct host from (this notification is always sent from the
 * applicant/web guard's single domain, so that particular mismatch would be
 * silently masked by APP_URL, but computing it eagerly is the actually
 * correct fix rather than relying on that coincidence).
 */
class VerifyEmailNotification extends TemplatedNotification
{
    public function __construct(private readonly string $verificationUrl)
    {
        parent::__construct();
    }

    public function eventKey(): string
    {
        return 'user.email_verification';
    }

    public function data(object $notifiable): array
    {
        return ['verification_url' => $this->verificationUrl];
    }
}
