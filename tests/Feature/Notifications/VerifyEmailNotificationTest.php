<?php

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;

test('carries the given verification url as its data, event key user.email_verification', function () {
    $notification = new VerifyEmailNotification('https://visa.geninnovations.net/email/verify/1/abc?signature=xyz');

    expect($notification->eventKey())->toBe('user.email_verification')
        ->and($notification->data(new User))->toBe([
            'verification_url' => 'https://visa.geninnovations.net/email/verify/1/abc?signature=xyz',
        ]);
});

test('toMail interpolates the verification url into the seeded template', function () {
    NotificationTemplate::query()->create([
        'event_key' => 'user.email_verification',
        'channel' => 'mail',
        'subject' => 'Verify your email address',
        'body' => 'Visit :verification_url to verify.',
    ]);

    $mail = (new VerifyEmailNotification('https://example.com/verify/xyz'))->toMail(new User);

    expect($mail->subject)->toBe('Verify your email address')
        ->and($mail->introLines)->toBe(['Visit https://example.com/verify/xyz to verify.']);
});
