<?php

use App\Models\Applicant;
use App\Models\User;
use App\Notifications\PasswordWasReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/**
 * FR-ID-11: expiring single-use tokens, account owner notified on reset.
 * Non-enumeration matches the project's stance elsewhere (PUB-05 for
 * registration) — the forgot-password response never reveals whether the
 * email exists.
 */
test('requesting a reset link for a known email queues the reset notification', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'priya@example.com']);

    $response = $this->post('/forgot-password', ['email' => 'priya@example.com']);

    $response->assertSessionHasNoErrors();
    // Password::broker('applicants') resolves users through the Applicant
    // scoped model, not User directly — Notification::fake() buckets by
    // exact class, so the assertion must match that concrete class.
    Notification::assertSentTo(Applicant::query()->find($user->id), ResetPassword::class);
});

test('requesting a reset link for an unknown email responds identically without sending anything', function () {
    Notification::fake();

    $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

    $response->assertSessionHasNoErrors();
    Notification::assertNothingSent();
});

test('a valid token resets the password and notifies the account owner', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'priya@example.com', 'password' => Hash::make('old-password')]);
    $token = Password::broker('applicants')->createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => 'priya@example.com',
        'password' => 'a-new-secure-password',
        'password_confirmation' => 'a-new-secure-password',
    ]);

    $response->assertSessionHasNoErrors();
    expect(Hash::check('a-new-secure-password', $user->fresh()->password))->toBeTrue();
    Notification::assertSentTo(Applicant::query()->find($user->id), PasswordWasReset::class);
});

test('an invalid token is rejected and the password is not changed', function () {
    $user = User::factory()->create(['email' => 'priya@example.com', 'password' => Hash::make('old-password')]);

    $response = $this->post('/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'priya@example.com',
        'password' => 'a-new-secure-password',
        'password_confirmation' => 'a-new-secure-password',
    ]);

    $response->assertSessionHasErrors('email');
    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

test('a used token cannot be replayed to reset the password twice', function () {
    $user = User::factory()->create(['email' => 'priya@example.com', 'password' => Hash::make('old-password')]);
    $token = Password::broker('applicants')->createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'priya@example.com',
        'password' => 'first-new-password',
        'password_confirmation' => 'first-new-password',
    ]);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => 'priya@example.com',
        'password' => 'second-new-password',
        'password_confirmation' => 'second-new-password',
    ]);

    $response->assertSessionHasErrors('email');
    expect(Hash::check('first-new-password', $user->fresh()->password))->toBeTrue();
});

test('resetting on the staff broker uses the staff guard, not applicants', function () {
    Notification::fake();
    $staff = User::factory()->create(['email' => 'chen@example.com', 'password' => Hash::make('old-password'), 'user_type' => 'staff']);
    $token = Password::broker('staff')->createToken($staff);

    $response = $this->post('http://'.config('app.staff_domain').'/reset-password', [
        'token' => $token,
        'email' => 'chen@example.com',
        'password' => 'a-new-secure-password',
        'password_confirmation' => 'a-new-secure-password',
    ]);

    $response->assertSessionHasNoErrors();
    expect(Hash::check('a-new-secure-password', $staff->fresh()->password))->toBeTrue();
});
