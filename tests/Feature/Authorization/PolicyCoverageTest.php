<?php

use App\Models\Country;
use App\Models\Currency;
use App\Models\DocumentType;
use App\Models\Holiday;
use App\Models\RejectionReason;
use App\Models\VisaTypeDocumentRequirement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * One of the five permanently-green tests (Implementation_plan.md §16.2):
 * every sensitive model must have a registered authorisation policy
 * (PRD FR-ID-06). If this ever goes red, stop feature work and fix it
 * before doing anything else.
 *
 * Pure reference/config tables are the only exemption — Backend_schema.md
 * §12.3's policy map deliberately excludes them: no PII, no per-row
 * scoping, nothing beyond "admin writes, everyone reads" (which the
 * still-policed config models like VisaType/VisaFee/ServiceLocation
 * already cover explicitly).
 */
const POLICY_COVERAGE_EXEMPT_MODELS = [
    Currency::class,
    Country::class,
    DocumentType::class,
    RejectionReason::class,
    Holiday::class,
    VisaTypeDocumentRequirement::class,
];

test('every sensitive model discovered under app/Models has a registered policy', function () {
    $modelClasses = collect(glob(app_path('Models/*.php')))
        ->map(fn (string $file) => 'App\\Models\\'.basename($file, '.php'))
        ->filter(fn (string $class) => is_subclass_of($class, Model::class));

    expect($modelClasses)->not->toBeEmpty();

    $sensitiveModels = $modelClasses->reject(
        fn (string $class) => in_array($class, POLICY_COVERAGE_EXEMPT_MODELS, true)
    );

    // Sanity check: this must not pass by accident because every model got exempted.
    expect($sensitiveModels)->not->toBeEmpty();

    foreach ($sensitiveModels as $class) {
        expect(Gate::getPolicyFor($class))->not->toBeNull(
            "Expected {$class} to have a registered authorisation policy (App\\Policies\\".class_basename($class).'Policy).'
        );
    }
});

test('the exemption list only contains models that still exist as genuinely non-sensitive reference tables', function () {
    foreach (POLICY_COVERAGE_EXEMPT_MODELS as $class) {
        expect(class_exists($class))->toBeTrue("{$class} is listed as policy-exempt but no longer exists in app/Models — remove it from the exemption list.");
    }
});
