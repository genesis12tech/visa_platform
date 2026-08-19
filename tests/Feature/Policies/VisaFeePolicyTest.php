<?php

use App\Models\Country;
use App\Models\User;
use App\Models\VisaFee;
use App\Models\VisaType;
use App\Support\Money\Money;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

function makePolicyVisaFee(): VisaFee
{
    $country = Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);
    $visaType = VisaType::query()->create([
        'country_id' => $country->id,
        'code' => 'TOURIST',
        'name' => 'Tourist',
        'processing_days_min' => 3,
        'processing_days_max' => 5,
    ]);

    return VisaFee::query()->create([
        'visa_type_id' => $visaType->id,
        'currency' => 'USD',
        'base_fee_minor' => Money::of(14000, 'USD'),
        'valid_from' => '2026-01-01',
    ]);
}

test('anyone, even a guest, can view a fee rule', function () {
    $fee = makePolicyVisaFee();

    expect(Gate::forUser(null)->allows('view', $fee))->toBeTrue();
});

test('a user without config.manage cannot create, update, or delete a fee rule', function () {
    $fee = makePolicyVisaFee();
    $user = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);

    expect($user->can('create', VisaFee::class))->toBeFalse()
        ->and($user->can('update', $fee))->toBeFalse()
        ->and($user->can('delete', $fee))->toBeFalse();
});

test('a user with config.manage can create, update, and delete a fee rule', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    $fee = makePolicyVisaFee();
    $admin = User::query()->create(['name' => 'Marcus', 'email' => 'marcus@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $admin->assignRole('admin');

    expect($admin->can('create', VisaFee::class))->toBeTrue()
        ->and($admin->can('update', $fee))->toBeTrue()
        ->and($admin->can('delete', $fee))->toBeTrue();
});
