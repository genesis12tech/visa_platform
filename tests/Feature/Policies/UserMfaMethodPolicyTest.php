<?php

use App\Models\User;
use App\Models\UserMfaMethod;
use Illuminate\Support\Facades\Artisan;

test('the owner can view and update their own mfa method', function () {
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $method = UserMfaMethod::query()->create(['user_id' => $user->id, 'secret_encrypted' => 'AAA']);

    expect($user->can('view', $method))->toBeTrue()
        ->and($user->can('update', $method))->toBeTrue()
        ->and($user->can('delete', $method))->toBeTrue();
});

test('another staff member cannot view or manage someone else\'s mfa method', function () {
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $other = User::query()->create(['name' => 'Fatima', 'email' => 'fatima@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $method = UserMfaMethod::query()->create(['user_id' => $user->id, 'secret_encrypted' => 'AAA']);

    expect($other->can('view', $method))->toBeFalse()
        ->and($other->can('delete', $method))->toBeFalse();
});

test('an admin can delete (reset) another user\'s mfa method for account recovery', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $admin = User::query()->create(['name' => 'Marcus', 'email' => 'marcus@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $admin->assignRole('admin');
    $method = UserMfaMethod::query()->create(['user_id' => $user->id, 'secret_encrypted' => 'AAA']);

    expect($admin->can('delete', $method))->toBeTrue()
        ->and($admin->can('view', $method))->toBeFalse();
});
