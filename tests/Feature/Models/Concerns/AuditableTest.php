<?php

use App\Models\AuditLog;
use App\Models\Country;
use App\Models\Currency;
use App\Models\VisaType;

test('creating a reference-data row writes an audit_logs entry with the new values', function () {
    $currency = Currency::query()->create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'minor_unit_exponent' => 2]);

    $log = AuditLog::query()->where('action', 'currency.created')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->auditable_type)->toBe(Currency::class)
        ->and($log->auditable_id)->toBe($currency->id)
        ->and($log->new_values['code'])->toBe('USD');
});

test('updating a reference-data row writes an audit_logs entry with old and new values', function () {
    $currency = Currency::query()->create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'minor_unit_exponent' => 2]);

    $currency->update(['name' => 'United States Dollar']);

    $log = AuditLog::query()->where('action', 'currency.updated')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values['name'])->toBe('US Dollar')
        ->and($log->new_values['name'])->toBe('United States Dollar');
});

test('deleting a reference-data row writes an audit_logs entry with the deleted values', function () {
    $currency = Currency::query()->create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'minor_unit_exponent' => 2]);
    $currencyId = $currency->id;

    $currency->delete();

    $log = AuditLog::query()->where('action', 'currency.deleted')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->auditable_id)->toBe($currencyId)
        ->and($log->old_values['code'])->toBe('USD');
});

test('a second reference-data model (visa_types) is independently audited', function () {
    $country = Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);

    $visaType = VisaType::query()->create([
        'country_id' => $country->id,
        'code' => 'TOURIST',
        'name' => 'Tourist Visa',
        'processing_days_min' => 3,
        'processing_days_max' => 5,
    ]);

    $log = AuditLog::query()->where('action', 'visa_type.created')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->auditable_id)->toBe($visaType->id);
});
