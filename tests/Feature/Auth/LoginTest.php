<?php

use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Backend_schema.md §11.3: one generic message for every credential failure
 * (unknown email, wrong password, suspended, unverified — none distinguishable
 * from outside), login_attempts recorded before evaluating (even unknown
 * emails, with a null user_id), and rate limiting independently on email and
 * IP.
 */
function makeActiveUser(array $overrides = []): User
{
    return User::factory()->create(array_merge(['password' => Hash::make('correct-password')], $overrides));
}

beforeEach(function () {
    RateLimiter::clear('login-email:priya@example.com');
    RateLimiter::clear('login-ip:127.0.0.1');
});

test('correct credentials for an active, verified user log them in on the web guard', function () {
    $user = makeActiveUser(['email' => 'priya@example.com']);

    $response = $this->post('/login', ['email' => 'priya@example.com', 'password' => 'correct-password']);

    $this->assertAuthenticatedAs($user, 'web');
    $response->assertRedirect('/');

    $attempt = LoginAttempt::query()->latest('id')->first();
    expect($attempt->successful)->toBeTrue()
        ->and($attempt->user_id)->toBe($user->id)
        ->and($user->fresh()->last_login_at)->not->toBeNull();
});

test('a successful login writes an auth.login audit log entry, a failed attempt does not', function () {
    $user = makeActiveUser(['email' => 'priya@example.com']);

    $this->post('/login', ['email' => 'priya@example.com', 'password' => 'wrong-password']);
    expect(AuditLog::query()->where('action', 'auth.login')->count())->toBe(0);

    $this->post('/login', ['email' => 'priya@example.com', 'password' => 'correct-password']);

    $log = AuditLog::query()->where('action', 'auth.login')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->actor_user_id)->toBe($user->id);
});

test('an unknown email and a wrong password produce the identical generic failure message', function () {
    makeActiveUser(['email' => 'priya@example.com']);

    $unknownResponse = $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever']);
    $wrongPasswordResponse = $this->post('/login', ['email' => 'priya@example.com', 'password' => 'wrong-password']);

    $unknownMessage = collect($unknownResponse->getSession()->get('errors')->get('email'))->first();
    $wrongMessage = collect($wrongPasswordResponse->getSession()->get('errors')->get('email'))->first();

    expect($unknownMessage)->toBe($wrongMessage);
});

test('an unknown email is recorded in login_attempts with a null user_id', function () {
    $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever']);

    $attempt = LoginAttempt::query()->latest('id')->first();
    expect($attempt->user_id)->toBeNull()
        ->and($attempt->email)->toBe('nobody@example.com')
        ->and($attempt->successful)->toBeFalse()
        ->and($attempt->failure_reason)->toBe('bad_credentials');
});

test('a wrong password for a known email is recorded with that user_id and bad_credentials', function () {
    $user = makeActiveUser(['email' => 'priya@example.com']);

    $this->post('/login', ['email' => 'priya@example.com', 'password' => 'wrong-password']);

    $attempt = LoginAttempt::query()->latest('id')->first();
    expect($attempt->user_id)->toBe($user->id)
        ->and($attempt->failure_reason)->toBe('bad_credentials');

    $this->assertGuest('web');
});

test('a suspended user cannot log in even with the correct password', function () {
    $user = makeActiveUser([
        'email' => 'priya@example.com',
        'status' => 'suspended',
        'suspended_at' => now(),
        'suspension_reason' => 'Fraudulent application detected.',
    ]);

    $this->post('/login', ['email' => 'priya@example.com', 'password' => 'correct-password']);

    $this->assertGuest('web');
    $attempt = LoginAttempt::query()->latest('id')->first();
    expect($attempt->failure_reason)->toBe('suspended');
});

test('an unverified user cannot log in even with the correct password', function () {
    makeActiveUser(['email' => 'priya@example.com', 'email_verified_at' => null]);

    $this->post('/login', ['email' => 'priya@example.com', 'password' => 'correct-password']);

    $this->assertGuest('web');
    $attempt = LoginAttempt::query()->latest('id')->first();
    expect($attempt->failure_reason)->toBe('unverified');
});

test('the session id regenerates on a successful login', function () {
    $user = makeActiveUser(['email' => 'priya@example.com']);

    $this->get('/login'); // establish an initial session
    $before = session()->getId();

    $this->post('/login', ['email' => 'priya@example.com', 'password' => 'correct-password']);

    expect(session()->getId())->not->toBe($before);
});

test('five failed attempts for the same email throttle a sixth, regardless of password correctness', function () {
    $user = makeActiveUser(['email' => 'priya@example.com']);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['email' => 'priya@example.com', 'password' => 'wrong-password']);
    }

    $response = $this->post('/login', ['email' => 'priya@example.com', 'password' => 'correct-password']);

    $response->assertSessionHasErrors('email');
    $this->assertGuest('web');
});

test('the staff guard authenticates a staff user and never an applicant', function () {
    $staff = User::factory()->create(['email' => 'chen@example.com', 'password' => Hash::make('correct-password'), 'user_type' => 'staff']);
    $applicant = User::factory()->create(['email' => 'priya@example.com', 'password' => Hash::make('correct-password'), 'user_type' => 'applicant']);

    $this->post('http://'.config('app.staff_domain').'/login', ['email' => 'chen@example.com', 'password' => 'correct-password']);
    $this->assertAuthenticatedAs($staff, 'staff');

    Auth::guard('staff')->logout();

    $this->post('http://'.config('app.staff_domain').'/login', ['email' => 'priya@example.com', 'password' => 'correct-password']);
    $this->assertGuest('staff');
});

test('logging out ends the web guard session', function () {
    $user = makeActiveUser(['email' => 'priya@example.com']);
    $this->actingAs($user, 'web');

    $this->post('/logout');

    $this->assertGuest('web');
});
