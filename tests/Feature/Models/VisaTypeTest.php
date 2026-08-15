<?php

use App\Models\Country;
use App\Models\VisaType;
use Illuminate\Database\QueryException;

function makeCountry(string $iso2 = 'IN', string $iso3 = 'IND', string $name = 'India'): Country
{
    return Country::query()->create(['iso2' => $iso2, 'iso3' => $iso3, 'name' => $name]);
}

test('a visa type is created with an auto-generated ulid and defaults to inactive', function () {
    $country = makeCountry();

    $visaType = VisaType::query()->create([
        'country_id' => $country->id,
        'code' => 'TOURIST',
        'name' => 'Tourist Visa',
        'processing_days_min' => 3,
        'processing_days_max' => 5,
    ]);

    expect($visaType->ulid)->toBeString()
        ->and(strlen($visaType->ulid))->toBe(26)
        ->and($visaType->is_active)->toBeFalse()
        ->and($visaType->sla_business_hours)->toBe(240)
        ->and($visaType->getRouteKeyName())->toBe('ulid');
});

test('code must be unique per country but may repeat across countries', function () {
    $india = makeCountry('IN', 'IND', 'India');
    $france = makeCountry('FR', 'FRA', 'France');

    VisaType::query()->create(['country_id' => $india->id, 'code' => 'TOURIST', 'name' => 'Tourist', 'processing_days_min' => 3, 'processing_days_max' => 5]);
    VisaType::query()->create(['country_id' => $france->id, 'code' => 'TOURIST', 'name' => 'Tourist', 'processing_days_min' => 3, 'processing_days_max' => 5]);

    expect(VisaType::query()->count())->toBe(2);
});

test('the same code cannot be reused within the same country', function () {
    $country = makeCountry();

    VisaType::query()->create(['country_id' => $country->id, 'code' => 'TOURIST', 'name' => 'Tourist', 'processing_days_min' => 3, 'processing_days_max' => 5]);
    VisaType::query()->create(['country_id' => $country->id, 'code' => 'TOURIST', 'name' => 'Duplicate', 'processing_days_min' => 3, 'processing_days_max' => 5]);
})->throws(QueryException::class);

test('processing_days_max cannot be less than processing_days_min', function () {
    $country = makeCountry();

    VisaType::query()->create(['country_id' => $country->id, 'code' => 'TOURIST', 'name' => 'Tourist', 'processing_days_min' => 10, 'processing_days_max' => 5]);
})->throws(QueryException::class);

test('a visa type cannot reference a nonexistent country', function () {
    VisaType::query()->create(['country_id' => 999999, 'code' => 'TOURIST', 'name' => 'Tourist', 'processing_days_min' => 3, 'processing_days_max' => 5]);
})->throws(QueryException::class);

test('a visa type belongs to its country', function () {
    $country = makeCountry();
    $visaType = VisaType::query()->create(['country_id' => $country->id, 'code' => 'TOURIST', 'name' => 'Tourist', 'processing_days_min' => 3, 'processing_days_max' => 5]);

    expect($visaType->country->is($country))->toBeTrue();
});
