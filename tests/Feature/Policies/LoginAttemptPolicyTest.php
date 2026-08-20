<?php

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

test('a user without audit.view cannot view login attempts', function () {
    $user = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);
    $attempt = LoginAttempt::query()->create(['email' => 'a@example.com', 'successful' => true]);

    expect($user->can('viewAny', LoginAttempt::class))->toBeFalse()
        ->and($user->can('view', $attempt))->toBeFalse();
});

test('a user with audit.view can view login attempts', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    $officer = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $officer->assignRole('case_officer');
    $attempt = LoginAttempt::query()->create(['email' => 'chen@example.com', 'successful' => true]);

    expect($officer->can('viewAny', LoginAttempt::class))->toBeTrue()
        ->and($officer->can('view', $attempt))->toBeTrue();
});
