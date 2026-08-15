<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use App\Models\VisaFee;
use App\Models\VisaType;
use App\Support\Money\Money;
use Illuminate\Database\Seeder;

/**
 * Pilot reference data per Implementation_plan.md S2.2: 20 countries, 4 visa
 * types, 8 fee rules including one nationality-specific and one expired.
 * Idempotent throughout (updateOrCreate on each table's natural key) so it's
 * safe to re-run.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $currencies = $this->seedCurrencies();
        $countries = $this->seedCountries();
        $visaTypes = $this->seedVisaTypes($countries['IN']);
        $this->seedVisaFees($visaTypes, $countries);
    }

    /**
     * @return array<string, Currency>
     */
    private function seedCurrencies(): array
    {
        $rows = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'minor_unit_exponent' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'minor_unit_exponent' => 2],
            ['code' => 'GBP', 'name' => 'Pound Sterling', 'symbol' => '£', 'minor_unit_exponent' => 2],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'minor_unit_exponent' => 0],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'minor_unit_exponent' => 2],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'AED', 'minor_unit_exponent' => 2],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'minor_unit_exponent' => 2],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out[$row['code']] = Currency::query()->updateOrCreate(['code' => $row['code']], $row);
        }

        return $out;
    }

    /**
     * @return array<string, Country>
     */
    private function seedCountries(): array
    {
        // Destination first, then 19 representative applicant nationalities.
        $rows = [
            ['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India'],
            ['iso2' => 'US', 'iso3' => 'USA', 'name' => 'United States'],
            ['iso2' => 'GB', 'iso3' => 'GBR', 'name' => 'United Kingdom'],
            ['iso2' => 'CA', 'iso3' => 'CAN', 'name' => 'Canada'],
            ['iso2' => 'AU', 'iso3' => 'AUS', 'name' => 'Australia'],
            ['iso2' => 'DE', 'iso3' => 'DEU', 'name' => 'Germany'],
            ['iso2' => 'FR', 'iso3' => 'FRA', 'name' => 'France'],
            ['iso2' => 'JP', 'iso3' => 'JPN', 'name' => 'Japan'],
            ['iso2' => 'CN', 'iso3' => 'CHN', 'name' => 'China'],
            ['iso2' => 'SG', 'iso3' => 'SGP', 'name' => 'Singapore'],
            ['iso2' => 'AE', 'iso3' => 'ARE', 'name' => 'United Arab Emirates'],
            ['iso2' => 'BR', 'iso3' => 'BRA', 'name' => 'Brazil'],
            ['iso2' => 'ZA', 'iso3' => 'ZAF', 'name' => 'South Africa'],
            ['iso2' => 'NG', 'iso3' => 'NGA', 'name' => 'Nigeria'],
            ['iso2' => 'KE', 'iso3' => 'KEN', 'name' => 'Kenya'],
            ['iso2' => 'RU', 'iso3' => 'RUS', 'name' => 'Russia'],
            ['iso2' => 'IT', 'iso3' => 'ITA', 'name' => 'Italy'],
            ['iso2' => 'ES', 'iso3' => 'ESP', 'name' => 'Spain'],
            ['iso2' => 'NL', 'iso3' => 'NLD', 'name' => 'Netherlands'],
            ['iso2' => 'KR', 'iso3' => 'KOR', 'name' => 'South Korea'],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out[$row['iso2']] = Country::query()->updateOrCreate(['iso2' => $row['iso2']], $row);
        }

        return $out;
    }

    /**
     * @return array<string, VisaType>
     */
    private function seedVisaTypes(Country $india): array
    {
        $rows = [
            ['code' => 'TOURIST', 'name' => 'Tourist Visa', 'processing_days_min' => 2, 'processing_days_max' => 4, 'requires_biometrics' => false],
            ['code' => 'BUSINESS', 'name' => 'Business Visa', 'processing_days_min' => 3, 'processing_days_max' => 5, 'requires_biometrics' => false],
            ['code' => 'STUDENT', 'name' => 'Student Visa', 'processing_days_min' => 10, 'processing_days_max' => 20, 'requires_biometrics' => true, 'requires_interview' => true],
            ['code' => 'EMPLOYMENT', 'name' => 'Employment Visa', 'processing_days_min' => 10, 'processing_days_max' => 20, 'requires_biometrics' => true],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out[$row['code']] = VisaType::query()->updateOrCreate(
                ['country_id' => $india->id, 'code' => $row['code']],
                $row + ['country_id' => $india->id, 'is_active' => true]
            );
        }

        return $out;
    }

    /**
     * @param  array<string, VisaType>  $visaTypes
     * @param  array<string, Country>  $countries
     */
    private function seedVisaFees(array $visaTypes, array $countries): void
    {
        $rules = [
            // Tourist: general rate, active.
            [
                'visa_type_id' => $visaTypes['TOURIST']->id,
                'nationality_country_id' => null,
                'currency' => 'USD',
                'base_fee_minor' => Money::of(2500, 'USD'),
                'service_fee_minor' => Money::of(1000, 'USD'),
                'valid_from' => '2026-01-01',
                'valid_until' => null,
            ],
            // Tourist: nationality-specific rate for Japan.
            [
                'visa_type_id' => $visaTypes['TOURIST']->id,
                'nationality_country_id' => $countries['JP']->id,
                'currency' => 'USD',
                'base_fee_minor' => Money::of(4000, 'USD'),
                'service_fee_minor' => Money::of(1000, 'USD'),
                'valid_from' => '2026-01-01',
                'valid_until' => null,
            ],
            // Tourist: the expired rate this general rule superseded.
            [
                'visa_type_id' => $visaTypes['TOURIST']->id,
                'nationality_country_id' => null,
                'currency' => 'USD',
                'base_fee_minor' => Money::of(2000, 'USD'),
                'service_fee_minor' => Money::of(800, 'USD'),
                'valid_from' => '2025-01-01',
                'valid_until' => '2025-12-31',
            ],
            // Business: general rate.
            [
                'visa_type_id' => $visaTypes['BUSINESS']->id,
                'nationality_country_id' => null,
                'currency' => 'USD',
                'base_fee_minor' => Money::of(8000, 'USD'),
                'service_fee_minor' => Money::of(2000, 'USD'),
                'valid_from' => '2026-01-01',
                'valid_until' => null,
            ],
            // Business: nationality-specific rate for UAE.
            [
                'visa_type_id' => $visaTypes['BUSINESS']->id,
                'nationality_country_id' => $countries['AE']->id,
                'currency' => 'AED',
                'base_fee_minor' => Money::of(30000, 'AED'),
                'service_fee_minor' => Money::of(5000, 'AED'),
                'valid_from' => '2026-01-01',
                'valid_until' => null,
            ],
            // Student: general rate.
            [
                'visa_type_id' => $visaTypes['STUDENT']->id,
                'nationality_country_id' => null,
                'currency' => 'USD',
                'base_fee_minor' => Money::of(10000, 'USD'),
                'service_fee_minor' => Money::of(2000, 'USD'),
                'valid_from' => '2026-01-01',
                'valid_until' => null,
            ],
            // Student: nationality-specific rate for China.
            [
                'visa_type_id' => $visaTypes['STUDENT']->id,
                'nationality_country_id' => $countries['CN']->id,
                'currency' => 'USD',
                'base_fee_minor' => Money::of(12000, 'USD'),
                'service_fee_minor' => Money::of(2000, 'USD'),
                'valid_from' => '2026-01-01',
                'valid_until' => null,
            ],
            // Employment: general rate.
            [
                'visa_type_id' => $visaTypes['EMPLOYMENT']->id,
                'nationality_country_id' => null,
                'currency' => 'USD',
                'base_fee_minor' => Money::of(15000, 'USD'),
                'service_fee_minor' => Money::of(3000, 'USD'),
                'valid_from' => '2026-01-01',
                'valid_until' => null,
            ],
        ];

        foreach ($rules as $rule) {
            VisaFee::query()->updateOrCreate(
                [
                    'visa_type_id' => $rule['visa_type_id'],
                    'nationality_country_id' => $rule['nationality_country_id'],
                    'valid_from' => $rule['valid_from'],
                ],
                $rule + ['is_active' => true]
            );
        }
    }
}
