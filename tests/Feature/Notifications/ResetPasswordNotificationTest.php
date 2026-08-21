<?php

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;

test('carries the given reset url as its data, event key user.password_reset_link', function () {
    $notification = new ResetPasswordNotification('https://visa.geninnovations.net/reset-password/abc?email=priya%40example.com');

    expect($notification->eventKey())->toBe('user.password_reset_link')
        ->and($notification->data(new User))->toBe([
            'reset_url' => 'https://visa.geninnovations.net/reset-password/abc?email=priya%40example.com',
        ]);
});

test('toMail interpolates the reset url into the seeded template', function () {
    NotificationTemplate::query()->create([
        'event_key' => 'user.password_reset_link',
        'channel' => 'mail',
        'subject' => 'Reset your password',
        'body' => 'Visit :reset_url to reset your password.',
    ]);

    $mail = (new ResetPasswordNotification('https://example.com/reset/xyz'))->toMail(new User);

    expect($mail->subject)->toBe('Reset your password')
        ->and($mail->introLines)->toBe(['Visit https://example.com/reset/xyz to reset your password.']);
});
