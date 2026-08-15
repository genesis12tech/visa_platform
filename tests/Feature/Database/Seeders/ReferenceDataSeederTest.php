<?php

use App\Models\Country;
use App\Models\Currency;
use App\Models\VisaFee;
use App\Models\VisaType;
use Illuminate\Support\Facades\Artisan;

test('seeding produces 20 countries, 4 visa types, and 8 fee rules with the required edge cases', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ReferenceDataSeeder']);

    expect(Country::query()->count())->toBe(20)
        ->and(VisaType::query()->count())->toBe(4)
        ->and(VisaFee::query()->count())->toBe(8)
        ->and(Currency::query()->count())->toBeGreaterThanOrEqual(4);

    expect(VisaFee::query()->whereNotNull('valid_until')->where('valid_until', '<', now())->exists())
        ->toBeTrue();

    expect(VisaFee::query()->whereNotNull('nationality_country_id')->exists())
        ->toBeTrue();
});

test('seeding is idempotent — running it twice does not create duplicates', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ReferenceDataSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ReferenceDataSeeder']);

    expect(Country::query()->count())->toBe(20)
        ->and(VisaType::query()->count())->toBe(4)
        ->and(VisaFee::query()->count())->toBe(8);
});
