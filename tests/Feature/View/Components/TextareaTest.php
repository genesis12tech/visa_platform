<?php

use Illuminate\Support\Facades\Blade;

test('renders with matching id and name', function () {
    $html = Blade::render('<x-textarea name="notes" />');

    expect($html)->toContain('<textarea')
        ->toContain('id="notes"')
        ->toContain('name="notes"');
});

test('an error state adds aria-invalid and describes the error id', function () {
    $html = Blade::render('<x-textarea name="notes" error="Enter your notes" />');

    expect($html)->toContain('aria-invalid="true"')
        ->toContain('aria-describedby="notes-error"');
});

test('carries the literal input class the shipped forced-colors stylesheet targets', function () {
    $html = Blade::render('<x-textarea name="notes" />');

    expect($html)->toMatch('/class="[^"]*\binput\b/');
});
