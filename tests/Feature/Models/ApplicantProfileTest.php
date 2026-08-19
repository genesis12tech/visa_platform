<?php

use App\Models\ApplicantProfile;
use App\Models\Country;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function makeApplicantUser(): User
{
    return User::query()->create(['name' => 'Priya Sharma', 'email' => 'priya@example.com', 'password' => 'x']);
}

test('the passport number is stored encrypted — a raw SQL read never returns plaintext', function () {
    $user = makeApplicantUser();
    $india = Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);

    $profile = ApplicantProfile::query()->create([
        'user_id' => $user->id,
        'first_name' => 'Priya',
        'last_name' => 'Sharma',
        'date_of_birth' => '1991-03-14',
        'nationality_country_id' => $india->id,
        'passport_number_encrypted' => 'M1234567',
    ]);

    // The critical assertion: read the column directly with SQL, bypassing Eloquent's cast entirely.
    $raw = DB::table('applicant_profiles')->where('id', $profile->id)->value('passport_number_encrypted');

    expect($raw)->not->toContain('M1234567')
        ->and($raw)->not->toBeNull();

    // But through the model, it decrypts transparently.
    $fresh = ApplicantProfile::query()->find($profile->id);
    expect($fresh->passport_number_encrypted)->toBe('M1234567');
});

test('a blind-index hash is computed automatically whenever the passport number is set', function () {
    $user = makeApplicantUser();
    $india = Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);

    $profile = ApplicantProfile::query()->create([
        'user_id' => $user->id,
        'first_name' => 'Priya',
        'last_name' => 'Sharma',
        'date_of_birth' => '1991-03-14',
        'nationality_country_id' => $india->id,
        'passport_number_encrypted' => 'M1234567',
    ]);

    expect($profile->passport_number_hash)->toHaveLength(64)
        ->and($profile->passport_number_hash)->toBe(ApplicantProfile::hashPassportNumber('M1234567'));
});

test('the blind index is stable regardless of case or surrounding whitespace — it finds duplicates', function () {
    expect(ApplicantProfile::hashPassportNumber('m1234567'))
        ->toBe(ApplicantProfile::hashPassportNumber(' M1234567 '))
        ->toBe(ApplicantProfile::hashPassportNumber('M1234567'));
});

test('two different passport numbers hash to different values', function () {
    expect(ApplicantProfile::hashPassportNumber('M1234567'))
        ->not->toBe(ApplicantProfile::hashPassportNumber('M7654321'));
});

test('a profile with no passport number set has no hash', function () {
    $user = makeApplicantUser();
    $india = Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);

    $profile = ApplicantProfile::query()->create([
        'user_id' => $user->id,
        'first_name' => 'Priya',
        'last_name' => 'Sharma',
        'date_of_birth' => '1991-03-14',
        'nationality_country_id' => $india->id,
    ]);

    expect($profile->passport_number_hash)->toBeNull()
        ->and($profile->passport_number_encrypted)->toBeNull();
});

test('a user can have only one applicant profile', function () {
    $user = makeApplicantUser();
    $india = Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);

    ApplicantProfile::query()->create(['user_id' => $user->id, 'first_name' => 'Priya', 'last_name' => 'Sharma', 'date_of_birth' => '1991-03-14', 'nationality_country_id' => $india->id]);
    ApplicantProfile::query()->create(['user_id' => $user->id, 'first_name' => 'Priya', 'last_name' => 'Sharma', 'date_of_birth' => '1991-03-14', 'nationality_country_id' => $india->id]);
})->throws(QueryException::class);

test('deleting the user cascades to their profile', function () {
    $user = makeApplicantUser();
    $india = Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);
    ApplicantProfile::query()->create(['user_id' => $user->id, 'first_name' => 'Priya', 'last_name' => 'Sharma', 'date_of_birth' => '1991-03-14', 'nationality_country_id' => $india->id]);

    $user->forceDelete();

    expect(ApplicantProfile::query()->count())->toBe(0);
});

test('passport_expires_at must be after passport_issued_at when both are set', function () {
    $user = makeApplicantUser();
    $india = Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);

    ApplicantProfile::query()->create([
        'user_id' => $user->id, 'first_name' => 'Priya', 'last_name' => 'Sharma',
        'date_of_birth' => '1991-03-14', 'nationality_country_id' => $india->id,
        'passport_issued_at' => '2026-01-01',
        'passport_expires_at' => '2025-01-01',
    ]);
})->throws(QueryException::class)->skip(fn () => ! databaseEnforcesCheckConstraints(), 'CHECK constraints are not enforced on this engine');

test('a profile belongs to its nationality country', function () {
    $user = makeApplicantUser();
    $india = Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);
    $profile = ApplicantProfile::query()->create(['user_id' => $user->id, 'first_name' => 'Priya', 'last_name' => 'Sharma', 'date_of_birth' => '1991-03-14', 'nationality_country_id' => $india->id]);

    expect($profile->nationalityCountry->is($india))->toBeTrue()
        ->and($profile->user->is($user))->toBeTrue();
});
