<?php

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\Exceptions\NotificationTemplateNotFoundException;
use App\Notifications\TemplatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class TestTemplatedNotification extends TemplatedNotification
{
    public function eventKey(): string
    {
        return 'test.event';
    }

    public function data(object $notifiable): array
    {
        return ['name' => $notifiable->name];
    }
}

function makeMailTemplate(string $eventKey = 'test.event'): NotificationTemplate
{
    return NotificationTemplate::query()->create([
        'event_key' => $eventKey,
        'channel' => 'mail',
        'subject' => 'Hello :name',
        'body' => "Line one for :name.\nLine two.",
    ]);
}

test('is queued, routed to the emails queue', function () {
    $notification = new TestTemplatedNotification;

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->queue)->toBe('emails');
});

test('via() sends through mail and database by default', function () {
    expect((new TestTemplatedNotification)->via(new User))->toBe(['mail', 'database']);
});

test('toMail resolves the template and interpolates the notifiable\'s data', function () {
    makeMailTemplate();
    $user = User::query()->create(['name' => 'Priya', 'email' => 'priya@example.com', 'password' => 'x']);

    $mail = (new TestTemplatedNotification)->toMail($user);

    expect($mail->subject)->toBe('Hello Priya')
        ->and($mail->introLines)->toBe(['Line one for Priya.', 'Line two.']);
});

test('toMail throws when no active template exists for the event, rather than sending blank content', function () {
    $user = User::query()->create(['name' => 'Priya', 'email' => 'priya@example.com', 'password' => 'x']);

    (new TestTemplatedNotification)->toMail($user);
})->throws(NotificationTemplateNotFoundException::class);

test('toDatabase stores the event key and the same data used for the mail template', function () {
    $user = User::query()->create(['name' => 'Priya', 'email' => 'priya@example.com', 'password' => 'x']);

    $payload = (new TestTemplatedNotification)->toDatabase($user);

    expect($payload)->toBe(['event_key' => 'test.event', 'data' => ['name' => 'Priya']]);
});
