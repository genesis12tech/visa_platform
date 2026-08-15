<?php

use App\Models\Country;
use App\Models\Holiday;
use App\Models\ServiceLocation;
use Illuminate\Database\QueryException;

function makeLocation(): ServiceLocation
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

test('a holiday can be scoped to a specific location', function () {
    $location = makeLocation();

    $holiday = Holiday::query()->create([
        'location_id' => $location->id,
        'holiday_date' => '2026-10-02',
        'description' => 'Gandhi Jayanti',
    ]);

    expect($holiday->location->is($location))->toBeTrue()
        ->and($holiday->getAttributes())->not->toHaveKey('ulid')
        ->and($holiday->getAttributes())->not->toHaveKey('updated_at');
});

test('a holiday can be mission-wide with a null location', function () {
    $holiday = Holiday::query()->create([
        'location_id' => null,
        'holiday_date' => '2026-01-26',
        'description' => 'Republic Day',
    ]);

    expect($holiday->location_id)->toBeNull();
});

test('the same location cannot have two holiday rows on the same date', function () {
    $location = makeLocation();

    Holiday::query()->create(['location_id' => $location->id, 'holiday_date' => '2026-10-02', 'description' => 'Gandhi Jayanti']);
    Holiday::query()->create(['location_id' => $location->id, 'holiday_date' => '2026-10-02', 'description' => 'Duplicate']);
})->throws(QueryException::class);

test('deleting a service location cascades to its holidays', function () {
    $location = makeLocation();
    Holiday::query()->create(['location_id' => $location->id, 'holiday_date' => '2026-10-02', 'description' => 'Gandhi Jayanti']);

    $location->delete();

    expect(Holiday::query()->count())->toBe(0);
});
