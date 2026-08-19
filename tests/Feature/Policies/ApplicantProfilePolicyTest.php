<?php

use App\Models\ApplicantProfile;
use App\Models\Country;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

function makeProfileForPolicyTest(User $owner): ApplicantProfile
{
    $india = Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);

    return ApplicantProfile::query()->create([
        'user_id' => $owner->id,
        'first_name' => 'Priya',
        'last_name' => 'Sharma',
        'date_of_birth' => '1991-03-14',
        'nationality_country_id' => $india->id,
    ]);
}

test('the owner can view their own profile', function () {
    $owner = User::query()->create(['name' => 'Priya', 'email' => 'priya@example.com', 'password' => 'x']);
    $profile = makeProfileForPolicyTest($owner);

    expect($owner->can('view', $profile))->toBeTrue();
});

test('another user without application.view cannot view someone else\'s profile', function () {
    $owner = User::query()->create(['name' => 'Priya', 'email' => 'priya@example.com', 'password' => 'x']);
    $stranger = User::query()->create(['name' => 'Stranger', 'email' => 'stranger@example.com', 'password' => 'x']);
    $profile = makeProfileForPolicyTest($owner);

    expect($stranger->can('view', $profile))->toBeFalse();
});

test('a staff member with application.view can view any profile', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    $owner = User::query()->create(['name' => 'Priya', 'email' => 'priya@example.com', 'password' => 'x']);
    $officer = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $officer->assignRole('case_officer');
    $profile = makeProfileForPolicyTest($owner);

    expect($officer->can('view', $profile))->toBeTrue();
});

test('the owner can update their own profile but nobody else can, even staff', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    $owner = User::query()->create(['name' => 'Priya', 'email' => 'priya@example.com', 'password' => 'x']);
    $officer = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $officer->assignRole('case_officer');
    $profile = makeProfileForPolicyTest($owner);

    expect($owner->can('update', $profile))->toBeTrue()
        ->and($officer->can('update', $profile))->toBeFalse();
});

test('only a user with user.manage can delete a profile', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    $owner = User::query()->create(['name' => 'Priya', 'email' => 'priya@example.com', 'password' => 'x']);
    $admin = User::query()->create(['name' => 'Marcus', 'email' => 'marcus@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $admin->assignRole('admin');
    $profile = makeProfileForPolicyTest($owner);

    expect($owner->can('delete', $profile))->toBeFalse()
        ->and($admin->can('delete', $profile))->toBeTrue();
});
