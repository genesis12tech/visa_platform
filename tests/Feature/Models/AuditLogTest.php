<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('an audit log entry can be created with actor, action, and IP address', function () {
    $actor = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);

    $log = AuditLog::query()->create([
        'actor_user_id' => $actor->id,
        'actor_type' => 'user',
        'action' => 'auth.login',
        'ip_address' => '203.0.113.42',
        'user_agent' => 'PestTest/1.0',
        'new_values' => ['guard' => 'staff'],
    ]);

    expect($log->actor_user_id)->toBe($actor->id)
        ->and($log->action)->toBe('auth.login')
        ->and($log->ip_address)->toBe('203.0.113.42')
        ->and($log->new_values)->toBe(['guard' => 'staff'])
        ->and($log->actor->id)->toBe($actor->id);
});

test('actor_user_id defaults to null with actor_type system for unattributed entries', function () {
    $log = AuditLog::query()->create(['action' => 'tracking.lookup']);

    expect($log->actor_user_id)->toBeNull()
        ->and($log->actor_type)->toBe('system');
});

test('on_behalf_of_user_id records the applicant an agent acted for', function () {
    $agent = User::query()->create(['name' => 'Agent', 'email' => 'agent@example.com', 'password' => 'x', 'user_type' => 'agent']);
    $applicant = User::query()->create(['name' => 'Applicant', 'email' => 'applicant@example.com', 'password' => 'x']);

    $log = AuditLog::query()->create([
        'actor_user_id' => $agent->id,
        'actor_type' => 'user',
        'on_behalf_of_user_id' => $applicant->id,
        'action' => 'application.updated',
    ]);

    expect($log->onBehalfOf->id)->toBe($applicant->id);
});

test('has no ulid — audit_logs is a system log, never addressed publicly', function () {
    $log = AuditLog::query()->create(['action' => 'user.registered']);

    expect($log->getAttributes())->not->toHaveKey('ulid')
        ->and($log->getAttributes())->not->toHaveKey('updated_at');
});

test('rows cannot be updated — enforced by trg_audit_logs_no_update', function () {
    $log = AuditLog::query()->create(['action' => 'user.registered']);

    DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'tampered']);
})->throws(QueryException::class);

test('rows cannot be deleted — enforced by trg_audit_logs_no_delete', function () {
    $log = AuditLog::query()->create(['action' => 'user.registered']);

    DB::table('audit_logs')->where('id', $log->id)->delete();
})->throws(QueryException::class);

test('an actor who has audit log entries cannot be hard-deleted', function () {
    $actor = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x']);
    AuditLog::query()->create(['actor_user_id' => $actor->id, 'actor_type' => 'user', 'action' => 'auth.login']);

    $actor->forceDelete();
})->throws(QueryException::class);
