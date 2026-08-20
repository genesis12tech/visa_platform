<?php

use App\Models\MfaRecoveryCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('a recovery code is generated with a bcrypt hash, never the plaintext', function () {
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);

    [$plain, $code] = MfaRecoveryCode::generateFor($user);

    expect($plain)->toBeString()
        ->and(strlen($plain))->toBeGreaterThanOrEqual(10)
        ->and($code->code_hash)->not->toBe($plain)
        ->and(Hash::check($plain, $code->code_hash))->toBeTrue()
        ->and($code->used_at)->toBeNull()
        ->and($code->getAttributes())->not->toHaveKey('updated_at');
});

test('ten recovery codes can be generated for a user in one batch', function () {
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);

    $codes = MfaRecoveryCode::generateBatchFor($user, 10);

    expect($codes)->toHaveCount(10)
        ->and(MfaRecoveryCode::query()->where('user_id', $user->id)->count())->toBe(10);

    $plaintexts = array_column($codes, 0);
    expect(collect($plaintexts)->unique())->toHaveCount(10);
});

test('deleting the user cascades to their recovery codes', function () {
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
    MfaRecoveryCode::generateBatchFor($user, 10);

    $user->forceDelete();

    expect(MfaRecoveryCode::query()->count())->toBe(0);
});

test('a code belongs to its user', function () {
    $user = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
    [, $code] = MfaRecoveryCode::generateFor($user);

    expect($code->user->is($user))->toBeTrue();
});
