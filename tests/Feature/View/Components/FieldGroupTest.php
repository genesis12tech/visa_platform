<?php

use Illuminate\Support\Facades\Blade;

test('renders the label associated to the input id via for/id', function () {
    $html = Blade::render('<x-field-group label="Email address" name="email"><x-input name="email" /></x-field-group>');

    expect($html)->toContain('for="email"')
        ->toContain('id="email"')
        ->toContain('Email address');
});

test('a required field shows the asterisk and a visually hidden "required" word', function () {
    $html = Blade::render('<x-field-group label="Email" name="email" :required="true"><x-input name="email" /></x-field-group>');

    expect($html)->toContain('aria-hidden="true">*</span>')
        ->toContain('required');
});

test('an error renders below the input, before the hint, with an icon', function () {
    $html = Blade::render('<x-field-group label="Email" name="email" hint="We will never share this" error="Enter a valid email address"><x-input name="email" /></x-field-group>');

    $errorPos = strpos($html, 'Enter a valid email address');
    $hintPos = strpos($html, 'We will never share this');

    expect($errorPos)->not->toBeFalse()
        ->and($hintPos)->not->toBeFalse()
        ->and($errorPos)->toBeLessThan($hintPos)
        ->and($html)->toContain('id="email-error"')
        ->toContain('<svg');
});

test('no hint or error renders nothing extra', function () {
    $html = Blade::render('<x-field-group label="Email" name="email"><x-input name="email" /></x-field-group>');

    expect($html)->not->toContain('email-hint')
        ->not->toContain('email-error');
});

test('carries the literal field-group class the shipped print stylesheet targets', function () {
    $html = Blade::render('<x-field-group label="Email" name="email"><x-input name="email" /></x-field-group>');

    expect($html)->toMatch('/class="[^"]*\bfield-group\b/');
});
