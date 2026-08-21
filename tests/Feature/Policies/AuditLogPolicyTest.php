<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

test('a user without audit.view cannot view audit logs', function () {
    $user = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);
    $log = AuditLog::query()->create(['action' => 'user.registered']);

    expect($user->can('viewAny', AuditLog::class))->toBeFalse()
        ->and($user->can('view', $log))->toBeFalse();
});

test('a user with audit.view can view audit logs', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    $officer = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $officer->assignRole('case_officer');
    $log = AuditLog::query()->create(['action' => 'user.registered']);

    expect($officer->can('viewAny', AuditLog::class))->toBeTrue()
        ->and($officer->can('view', $log))->toBeTrue();
});
