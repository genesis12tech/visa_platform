<?php

use Illuminate\Support\Facades\Blade;

test('renders with matching id and name, default type text', function () {
    $html = Blade::render('<x-input name="email" />');

    expect($html)->toContain('id="email"')
        ->toContain('name="email"')
        ->toContain('type="text"');
});

test('type can be overridden', function () {
    $html = Blade::render('<x-input name="email" type="email" />');

    expect($html)->toContain('type="email"');
});

test('an error state adds aria-invalid and describes the error id', function () {
    $html = Blade::render('<x-input name="email" error="Enter a valid email address" />');

    expect($html)->toContain('aria-invalid="true"')
        ->toContain('aria-describedby="email-error"')
        ->toContain('border-danger');
});

test('a hint without an error still wires aria-describedby to the hint id', function () {
    $html = Blade::render('<x-input name="email" hint="true" />');

    expect($html)->toContain('aria-describedby="email-hint"');
});

test('disabled adds the disabled attribute and disabled styling', function () {
    $html = Blade::render('<x-input name="email" :disabled="true" />');

    expect($html)->toContain('disabled')
        ->toContain('disabled:bg-disabled');
});

test('readonly adds the readonly attribute and its own state styling, distinct from disabled', function () {
    $html = Blade::render('<x-input name="email" :readonly="true" />');

    expect($html)->toContain('readonly')
        ->toContain('surface-sunken')
        ->not->toMatch('/(?<![-:])\bdisabled\b(?!:)/');
});

test('never smaller than the 16px base font size, to avoid iOS zoom-on-focus', function () {
    $html = Blade::render('<x-input name="email" />');

    expect($html)->toContain('text-base')
        ->not->toContain('text-sm')
        ->not->toContain('text-xs');
});

test('carries the literal input class the shipped forced-colors stylesheet targets', function () {
    $html = Blade::render('<x-input name="email" />');

    expect($html)->toMatch('/class="[^"]*\binput\b/');
});
