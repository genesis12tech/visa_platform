<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

function makePolicyUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Test User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'x',
    ], $overrides));
}

test('a user can view their own record', function () {
    $user = makePolicyUser();

    expect($user->can('view', $user))->toBeTrue();
});

test('a user cannot view another user without user.manage', function () {
    $viewer = makePolicyUser();
    $target = makePolicyUser();

    expect($viewer->can('view', $target))->toBeFalse();
});

test('a user with user.manage can view any user', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    $admin = makePolicyUser();
    $admin->assignRole('admin');
    $target = makePolicyUser();

    expect($admin->can('view', $target))->toBeTrue();
});

test('a user can update their own record but not another user without user.manage', function () {
    $user = makePolicyUser();
    $other = makePolicyUser();

    expect($user->can('update', $user))->toBeTrue()
        ->and($user->can('update', $other))->toBeFalse();
});

test('a user with user.manage can delete another user but never themselves', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    $admin = makePolicyUser();
    $admin->assignRole('admin');
    $target = makePolicyUser();

    expect($admin->can('delete', $target))->toBeTrue()
        ->and($admin->can('delete', $admin))->toBeFalse();
});
