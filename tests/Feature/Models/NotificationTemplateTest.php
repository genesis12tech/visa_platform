<?php

use App\Models\NotificationTemplate;
use App\Notifications\Exceptions\NotificationTemplateNotFoundException;
use Illuminate\Database\QueryException;

test('resolve finds the active template for an event, channel, and locale', function () {
    NotificationTemplate::query()->create([
        'event_key' => 'user.email_verification',
        'channel' => 'mail',
        'locale' => 'en',
        'subject' => 'Verify your email address',
        'body' => 'Visit :verification_url to verify.',
    ]);

    $template = NotificationTemplate::resolve('user.email_verification', 'mail', 'en');

    expect($template->subject)->toBe('Verify your email address');
});

test('locale defaults to en when not given', function () {
    NotificationTemplate::query()->create([
        'event_key' => 'user.email_verification',
        'channel' => 'mail',
        'body' => 'Visit :verification_url to verify.',
    ]);

    $template = NotificationTemplate::resolve('user.email_verification', 'mail');

    expect($template->locale)->toBe('en');
});

test('an inactive template is never resolved, even if it is the only match', function () {
    NotificationTemplate::query()->create([
        'event_key' => 'user.email_verification',
        'channel' => 'mail',
        'body' => 'Visit :verification_url to verify.',
        'is_active' => false,
    ]);

    NotificationTemplate::resolve('user.email_verification', 'mail', 'en');
})->throws(NotificationTemplateNotFoundException::class);

test('a missing template throws rather than silently falling back to a default', function () {
    NotificationTemplate::resolve('not.a.real.event', 'mail', 'en');
})->throws(NotificationTemplateNotFoundException::class);

test('render() substitutes :placeholder tokens in both subject and body', function () {
    $template = NotificationTemplate::query()->create([
        'event_key' => 'user.email_verification',
        'channel' => 'mail',
        'subject' => 'Hello :name',
        'body' => 'Visit :verification_url to verify, :name.',
    ]);

    $rendered = $template->render(['name' => 'Priya', 'verification_url' => 'https://example.com/verify']);

    expect($rendered['subject'])->toBe('Hello Priya')
        ->and($rendered['body'])->toBe('Visit https://example.com/verify to verify, Priya.');
});

test('a null subject renders as null, not the literal string', function () {
    $template = NotificationTemplate::query()->create([
        'event_key' => 'x',
        'channel' => 'database',
        'body' => 'Body only.',
    ]);

    $rendered = $template->render([]);

    expect($rendered['subject'])->toBeNull();
});

test('event_key, locale, and channel together must be unique', function () {
    NotificationTemplate::query()->create(['event_key' => 'user.email_verification', 'channel' => 'mail', 'locale' => 'en', 'body' => 'A']);
    NotificationTemplate::query()->create(['event_key' => 'user.email_verification', 'channel' => 'mail', 'locale' => 'en', 'body' => 'B']);
})->throws(QueryException::class);

test('channel must be mail or database', function () {
    NotificationTemplate::query()->create(['event_key' => 'x', 'channel' => 'sms', 'body' => 'A']);
})->throws(QueryException::class)->skip(fn () => ! databaseEnforcesCheckConstraints(), 'CHECK constraints are not enforced on this engine');
