<?php

use App\Models\User;
use App\Models\UserMfaMethod;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('a TOTP secret is stored encrypted — a raw SQL read never returns plaintext', function () {
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);

    $method = UserMfaMethod::query()->create([
        'user_id' => $user->id,
        'type' => 'totp',
        'secret_encrypted' => 'JBSWY3DPEHPK3PXP',
    ]);

    $raw = DB::table('user_mfa_methods')->where('id', $method->id)->value('secret_encrypted');

    expect($raw)->not->toContain('JBSWY3DPEHPK3PXP')
        ->and($raw)->not->toBeNull();

    $fresh = UserMfaMethod::query()->find($method->id);
    expect($fresh->secret_encrypted)->toBe('JBSWY3DPEHPK3PXP')
        ->and($fresh->confirmed_at)->toBeNull();
});

test('type must be totp', function () {
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);

    UserMfaMethod::query()->create([
        'user_id' => $user->id,
        'type' => 'sms',
        'secret_encrypted' => 'JBSWY3DPEHPK3PXP',
    ]);
})->throws(QueryException::class)->skip(fn () => ! databaseEnforcesCheckConstraints(), 'CHECK constraints are not enforced on this engine');

test('a user can have only one totp method', function () {
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);

    UserMfaMethod::query()->create(['user_id' => $user->id, 'type' => 'totp', 'secret_encrypted' => 'AAA']);
    UserMfaMethod::query()->create(['user_id' => $user->id, 'type' => 'totp', 'secret_encrypted' => 'BBB']);
})->throws(QueryException::class);

test('deleting the user cascades to their mfa method', function () {
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
    UserMfaMethod::query()->create(['user_id' => $user->id, 'type' => 'totp', 'secret_encrypted' => 'AAA']);

    $user->forceDelete();

    expect(UserMfaMethod::query()->count())->toBe(0);
});

test('a method belongs to its user', function () {
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $method = UserMfaMethod::query()->create(['user_id' => $user->id, 'type' => 'totp', 'secret_encrypted' => 'AAA']);

    expect($method->user->is($user))->toBeTrue();
});
