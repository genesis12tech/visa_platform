<?php

use App\Models\Country;
use App\Models\VisaFee;
use App\Models\VisaType;
use App\Services\Exceptions\AmbiguousFeeRuleException;
use App\Services\Exceptions\FeeRuleNotFoundException;
use App\Services\FeeResolver;
use App\Support\Money\Money;
use Illuminate\Support\Carbon;

function feeVisaType(): VisaType
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

function makeFee(VisaType $visaType, array $overrides = []): VisaFee
{
    return VisaFee::query()->create(array_merge([
        'visa_type_id' => $visaType->id,
        'nationality_country_id' => null,
        'currency' => 'USD',
        'base_fee_minor' => Money::of(14000, 'USD'),
        'valid_from' => '2026-01-01',
        'valid_until' => null,
        'is_active' => true,
    ], $overrides));
}

beforeEach(function () {
    $this->resolver = new FeeResolver;
});

test('resolves the single general rule when no nationality-specific rule exists', function () {
    $visaType = feeVisaType();
    $fee = makeFee($visaType);

    $resolved = $this->resolver->resolve($visaType->id, null, Carbon::parse('2026-06-01'));

    expect($resolved->is($fee))->toBeTrue();
});

test('a nationality-specific rule takes precedence over the general rule', function () {
    $visaType = feeVisaType();
    $japan = Country::query()->create(['iso2' => 'JP', 'iso3' => 'JPN', 'name' => 'Japan']);

    makeFee($visaType); // general
    $specific = makeFee($visaType, ['nationality_country_id' => $japan->id, 'base_fee_minor' => Money::of(40000, 'USD')]);

    $resolved = $this->resolver->resolve($visaType->id, $japan->id, Carbon::parse('2026-06-01'));

    expect($resolved->is($specific))->toBeTrue();
});

test('falls back to the general rule for a nationality with no specific rule', function () {
    $visaType = feeVisaType();
    $france = Country::query()->create(['iso2' => 'FR', 'iso3' => 'FRA', 'name' => 'France']);
    $general = makeFee($visaType);

    $resolved = $this->resolver->resolve($visaType->id, $france->id, Carbon::parse('2026-06-01'));

    expect($resolved->is($general))->toBeTrue();
});

test('valid_from is inclusive — resolving exactly on the start date matches', function () {
    $visaType = feeVisaType();
    $fee = makeFee($visaType, ['valid_from' => '2026-06-01']);

    $resolved = $this->resolver->resolve($visaType->id, null, Carbon::parse('2026-06-01'));

    expect($resolved->is($fee))->toBeTrue();
});

test('valid_until is exclusive — resolving exactly on the end date does not match', function () {
    $visaType = feeVisaType();
    makeFee($visaType, ['valid_from' => '2026-01-01', 'valid_until' => '2026-06-01']);

    $this->resolver->resolve($visaType->id, null, Carbon::parse('2026-06-01'));
})->throws(FeeRuleNotFoundException::class);

test('an expired rule does not interfere with resolving the current rule', function () {
    $visaType = feeVisaType();
    makeFee($visaType, ['valid_from' => '2025-01-01', 'valid_until' => '2025-12-31', 'base_fee_minor' => Money::of(1, 'USD')]);
    $current = makeFee($visaType, ['valid_from' => '2026-01-01']);

    $resolved = $this->resolver->resolve($visaType->id, null, Carbon::parse('2026-06-01'));

    expect($resolved->is($current))->toBeTrue();
});

test('an inactive rule is never resolved even if its date window matches', function () {
    $visaType = feeVisaType();
    makeFee($visaType, ['is_active' => false]);

    $this->resolver->resolve($visaType->id, null, Carbon::parse('2026-06-01'));
})->throws(FeeRuleNotFoundException::class);

test('throws when no rule matches at all', function () {
    $visaType = feeVisaType();

    $this->resolver->resolve($visaType->id, null, Carbon::parse('2026-06-01'));
})->throws(FeeRuleNotFoundException::class);

test('throws AmbiguousFeeRuleException when two general rules overlap for the same date — never a silent pick', function () {
    $visaType = feeVisaType();
    makeFee($visaType, ['valid_from' => '2026-01-01']);
    makeFee($visaType, ['valid_from' => '2026-03-01']);

    $this->resolver->resolve($visaType->id, null, Carbon::parse('2026-06-01'));
})->throws(AmbiguousFeeRuleException::class);

test('throws AmbiguousFeeRuleException when two nationality-specific rules overlap', function () {
    $visaType = feeVisaType();
    $japan = Country::query()->create(['iso2' => 'JP', 'iso3' => 'JPN', 'name' => 'Japan']);
    makeFee($visaType, ['nationality_country_id' => $japan->id, 'valid_from' => '2026-01-01']);
    makeFee($visaType, ['nationality_country_id' => $japan->id, 'valid_from' => '2026-03-01']);

    $this->resolver->resolve($visaType->id, $japan->id, Carbon::parse('2026-06-01'));
})->throws(AmbiguousFeeRuleException::class);

test('defaults to resolving as of now when no date is given', function () {
    $visaType = feeVisaType();
    $fee = makeFee($visaType, ['valid_from' => Carbon::now()->subDay()->toDateString()]);

    $resolved = $this->resolver->resolve($visaType->id, null);

    expect($resolved->is($fee))->toBeTrue();
});
