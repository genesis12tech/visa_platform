<?php

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\PasswordWasReset;

test('event key is user.password_was_reset, carries no interpolated data', function () {
    $notification = new PasswordWasReset;

    expect($notification->eventKey())->toBe('user.password_was_reset')
        ->and($notification->data(new User))->toBe([]);
});

test('toMail resolves the seeded template', function () {
    NotificationTemplate::query()->create([
        'event_key' => 'user.password_was_reset',
        'channel' => 'mail',
        'subject' => 'Your password has been changed',
        'body' => 'This is a confirmation that your password was just changed.',
    ]);

    $mail = (new PasswordWasReset)->toMail(new User);

    expect($mail->subject)->toBe('Your password has been changed');
});
