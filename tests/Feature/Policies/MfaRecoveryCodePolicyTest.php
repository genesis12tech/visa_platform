<?php

use App\Models\MfaRecoveryCode;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

test('the owner can view and delete their own recovery codes', function () {
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
    [, $code] = MfaRecoveryCode::generateFor($user);

    expect($user->can('view', $code))->toBeTrue()
        ->and($user->can('delete', $code))->toBeTrue();
});

test('another staff member cannot view or delete someone else\'s recovery codes', function () {
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $other = User::query()->create(['name' => 'Fatima', 'email' => 'fatima@example.com', 'password' => 'x', 'user_type' => 'staff']);
    [, $code] = MfaRecoveryCode::generateFor($user);

    expect($other->can('view', $code))->toBeFalse()
        ->and($other->can('delete', $code))->toBeFalse();
});

test('an admin can delete another user\'s recovery codes for account recovery', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $admin = User::query()->create(['name' => 'Marcus', 'email' => 'marcus@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $admin->assignRole('admin');
    [, $code] = MfaRecoveryCode::generateFor($user);

    expect($admin->can('delete', $code))->toBeTrue();
});
