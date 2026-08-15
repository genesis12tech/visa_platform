<?php

use App\Models\Currency;
use Illuminate\Database\QueryException;

test('a currency can be created with its minor unit exponent', function () {
    $currency = Currency::query()->create([
        'code' => 'usd',
        'name' => 'US Dollar',
        'symbol' => '$',
        'minor_unit_exponent' => 2,
    ]);

    expect($currency->code)->toBe('USD')
        ->and($currency->minor_unit_exponent)->toBe(2)
        ->and($currency->is_active)->toBeTrue();
});

test('a zero-decimal currency like JPY is representable', function () {
    $currency = Currency::query()->create([
        'code' => 'JPY',
        'name' => 'Japanese Yen',
        'symbol' => '¥',
        'minor_unit_exponent' => 0,
    ]);

    expect($currency->minor_unit_exponent)->toBe(0);
});

test('the currency code must be unique', function () {
    Currency::query()->create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'minor_unit_exponent' => 2]);
    Currency::query()->create(['code' => 'USD', 'name' => 'Duplicate', 'symbol' => '$', 'minor_unit_exponent' => 2]);
})->throws(QueryException::class);

test('minor_unit_exponent cannot exceed 4', function () {
    Currency::query()->create(['code' => 'ABC', 'name' => 'Test', 'symbol' => 'A', 'minor_unit_exponent' => 5]);
})->throws(QueryException::class);

test('a currency has no public ulid — reference tables are never addressed publicly', function () {
    $currency = Currency::query()->create(['code' => 'GBP', 'name' => 'Pound Sterling', 'symbol' => '£', 'minor_unit_exponent' => 2]);

    expect($currency->getAttributes())->not->toHaveKey('ulid');
});
