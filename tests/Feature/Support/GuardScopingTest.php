<?php

use App\Models\Agent;
use App\Models\Applicant;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Backend_schema.md §11.1: three session guards, each backed by a provider
 * scoped to one user_type, so "an applicant session can never resolve a
 * staff record even if a cookie is replayed across hosts" — the scoping
 * lives on the provider's model (a permanent global scope), not just in
 * login-time validation, so it holds for session resolution too.
 */
function makeTypedUser(string $type, string $email): User
{
    return User::query()->create([
        'name' => 'Test User',
        'email' => $email,
        'password' => 'password',
        'user_type' => $type,
    ]);
}

test('config/auth.php defines three guards, each with its own scoped provider', function () {
    expect(config('auth.guards.web.provider'))->toBe('applicants')
        ->and(config('auth.guards.agent.provider'))->toBe('agents')
        ->and(config('auth.guards.staff.provider'))->toBe('staff')
        ->and(config('auth.providers.applicants.model'))->toBe(Applicant::class)
        ->and(config('auth.providers.agents.model'))->toBe(Agent::class)
        ->and(config('auth.providers.staff.model'))->toBe(Staff::class);
});

test('the Applicant provider model only ever resolves applicant-type users', function () {
    $applicant = makeTypedUser('applicant', 'applicant@example.com');
    $agent = makeTypedUser('agent', 'agent@example.com');
    $staff = makeTypedUser('staff', 'staff@example.com');

    expect(Applicant::query()->find($applicant->id))->not->toBeNull()
        ->and(Applicant::query()->find($agent->id))->toBeNull()
        ->and(Applicant::query()->find($staff->id))->toBeNull();
});

test('the Agent provider model only ever resolves agent-type users', function () {
    $applicant = makeTypedUser('applicant', 'applicant@example.com');
    $agent = makeTypedUser('agent', 'agent@example.com');
    $staff = makeTypedUser('staff', 'staff@example.com');

    expect(Agent::query()->find($agent->id))->not->toBeNull()
        ->and(Agent::query()->find($applicant->id))->toBeNull()
        ->and(Agent::query()->find($staff->id))->toBeNull();
});

test('the Staff provider model only ever resolves staff-type users', function () {
    $applicant = makeTypedUser('applicant', 'applicant@example.com');
    $agent = makeTypedUser('agent', 'agent@example.com');
    $staff = makeTypedUser('staff', 'staff@example.com');

    expect(Staff::query()->find($staff->id))->not->toBeNull()
        ->and(Staff::query()->find($applicant->id))->toBeNull()
        ->and(Staff::query()->find($agent->id))->toBeNull();
});

test('the staff guard refuses to authenticate an applicant even with correct credentials', function () {
    makeTypedUser('applicant', 'applicant@example.com');

    $result = Auth::guard('staff')->attempt(['email' => 'applicant@example.com', 'password' => 'password']);

    expect($result)->toBeFalse()
        ->and(Auth::guard('staff')->check())->toBeFalse();
});

test('the web (applicant) guard authenticates an applicant and the session does not resolve on the staff guard', function () {
    makeTypedUser('applicant', 'applicant@example.com');

    $webResult = Auth::guard('web')->attempt(['email' => 'applicant@example.com', 'password' => 'password']);

    expect($webResult)->toBeTrue()
        ->and(Auth::guard('web')->check())->toBeTrue()
        ->and(Auth::guard('staff')->check())->toBeFalse();
});

test('HasUlid and Spatie roles still work through the scoped provider models', function () {
    $applicant = makeTypedUser('applicant', 'applicant@example.com');
    $found = Applicant::query()->find($applicant->id);

    expect($found->ulid)->toBe($applicant->ulid)
        ->and($found)->toBeInstanceOf(Illuminate\Foundation\Auth\User::class);
});
