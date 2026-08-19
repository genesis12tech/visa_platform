<?php

use App\Models\DocumentType;
use Illuminate\Database\QueryException;

test('a document type stores allowed mime types and extensions as arrays', function () {
    $docType = DocumentType::query()->create([
        'code' => 'passport_bio_page',
        'name' => 'Passport bio page',
        'allowed_mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
    ]);

    $fresh = DocumentType::query()->find($docType->id);

    expect($fresh->allowed_mime_types)->toBe(['application/pdf', 'image/jpeg', 'image/png'])
        ->and($fresh->allowed_extensions)->toBe(['pdf', 'jpg', 'jpeg', 'png'])
        ->and($fresh->max_size_bytes)->toBe(10485760)
        ->and($fresh->ulid)->toHaveLength(26);
});

test('code must be unique', function () {
    DocumentType::query()->create(['code' => 'passport', 'name' => 'Passport', 'allowed_mime_types' => ['application/pdf'], 'allowed_extensions' => ['pdf']]);
    DocumentType::query()->create(['code' => 'passport', 'name' => 'Duplicate', 'allowed_mime_types' => ['application/pdf'], 'allowed_extensions' => ['pdf']]);
})->throws(QueryException::class);

test('max_size_bytes must be at least 1024', function () {
    DocumentType::query()->create([
        'code' => 'tiny',
        'name' => 'Tiny',
        'allowed_mime_types' => ['application/pdf'],
        'allowed_extensions' => ['pdf'],
        'max_size_bytes' => 100,
    ]);
})->throws(QueryException::class)->skip(fn () => ! databaseEnforcesCheckConstraints(), 'CHECK constraints are not enforced on this engine');

test('max_size_bytes cannot exceed 50 MB', function () {
    DocumentType::query()->create([
        'code' => 'huge',
        'name' => 'Huge',
        'allowed_mime_types' => ['application/pdf'],
        'allowed_extensions' => ['pdf'],
        'max_size_bytes' => 100_000_000,
    ]);
})->throws(QueryException::class)->skip(fn () => ! databaseEnforcesCheckConstraints(), 'CHECK constraints are not enforced on this engine');
