<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

test('a user is created with defaults: pending status, applicant type, ulid, and no MFA', function () {
    $user = User::query()->create([
        'name' => 'Priya Sharma',
        'email' => 'priya@example.com',
        'password' => 'a-plaintext-password',
    ]);

    expect($user->ulid)->toHaveLength(26)
        ->and($user->status)->toBe('pending')
        ->and($user->user_type)->toBe('applicant')
        ->and($user->locale)->toBe('en')
        ->and($user->mfa_enabled_at)->toBeNull()
        ->and($user->getRouteKeyName())->toBe('ulid');
});

test('the password is hashed, never stored in plain text', function () {
    $user = User::query()->create([
        'name' => 'Priya Sharma',
        'email' => 'priya@example.com',
        'password' => 'a-plaintext-password',
    ]);

    $raw = DB::table('users')->where('id', $user->id)->value('password');

    expect($raw)->not->toBe('a-plaintext-password')
        ->and(Hash::check('a-plaintext-password', $raw))->toBeTrue();
});

test('email must be unique', function () {
    User::query()->create(['name' => 'A', 'email' => 'dup@example.com', 'password' => 'x']);
    User::query()->create(['name' => 'B', 'email' => 'dup@example.com', 'password' => 'x']);
})->throws(QueryException::class);

test('status must be one of pending, active, or suspended', function () {
    User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x', 'status' => 'bogus']);
})->throws(QueryException::class);

test('user_type must be one of applicant, agent, or staff', function () {
    User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x', 'user_type' => 'bogus']);
})->throws(QueryException::class);

test('a suspended user requires both suspended_at and a suspension_reason', function () {
    User::query()->create([
        'name' => 'A', 'email' => 'a@example.com', 'password' => 'x',
        'status' => 'suspended',
    ]);
})->throws(QueryException::class);

test('a suspended user with both suspended_at and a reason is valid', function () {
    $user = User::query()->create([
        'name' => 'A', 'email' => 'a@example.com', 'password' => 'x',
        'status' => 'suspended',
        'suspended_at' => now(),
        'suspension_reason' => 'Fraudulent application detected.',
    ]);

    expect($user->status)->toBe('suspended');
});

test('a user records who suspended them via a self-referencing relation', function () {
    $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $suspended = User::query()->create([
        'name' => 'A', 'email' => 'a@example.com', 'password' => 'x',
        'status' => 'suspended',
        'suspended_at' => now(),
        'suspension_reason' => 'Fraudulent application detected.',
        'suspended_by_user_id' => $admin->id,
    ]);

    expect($suspended->suspendedBy->is($admin))->toBeTrue();
});

test('deleting a user is a soft delete', function () {
    $user = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);
    $id = $user->id;

    $user->delete();

    expect(User::query()->find($id))->toBeNull()
        ->and(User::withTrashed()->find($id))->not->toBeNull();
});
