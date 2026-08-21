<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

test('a valid signed link verifies the email, activates the account, and assigns the applicant role', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    Event::fake([Verified::class]);
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user, 'web')->get($url);

    $fresh = $user->fresh();
    expect($fresh->email_verified_at)->not->toBeNull()
        ->and($fresh->status)->toBe('active')
        ->and($fresh->hasRole('applicant'))->toBeTrue();

    Event::assertDispatched(Verified::class);
    $response->assertRedirect('/');
});

test('an invalid hash is rejected', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email@example.com')]
    );

    $response = $this->actingAs($user, 'web')->get($url);

    $response->assertForbidden();
    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('an expired signed link is rejected', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinutes(1),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user, 'web')->get($url);

    $response->assertForbidden();
    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('visiting the verify link again once already verified redirects without reassigning the role or re-dispatching', function () {
    Event::fake([Verified::class]);
    $user = User::factory()->create(); // already verified by default factory state

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user, 'web')->get($url);

    $response->assertRedirect('/');
    Event::assertNotDispatched(Verified::class);
});

test('verifying writes a user.email_verified audit log entry naming the user as actor', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user, 'web')->get($url);

    $log = AuditLog::query()->where('action', 'user.email_verified')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_user_id)->toBe($user->id)
        ->and($log->auditable_id)->toBe($user->id);
});

test('an unauthenticated visitor cannot verify without logging in first', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->get($url);

    $response->assertRedirect('/login');
});

test('the resend endpoint sends a new verification notification for an unverified user', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user, 'web')->post(route('verification.send'));

    $response->assertRedirect();
    $response->assertSessionHas('status', 'verification-link-sent');
});
