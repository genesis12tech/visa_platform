<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * PRD §3.3 role capability matrix, translated into Spatie roles/permissions.
 * Every non-❌ cell in the matrix is a grant of the corresponding permission;
 * fine-grained scope (own/assigned/linked/team) is a Policy concern, not a
 * separate permission string, per Backend_schema.md §12.2/§12.3.
 */
const RPS_NINE_ROLES = [
    'applicant', 'agent', 'case_officer', 'senior_officer', 'document_verifier',
    'finance_officer', 'support_staff', 'admin', 'super_admin',
];

test('seeding creates exactly the nine PRD roles on the web guard', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);

    expect(Role::query()->count())->toBe(9);

    foreach (RPS_NINE_ROLES as $name) {
        expect(Role::query()->where('name', $name)->where('guard_name', 'web')->exists())->toBeTrue();
    }
});

test('seeding is idempotent — running it twice does not create duplicates', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);

    expect(Role::query()->count())->toBe(9)
        ->and(Permission::query()->count())->toBeGreaterThan(0);
});

test('separation of duties — refund.initiate and refund.approve are never held by the same role', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);

    $initiators = Permission::findByName('refund.initiate')->roles->pluck('name');
    $approvers = Permission::findByName('refund.approve')->roles->pluck('name');

    expect($initiators->intersect($approvers)->reject(fn ($name) => $name === 'super_admin'))->toBeEmpty();
    expect($initiators)->toContain('finance_officer')
        ->and($approvers)->toContain('admin')
        ->and($approvers)->not->toContain('finance_officer');
});

test('only case_officer, senior_officer, and super_admin can approve applications — never admin', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);

    $approvers = Permission::findByName('application.approve')->roles->pluck('name')->sort()->values();

    expect($approvers->all())->toBe(['case_officer', 'senior_officer', 'super_admin']);
});

test('support_staff is scoped to metadata-only application visibility and nothing else', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);

    $supportPermissions = Role::query()->where('name', 'support_staff')->first()->permissions->pluck('name');

    expect($supportPermissions->all())->toBe(['application.view']);
});

test('a user can be assigned a role and gains its permissions', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);

    $user = User::query()->create(['name' => 'Marcus Admin', 'email' => 'marcus@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $user->assignRole('admin');

    expect($user->hasRole('admin'))->toBeTrue()
        ->and($user->can('user.manage'))->toBeTrue()
        ->and($user->can('application.approve'))->toBeFalse();
});
