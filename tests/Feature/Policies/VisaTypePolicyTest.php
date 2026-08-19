<?php

use App\Models\Country;
use App\Models\User;
use App\Models\VisaType;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

function makePolicyVisaType(): VisaType
{
    $country = Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);

    return VisaType::query()->create([
        'country_id' => $country->id,
        'code' => 'TOURIST',
        'name' => 'Tourist',
        'processing_days_min' => 3,
        'processing_days_max' => 5,
    ]);
}

test('anyone, even a guest, can view a visa type', function () {
    $visaType = makePolicyVisaType();

    expect(Gate::forUser(null)->allows('view', $visaType))->toBeTrue();
});

test('a user without config.manage cannot create, update, or delete a visa type', function () {
    $visaType = makePolicyVisaType();
    $user = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);

    expect($user->can('create', VisaType::class))->toBeFalse()
        ->and($user->can('update', $visaType))->toBeFalse()
        ->and($user->can('delete', $visaType))->toBeFalse();
});

test('a user with config.manage can create, update, and delete a visa type', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    $visaType = makePolicyVisaType();
    $admin = User::query()->create(['name' => 'Marcus', 'email' => 'marcus@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $admin->assignRole('admin');

    expect($admin->can('create', VisaType::class))->toBeTrue()
        ->and($admin->can('update', $visaType))->toBeTrue()
        ->and($admin->can('delete', $visaType))->toBeTrue();
});
