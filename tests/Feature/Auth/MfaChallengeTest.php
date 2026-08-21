<?php

use App\Models\AuditLog;
use App\Models\MfaRecoveryCode;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserMfaMethod;
use App\Notifications\MfaChallengeLocked;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FAQRCode\Google2FA;

function makeEnrolledStaffUser(): array
{
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => Hash::make('correct-password'), 'user_type' => 'staff']);
    $user->forceFill(['email_verified_at' => now()])->save();
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();

    UserMfaMethod::query()->create([
        'user_id' => $user->id,
        'type' => 'totp',
        'secret_encrypted' => $secret,
        'confirmed_at' => now(),
    ]);

    $user->forceFill(['mfa_enabled_at' => now()])->save();

    return [$user, $secret];
}

beforeEach(function () {
    RateLimiter::clear('login-email:chen@example.com');
    RateLimiter::clear('login-ip:127.0.0.1');
});

test('logging in as an MFA-enrolled staff member does not complete the session, it redirects to the challenge', function () {
    [$user] = makeEnrolledStaffUser();

    $response = $this->post('http://'.config('app.staff_domain').'/login', ['email' => 'chen@example.com', 'password' => 'correct-password']);

    $this->assertGuest('staff');
    $response->assertRedirect(route('staff.mfa.challenge'));
});

test('a correct TOTP code at the challenge completes the login', function () {
    [$user, $secret] = makeEnrolledStaffUser();
    $google2fa = new Google2FA;

    $this->post('http://'.config('app.staff_domain').'/login', ['email' => 'chen@example.com', 'password' => 'correct-password']);

    $response = $this->post(route('staff.mfa.challenge.store'), ['code' => $google2fa->getCurrentOtp($secret)]);

    // Auth::guard('staff')->login() inside the controller receives a Staff
    // instance (guard-scoped), not User — assertAuthenticatedAs compares
    // strictly, so the assertion must match what's actually logged in.
    $this->assertAuthenticatedAs(Staff::query()->find($user->id), 'staff');
    $response->assertRedirect('/');
    expect(UserMfaMethod::query()->where('user_id', $user->id)->first()->last_used_at)->not->toBeNull();
});

test('a valid unused recovery code completes the login and is marked used, single-use', function () {
    [$user] = makeEnrolledStaffUser();
    [$plainCode] = MfaRecoveryCode::generateFor($user);

    $this->post('http://'.config('app.staff_domain').'/login', ['email' => 'chen@example.com', 'password' => 'correct-password']);

    $response = $this->post(route('staff.mfa.challenge.store'), ['code' => $plainCode]);

    $this->assertAuthenticatedAs(Staff::query()->find($user->id), 'staff');
    $response->assertRedirect('/');

    $usedCode = MfaRecoveryCode::query()->where('user_id', $user->id)->first();
    expect($usedCode->used_at)->not->toBeNull();

    // Log back out and try to replay the same code — must fail now.
    $this->post(route('staff.logout'));
    $this->post('http://'.config('app.staff_domain').'/login', ['email' => 'chen@example.com', 'password' => 'correct-password']);
    $replay = $this->post(route('staff.mfa.challenge.store'), ['code' => $plainCode]);

    $replay->assertSessionHasErrors('code');
    $this->assertGuest('staff');
});

test('completing the challenge writes an auth.login audit log entry, not the earlier password step', function () {
    [$user, $secret] = makeEnrolledStaffUser();
    $google2fa = new Google2FA;

    $this->post('http://'.config('app.staff_domain').'/login', ['email' => 'chen@example.com', 'password' => 'correct-password']);
    expect(AuditLog::query()->where('action', 'auth.login')->count())->toBe(0);

    $this->post(route('staff.mfa.challenge.store'), ['code' => $google2fa->getCurrentOtp($secret)]);

    $log = AuditLog::query()->where('action', 'auth.login')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->actor_user_id)->toBe($user->id);
});

test('an incorrect code does not complete the login', function () {
    makeEnrolledStaffUser();

    $this->post('http://'.config('app.staff_domain').'/login', ['email' => 'chen@example.com', 'password' => 'correct-password']);
    $response = $this->post(route('staff.mfa.challenge.store'), ['code' => '000000']);

    $response->assertSessionHasErrors('code');
    $this->assertGuest('staff');
});

test('five failed challenge attempts lock further attempts and email the account owner', function () {
    Notification::fake();
    [$user] = makeEnrolledStaffUser();

    $this->post('http://'.config('app.staff_domain').'/login', ['email' => 'chen@example.com', 'password' => 'correct-password']);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('staff.mfa.challenge.store'), ['code' => '000000']);
    }

    $response = $this->post(route('staff.mfa.challenge.store'), ['code' => '000000']);

    $response->assertSessionHasErrors('code');
    Notification::assertSentTo(Staff::query()->find($user->id), MfaChallengeLocked::class);
});

test('the challenge cannot be reached without a pending login', function () {
    $response = $this->get(route('staff.mfa.challenge'));

    $response->assertRedirect(route('staff.login'));
});
