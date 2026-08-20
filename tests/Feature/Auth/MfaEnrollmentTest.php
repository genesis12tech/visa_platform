<?php

use App\Models\MfaRecoveryCode;
use App\Models\User;
use App\Models\UserMfaMethod;
use PragmaRX\Google2FAQRCode\Google2FA;

function makeMfaStaffUser(): User
{
    return User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
}

test('the enrolment page generates and stashes a pending secret with a QR code', function () {
    $user = makeMfaStaffUser();

    $response = $this->actingAs($user, 'staff')->get(route('staff.mfa.enroll'));

    $response->assertOk();
    expect(session('mfa_pending_secret'))->not->toBeNull();
});

test('submitting a valid TOTP code completes enrolment: secret saved, ten recovery codes generated, mfa_enabled_at set', function () {
    $user = makeMfaStaffUser();
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();

    $this->actingAs($user, 'staff')->withSession(['mfa_pending_secret' => $secret])->get(route('staff.mfa.enroll'));

    $validCode = $google2fa->getCurrentOtp($secret);

    $response = $this->actingAs($user, 'staff')
        ->withSession(['mfa_pending_secret' => $secret])
        ->post(route('staff.mfa.enroll.store'), ['code' => $validCode]);

    $response->assertRedirect(route('staff.mfa.recovery-codes'));

    $method = UserMfaMethod::query()->where('user_id', $user->id)->first();
    expect($method)->not->toBeNull()
        ->and($method->secret_encrypted)->toBe($secret)
        ->and($method->confirmed_at)->not->toBeNull()
        ->and(MfaRecoveryCode::query()->where('user_id', $user->id)->count())->toBe(10)
        ->and($user->fresh()->mfa_enabled_at)->not->toBeNull();
});

test('submitting an invalid TOTP code does not enrol the user', function () {
    $user = makeMfaStaffUser();
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();

    $response = $this->actingAs($user, 'staff')
        ->withSession(['mfa_pending_secret' => $secret])
        ->post(route('staff.mfa.enroll.store'), ['code' => '000000']);

    $response->assertSessionHasErrors('code');
    expect(UserMfaMethod::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($user->fresh()->mfa_enabled_at)->toBeNull();
});

test('the recovery codes page shows the plaintext codes exactly once', function () {
    $user = makeMfaStaffUser();
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();
    $validCode = $google2fa->getCurrentOtp($secret);

    $this->actingAs($user, 'staff')
        ->withSession(['mfa_pending_secret' => $secret])
        ->post(route('staff.mfa.enroll.store'), ['code' => $validCode]);

    $first = $this->actingAs($user, 'staff')->get(route('staff.mfa.recovery-codes'));
    $first->assertOk();

    $second = $this->actingAs($user, 'staff')->get(route('staff.mfa.recovery-codes'));
    $second->assertRedirect('/');
});

test('an already-enrolled user visiting the enrolment page is redirected away', function () {
    $user = makeMfaStaffUser();
    $user->forceFill(['mfa_enabled_at' => now()])->save();
    UserMfaMethod::query()->create(['user_id' => $user->id, 'secret_encrypted' => 'AAA', 'confirmed_at' => now()]);

    $response = $this->actingAs($user, 'staff')->get(route('staff.mfa.enroll'));

    $response->assertRedirect('/');
});
