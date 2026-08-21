<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

test('logging without an authenticated user or explicit actor records a system entry', function () {
    $log = app(AuditLogger::class)->log('tracking.lookup');

    expect($log->actor_user_id)->toBeNull()
        ->and($log->actor_type)->toBe('system');
});

test('logging while authenticated on a guard resolves that guard\'s user as the actor', function () {
    $staff = User::query()->create(['name' => 'Chen', 'email' => 'chen@example.com', 'password' => 'x', 'user_type' => 'staff']);
    $this->actingAs($staff, 'staff');

    $log = app(AuditLogger::class)->log('auth.login');

    expect($log->actor_user_id)->toBe($staff->id)
        ->and($log->actor_type)->toBe('user');
});

test('an explicit actor option overrides guard resolution', function () {
    $user = User::query()->create(['name' => 'Priya', 'email' => 'priya@example.com', 'password' => 'x']);

    $log = app(AuditLogger::class)->log('user.registered', ['actor' => $user]);

    expect($log->actor_user_id)->toBe($user->id);
});

test('an on_behalf_of user is recorded when given', function () {
    $agent = User::query()->create(['name' => 'Agent', 'email' => 'agent@example.com', 'password' => 'x', 'user_type' => 'agent']);
    $applicant = User::query()->create(['name' => 'Applicant', 'email' => 'applicant@example.com', 'password' => 'x']);

    $log = app(AuditLogger::class)->log('application.updated', ['actor' => $agent, 'on_behalf_of' => $applicant]);

    expect($log->on_behalf_of_user_id)->toBe($applicant->id);
});

test('an auditable model records its polymorphic type and id via getMorphClass, not the runtime class', function () {
    $user = User::query()->create(['name' => 'Priya', 'email' => 'priya@example.com', 'password' => 'x']);

    $log = app(AuditLogger::class)->log('user.email_verified', ['actor' => $user, 'auditable' => $user]);

    expect($log->auditable_type)->toBe(User::class)
        ->and($log->auditable_id)->toBe($user->id);
});

test('old_values, new_values, and metadata are stored and retrieved as arrays', function () {
    $log = app(AuditLogger::class)->log('visa_fee.updated', [
        'old_values' => ['amount_minor' => 1000],
        'new_values' => ['amount_minor' => 1200],
        'metadata' => ['reason' => 'annual increase'],
    ]);

    $fresh = AuditLog::query()->find($log->id);

    expect($fresh->old_values)->toBe(['amount_minor' => 1000])
        ->and($fresh->new_values)->toBe(['amount_minor' => 1200])
        ->and($fresh->metadata)->toBe(['reason' => 'annual increase']);
});

test('the current request IP and user agent are captured when a request is bound', function () {
    $this->app->instance('request', Request::create('/', 'GET', server: [
        'REMOTE_ADDR' => '203.0.113.7',
        'HTTP_USER_AGENT' => 'PestTest/1.0',
    ]));

    $log = app(AuditLogger::class)->log('tracking.lookup');

    expect($log->ip_address)->toBe('203.0.113.7')
        ->and($log->user_agent)->toBe('PestTest/1.0');
});
