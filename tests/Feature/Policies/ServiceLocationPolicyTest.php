<?php

use App\Models\Country;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

function makePolicyServiceLocation(): ServiceLocation
{
    $country = Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);

    return ServiceLocation::query()->create([
        'name' => 'Mumbai VAC',
        'country_id' => $country->id,
        'address_line1' => '123 Marine Drive',
        'city' => 'Mumbai',
        'timezone' => 'Asia/Kolkata',
        'operating_hours' => [],
    ]);
}

test('anyone, even a guest, can view a service location', function () {
    $location = makePolicyServiceLocation();

    expect(Gate::forUser(null)->allows('view', $location))->toBeTrue();
});

test('a user without config.manage cannot create, update, or delete a service location', function () {
    $location = makePolicyServiceLocation();
    $user = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);

    expect($user->can('create', ServiceLocation::class))->toBeFalse()
        ->and($user->can('update', $location))->toBeFalse()
        ->and($user->can('delete', $location))->toBeFalse();
});

test('a user with config.manage can create, update, and delete a service location', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    $location = makePolicyServiceLocation();
    $admin = User::query()->create(['name' => 'Marcus', 'email' => 'marcus@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $admin->assignRole('admin');

    expect($admin->can('create', ServiceLocation::class))->toBeTrue()
        ->and($admin->can('update', $location))->toBeTrue()
        ->and($admin->can('delete', $location))->toBeTrue();
});
