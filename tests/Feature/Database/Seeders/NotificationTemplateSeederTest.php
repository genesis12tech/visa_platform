<?php

use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Artisan;

test('seeding produces one active mail-channel English template per known event', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\NotificationTemplateSeeder']);

    $eventKeys = [
        'user.email_verification',
        'user.password_reset_link',
        'user.password_was_reset',
        'mfa.challenge_locked',
    ];

    foreach ($eventKeys as $eventKey) {
        expect(NotificationTemplate::resolve($eventKey, 'mail', 'en'))->not->toBeNull();
    }

    expect(NotificationTemplate::query()->count())->toBe(count($eventKeys));
});

test('seeding is idempotent — running it twice does not create duplicates', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\NotificationTemplateSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\NotificationTemplateSeeder']);

    expect(NotificationTemplate::query()->count())->toBe(4);
});
