<?php

use App\Models\Country;
use Illuminate\Database\QueryException;

test('a country can be created with iso2 and iso3 codes', function () {
    $country = Country::query()->create(['iso2' => 'in', 'iso3' => 'ind', 'name' => 'India']);

    expect($country->iso2)->toBe('IN')
        ->and($country->iso3)->toBe('IND')
        ->and($country->is_active)->toBeTrue();
});

test('iso2 must be unique', function () {
    Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);
    Country::query()->create(['iso2' => 'IN', 'iso3' => 'XXX', 'name' => 'Duplicate']);
})->throws(QueryException::class);

test('iso3 must be unique', function () {
    Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);
    Country::query()->create(['iso2' => 'XX', 'iso3' => 'IND', 'name' => 'Duplicate']);
})->throws(QueryException::class);

test('a country has no public ulid', function () {
    $country = Country::query()->create(['iso2' => 'FR', 'iso3' => 'FRA', 'name' => 'France']);

    expect($country->getAttributes())->not->toHaveKey('ulid');
});
