<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

test('a new email registers a pending applicant, logs them in, and dispatches Registered', function () {
    Event::fake([Registered::class]);

    $response = $this->post('/register', [
        'name' => 'Priya Sharma',
        'email' => 'priya@example.com',
        'password' => 'a-secure-password',
        'password_confirmation' => 'a-secure-password',
    ]);

    $user = User::query()->where('email', 'priya@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->status)->toBe('pending')
        ->and($user->user_type)->toBe('applicant')
        ->and($user->email_verified_at)->toBeNull()
        ->and(Hash::check('a-secure-password', $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user, 'web');
    Event::assertDispatched(Registered::class);
    $response->assertRedirect(route('verification.notice'));
});

test('registering with an email that already exists resends verification but never reveals the account exists', function () {
    Event::fake([Registered::class]);
    $existing = User::factory()->unverified()->create(['email' => 'existing@example.com']);

    $response = $this->post('/register', [
        'name' => 'Someone Else',
        'email' => 'existing@example.com',
        'password' => 'a-secure-password',
        'password_confirmation' => 'a-secure-password',
    ]);

    // Response is identical in shape to the new-registration case (PRD PUB-05)
    $response->assertRedirect(route('verification.notice'));
    Event::assertNotDispatched(Registered::class);
    expect(User::query()->where('email', 'existing@example.com')->count())->toBe(1);

    $fresh = $existing->fresh();
    expect($fresh->name)->toBe($existing->name); // the original account's name is untouched, not overwritten
});

test('registering with an email belonging to an already-verified user does not disturb that account', function () {
    $existing = User::factory()->create(['email' => 'verified@example.com']);

    $response = $this->post('/register', [
        'name' => 'Someone Else',
        'email' => 'verified@example.com',
        'password' => 'a-secure-password',
        'password_confirmation' => 'a-secure-password',
    ]);

    $response->assertRedirect(route('verification.notice'));
    expect(User::query()->where('email', 'verified@example.com')->count())->toBe(1)
        ->and($existing->fresh()->email_verified_at)->not->toBeNull();
});

test('name, email, and password are required', function () {
    $response = $this->post('/register', []);

    $response->assertSessionHasErrors(['name', 'email', 'password']);
});

test('password confirmation must match', function () {
    $response = $this->post('/register', [
        'name' => 'Priya Sharma',
        'email' => 'priya@example.com',
        'password' => 'a-secure-password',
        'password_confirmation' => 'does-not-match',
    ]);

    $response->assertSessionHasErrors('password');
});

test('registering a new user writes a user.registered audit log entry naming that user as actor', function () {
    $this->post('/register', [
        'name' => 'Priya Sharma',
        'email' => 'priya@example.com',
        'password' => 'a-secure-password',
        'password_confirmation' => 'a-secure-password',
    ]);

    $user = User::query()->where('email', 'priya@example.com')->first();
    $log = AuditLog::query()->where('action', 'user.registered')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_user_id)->toBe($user->id)
        ->and($log->auditable_type)->toBe(User::class)
        ->and($log->auditable_id)->toBe($user->id);
});

test('password must be at least 8 characters', function () {
    $response = $this->post('/register', [
        'name' => 'Priya Sharma',
        'email' => 'priya@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertSessionHasErrors('password');
});
