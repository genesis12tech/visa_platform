<?php

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\MfaChallengeLocked;

test('event key is mfa.challenge_locked, carries no interpolated data', function () {
    $notification = new MfaChallengeLocked;

    expect($notification->eventKey())->toBe('mfa.challenge_locked')
        ->and($notification->data(new User))->toBe([]);
});

test('toMail resolves the seeded template', function () {
    NotificationTemplate::query()->create([
        'event_key' => 'mfa.challenge_locked',
        'channel' => 'mail',
        'subject' => 'Multiple failed sign-in verification attempts',
        'body' => 'Further attempts have been temporarily blocked.',
    ]);

    $mail = (new MfaChallengeLocked)->toMail(new User);

    expect($mail->subject)->toBe('Multiple failed sign-in verification attempts');
});
