<?php

use App\Models\User;
use App\Models\UserMfaMethod;

/**
 * Backend_schema.md §11.4: "any staff user without mfa_enabled_at can reach
 * ONLY the enrolment routes, in every non-local environment."
 */
function makeStaffUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff',
    ], $overrides));
}

test('a staff user without MFA enrolled is redirected away from a protected route to enrolment', function () {
    app()['env'] = 'production';
    $user = makeStaffUser();

    $response = $this->actingAs($user, 'staff')->get('http://'.config('app.staff_domain').'/');

    $response->assertRedirect(route('staff.mfa.enroll'));
});

test('a staff user without MFA enrolled can still reach the enrolment routes themselves', function () {
    app()['env'] = 'production';
    $user = makeStaffUser();

    $response = $this->actingAs($user, 'staff')->get(route('staff.mfa.enroll'));

    $response->assertOk();
});

test('a staff user with MFA enrolled passes through to the protected route normally', function () {
    app()['env'] = 'production';
    $user = makeStaffUser();
    $user->forceFill(['mfa_enabled_at' => now()])->save();
    UserMfaMethod::query()->create(['user_id' => $user->id, 'secret_encrypted' => 'AAA', 'confirmed_at' => now()]);

    $response = $this->actingAs($user, 'staff')->get('http://'.config('app.staff_domain').'/');

    $response->assertOk();
});

test('the check is skipped entirely in the local environment', function () {
    app()['env'] = 'local';
    $user = makeStaffUser();

    $response = $this->actingAs($user, 'staff')->get('http://'.config('app.staff_domain').'/');

    $response->assertOk();
});

test('a non-staff guard is never subject to this check', function () {
    app()['env'] = 'production';
    $applicant = User::query()->create(['name' => 'Priya', 'email' => 'priya@example.com', 'password' => 'x']);

    $response = $this->actingAs($applicant, 'web')->get('/');

    $response->assertOk();
});
