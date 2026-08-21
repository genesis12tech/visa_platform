<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/**
 * English mail-channel templates for the notifications this project actually
 * sends so far (Implementation_plan.md S2.11). Database-channel templates
 * (for a future notification-centre display) and Hindi/French translations
 * are deferred until something actually reads them.
 */
class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'event_key' => 'user.email_verification',
                'subject' => 'Verify your email address',
                'body' => "Welcome. Please verify your email address by visiting the link below.\n:verification_url\nThis verification link will expire in 60 minutes.\nIf you did not create an account, no further action is required.",
            ],
            [
                'event_key' => 'user.password_reset_link',
                'subject' => 'Reset your password',
                'body' => "We received a request to reset your password.\n:reset_url\nThis password reset link will expire in 60 minutes.\nIf you did not request a password reset, no further action is required.",
            ],
            [
                'event_key' => 'user.password_was_reset',
                'subject' => 'Your password has been changed',
                'body' => "This is a confirmation that your password was just changed.\nIf you did not make this change, contact support immediately.",
            ],
            [
                'event_key' => 'mfa.challenge_locked',
                'subject' => 'Multiple failed sign-in verification attempts',
                'body' => "There were 5 failed two-factor verification attempts on your account after a correct password was entered.\nFurther attempts have been temporarily blocked.\nIf this was not you, your password may be compromised — contact support immediately.",
            ],
        ];

        foreach ($rows as $row) {
            NotificationTemplate::query()->updateOrCreate(
                ['event_key' => $row['event_key'], 'channel' => 'mail', 'locale' => 'en'],
                ['subject' => $row['subject'], 'body' => $row['body']]
            );
        }
    }
}
