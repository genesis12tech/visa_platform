<?php

use App\Models\Country;
use App\Models\DocumentType;
use App\Models\VisaType;
use App\Models\VisaTypeDocumentRequirement;
use Illuminate\Database\QueryException;

function makeVisaTypeAndDocType(): array
{
    $country = Country::query()->create(['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India']);
    $visaType = VisaType::query()->create([
        'country_id' => $country->id,
        'code' => 'TOURIST',
        'name' => 'Tourist',
        'processing_days_min' => 3,
        'processing_days_max' => 5,
    ]);
    $docType = DocumentType::query()->create([
        'code' => 'passport',
        'name' => 'Passport',
        'allowed_mime_types' => ['application/pdf'],
        'allowed_extensions' => ['pdf'],
    ]);

    return [$visaType, $docType];
}

test('a requirement links a visa type to a document type with an optional condition', function () {
    [$visaType, $docType] = makeVisaTypeAndDocType();

    $requirement = VisaTypeDocumentRequirement::query()->create([
        'visa_type_id' => $visaType->id,
        'document_type_id' => $docType->id,
        'is_required' => true,
        'condition_rules' => ['type' => 'minor', 'value' => true],
    ]);

    expect($requirement->is_required)->toBeTrue()
        ->and($requirement->condition_rules)->toBe(['type' => 'minor', 'value' => true])
        ->and($requirement->visaType->is($visaType))->toBeTrue()
        ->and($requirement->documentType->is($docType))->toBeTrue();
});

test('condition_rules is nullable for an unconditional requirement', function () {
    [$visaType, $docType] = makeVisaTypeAndDocType();

    $requirement = VisaTypeDocumentRequirement::query()->create([
        'visa_type_id' => $visaType->id,
        'document_type_id' => $docType->id,
    ]);

    expect($requirement->condition_rules)->toBeNull()
        ->and($requirement->is_required)->toBeTrue()
        ->and($requirement->sort_order)->toBe(0);
});

test('the same document type cannot be attached twice to the same visa type', function () {
    [$visaType, $docType] = makeVisaTypeAndDocType();

    VisaTypeDocumentRequirement::query()->create(['visa_type_id' => $visaType->id, 'document_type_id' => $docType->id]);
    VisaTypeDocumentRequirement::query()->create(['visa_type_id' => $visaType->id, 'document_type_id' => $docType->id]);
})->throws(QueryException::class);

test('deleting a visa type cascades to its document requirements', function () {
    [$visaType, $docType] = makeVisaTypeAndDocType();
    VisaTypeDocumentRequirement::query()->create(['visa_type_id' => $visaType->id, 'document_type_id' => $docType->id]);

    $visaType->delete();

    expect(VisaTypeDocumentRequirement::query()->count())->toBe(0);
});

test('a document type in use cannot be deleted', function () {
    [$visaType, $docType] = makeVisaTypeAndDocType();
    VisaTypeDocumentRequirement::query()->create(['visa_type_id' => $visaType->id, 'document_type_id' => $docType->id]);

    $docType->delete();
})->throws(QueryException::class);
