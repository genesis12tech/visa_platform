<?php

use App\Models\Country;
use App\Models\ServiceLocation;
use Illuminate\Database\QueryException;

test('a service location stores operating hours as an array and belongs to a country', function () {
    $country = Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);

    $location = ServiceLocation::query()->create([
        'name' => 'Mumbai Visa Application Centre',
        'country_id' => $country->id,
        'address_line1' => '123 Marine Drive',
        'city' => 'Mumbai',
        'timezone' => 'Asia/Kolkata',
        'latitude' => 18.9220,
        'longitude' => 72.8347,
        'operating_hours' => ['mon' => ['09:00', '17:00'], 'tue' => ['09:00', '17:00']],
    ]);

    $fresh = ServiceLocation::query()->find($location->id);

    expect($fresh->ulid)->toHaveLength(26)
        ->and($fresh->operating_hours)->toBe(['mon' => ['09:00', '17:00'], 'tue' => ['09:00', '17:00']])
        ->and($fresh->country->is($country))->toBeTrue()
        ->and($fresh->is_active)->toBeTrue();
});

test('a service location cannot reference a nonexistent country', function () {
    ServiceLocation::query()->create([
        'name' => 'Nowhere Centre',
        'country_id' => 999999,
        'address_line1' => 'X',
        'city' => 'X',
        'timezone' => 'UTC',
        'operating_hours' => [],
    ]);
})->throws(QueryException::class);
