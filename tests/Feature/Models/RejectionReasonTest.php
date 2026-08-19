<?php

use App\Models\RejectionReason;
use Illuminate\Database\QueryException;

test('a rejection reason is created with the applicant-facing text separate from the internal label', function () {
    $reason = RejectionReason::query()->create([
        'scope' => 'document',
        'code' => 'illegible_scan',
        'label' => 'Illegible scan',
        'applicant_text' => "We couldn't read this document clearly. Please upload a sharper copy.",
    ]);

    expect($reason->getAttributes())->not->toHaveKey('ulid')
        ->and($reason->is_active)->toBeTrue()
        ->and($reason->sort_order)->toBe(0);
});

test('scope must be either document or decision', function () {
    RejectionReason::query()->create([
        'scope' => 'invalid_scope',
        'code' => 'x',
        'label' => 'X',
        'applicant_text' => 'X',
    ]);
})->throws(QueryException::class)->skip(fn () => ! databaseEnforcesCheckConstraints(), 'CHECK constraints are not enforced on this engine');

test('code must be unique within a scope but may repeat across scopes', function () {
    RejectionReason::query()->create(['scope' => 'document', 'code' => 'incomplete', 'label' => 'Incomplete', 'applicant_text' => 'Incomplete.']);
    RejectionReason::query()->create(['scope' => 'decision', 'code' => 'incomplete', 'label' => 'Incomplete', 'applicant_text' => 'Incomplete.']);

    expect(RejectionReason::query()->count())->toBe(2);
});

test('the same code cannot repeat within the same scope', function () {
    RejectionReason::query()->create(['scope' => 'document', 'code' => 'incomplete', 'label' => 'Incomplete', 'applicant_text' => 'Incomplete.']);
    RejectionReason::query()->create(['scope' => 'document', 'code' => 'incomplete', 'label' => 'Duplicate', 'applicant_text' => 'Incomplete.']);
})->throws(QueryException::class);
