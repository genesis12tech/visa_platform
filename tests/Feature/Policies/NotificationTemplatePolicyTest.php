<?php

use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

function makeTemplate(): NotificationTemplate
{
    return NotificationTemplate::query()->create([
        'event_key' => 'user.email_verification',
        'channel' => 'mail',
        'body' => 'Visit :verification_url to verify.',
    ]);
}

test('anyone, even a guest, can view notification templates', function () {
    $template = makeTemplate();

    expect(Gate::forUser(null)->allows('view', $template))->toBeTrue();
});

test('a user without config.manage cannot create, update, or delete a template', function () {
    $user = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);
    $template = makeTemplate();

    expect($user->can('create', NotificationTemplate::class))->toBeFalse()
        ->and($user->can('update', $template))->toBeFalse()
        ->and($user->can('delete', $template))->toBeFalse();
});

test('a user with config.manage can create, update, and delete a template', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $admin->assignRole('admin');
    $template = makeTemplate();

    expect($admin->can('create', NotificationTemplate::class))->toBeTrue()
        ->and($admin->can('update', $template))->toBeTrue()
        ->and($admin->can('delete', $template))->toBeTrue();
});
