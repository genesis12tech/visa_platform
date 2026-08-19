<?php

use App\Models\Country;
use App\Models\VisaFee;
use App\Models\VisaType;
use App\Support\Money\Money;
use Illuminate\Database\QueryException;

function makeVisaType(): VisaType
{
    $country = Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);

    return VisaType::query()->create([
        'country_id' => $country->id,
        'code' => 'TOURIST',
        'name' => 'Tourist Visa',
        'processing_days_min' => 3,
        'processing_days_max' => 5,
    ]);
}

test('a visa fee is created with all four money columns sharing one currency', function () {
    $visaType = makeVisaType();

    $fee = VisaFee::query()->create([
        'visa_type_id' => $visaType->id,
        'currency' => 'usd',
        'base_fee_minor' => Money::of(14000, 'USD'),
        'service_fee_minor' => Money::of(2000, 'USD'),
        'priority_fee_minor' => Money::of(0, 'USD'),
        'tax_minor' => Money::of(0, 'USD'),
        'valid_from' => '2026-01-01',
    ]);

    expect($fee->ulid)->toHaveLength(26)
        ->and($fee->base_fee_minor)->toBeInstanceOf(Money::class)
        ->and($fee->base_fee_minor->minorAmount())->toBe(14000)
        ->and($fee->service_fee_minor->minorAmount())->toBe(2000)
        ->and($fee->currency)->toBe('USD')
        ->and($fee->is_active)->toBeTrue();
});

test('nationality_country_id is nullable — null means the fee applies to all nationalities', function () {
    $visaType = makeVisaType();

    $fee = VisaFee::query()->create([
        'visa_type_id' => $visaType->id,
        'nationality_country_id' => null,
        'currency' => 'USD',
        'base_fee_minor' => Money::of(14000, 'USD'),
        'valid_from' => '2026-01-01',
    ]);

    expect($fee->nationality_country_id)->toBeNull();
});

test('a fee rule can be scoped to a specific nationality', function () {
    $visaType = makeVisaType();
    $japan = Country::query()->create(['iso2' => 'JP', 'iso3' => 'JPN', 'name' => 'Japan']);

    $fee = VisaFee::query()->create([
        'visa_type_id' => $visaType->id,
        'nationality_country_id' => $japan->id,
        'currency' => 'JPY',
        'base_fee_minor' => Money::of(3000, 'JPY'),
        'valid_from' => '2026-01-01',
    ]);

    expect($fee->nationalityCountry->is($japan))->toBeTrue();
});

test('fee amounts cannot be negative', function () {
    $visaType = makeVisaType();

    VisaFee::query()->create([
        'visa_type_id' => $visaType->id,
        'currency' => 'USD',
        'base_fee_minor' => Money::of(-100, 'USD'),
        'valid_from' => '2026-01-01',
    ]);
})->throws(QueryException::class)->skip(fn () => ! databaseEnforcesCheckConstraints(), 'CHECK constraints are not enforced on this engine');

test('valid_until must be strictly after valid_from when set', function () {
    $visaType = makeVisaType();

    VisaFee::query()->create([
        'visa_type_id' => $visaType->id,
        'currency' => 'USD',
        'base_fee_minor' => Money::of(14000, 'USD'),
        'valid_from' => '2026-06-01',
        'valid_until' => '2026-01-01',
    ]);
})->throws(QueryException::class)->skip(fn () => ! databaseEnforcesCheckConstraints(), 'CHECK constraints are not enforced on this engine');

test('valid_until may be left open-ended (null)', function () {
    $visaType = makeVisaType();

    $fee = VisaFee::query()->create([
        'visa_type_id' => $visaType->id,
        'currency' => 'USD',
        'base_fee_minor' => Money::of(14000, 'USD'),
        'valid_from' => '2026-01-01',
    ]);

    expect($fee->valid_until)->toBeNull();
});

test('a fee rule belongs to its visa type', function () {
    $visaType = makeVisaType();

    $fee = VisaFee::query()->create([
        'visa_type_id' => $visaType->id,
        'currency' => 'USD',
        'base_fee_minor' => Money::of(14000, 'USD'),
        'valid_from' => '2026-01-01',
    ]);

    expect($fee->visaType->is($visaType))->toBeTrue();
});
