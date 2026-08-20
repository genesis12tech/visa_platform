<?php

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Database\QueryException;

test('a login attempt records a known user by id', function () {
    $user = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);

    $attempt = LoginAttempt::query()->create([
        'email' => 'a@example.com',
        'user_id' => $user->id,
        'successful' => true,
        'ip_address' => '203.0.113.42',
        'user_agent' => 'PestTest/1.0',
        'guard' => 'web',
    ]);

    expect($attempt->user_id)->toBe($user->id)
        ->and($attempt->successful)->toBeTrue()
        ->and($attempt->ip_address)->toBe('203.0.113.42')
        ->and($attempt->getAttributes())->not->toHaveKey('ulid')
        ->and($attempt->getAttributes())->not->toHaveKey('updated_at');
});

test('a login attempt against an unknown email has a null user_id — this is what makes credential stuffing visible', function () {
    $attempt = LoginAttempt::query()->create([
        'email' => 'nobody@example.com',
        'user_id' => null,
        'successful' => false,
        'failure_reason' => 'bad_credentials',
        'guard' => 'web',
    ]);

    expect($attempt->user_id)->toBeNull()
        ->and($attempt->email)->toBe('nobody@example.com');
});

test('guard defaults to web when not specified', function () {
    $attempt = LoginAttempt::query()->create([
        'email' => 'a@example.com',
        'successful' => false,
        'failure_reason' => 'bad_credentials',
    ]);

    expect($attempt->guard)->toBe('web');
});

test('a successful attempt cannot carry a failure_reason', function () {
    LoginAttempt::query()->create([
        'email' => 'a@example.com',
        'successful' => true,
        'failure_reason' => 'bad_credentials',
    ]);
})->throws(QueryException::class)->skip(fn () => ! databaseEnforcesCheckConstraints(), 'CHECK constraints are not enforced on this engine');

test('a failed attempt must carry a valid failure_reason', function () {
    LoginAttempt::query()->create([
        'email' => 'a@example.com',
        'successful' => false,
        'failure_reason' => null,
    ]);
})->throws(QueryException::class)->skip(fn () => ! databaseEnforcesCheckConstraints(), 'CHECK constraints are not enforced on this engine');

test('failure_reason must be one of the recognised values', function () {
    LoginAttempt::query()->create([
        'email' => 'a@example.com',
        'successful' => false,
        'failure_reason' => 'not_a_real_reason',
    ]);
})->throws(QueryException::class)->skip(fn () => ! databaseEnforcesCheckConstraints(), 'CHECK constraints are not enforced on this engine');

test('deleting the referenced user sets user_id to null rather than deleting the attempt', function () {
    $user = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);
    $attempt = LoginAttempt::query()->create([
        'email' => 'a@example.com',
        'user_id' => $user->id,
        'successful' => true,
    ]);

    $user->forceDelete();

    expect($attempt->refresh()->user_id)->toBeNull()
        ->and(LoginAttempt::query()->count())->toBe(1);
});
