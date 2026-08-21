<?php

use Illuminate\Support\Facades\Blade;

test('renders a real label associated to the checkbox via for/id, never a placeholder', function () {
    $html = Blade::render('<x-checkbox name="terms" label="I agree to the terms" />');

    expect($html)->toContain('type="checkbox"')
        ->toMatch('/id="([a-z0-9_-]+)"/')
        ->toContain('I agree to the terms')
        ->toContain('<label');
});

test('checked marks the box checked', function () {
    $html = Blade::render('<x-checkbox name="terms" label="I agree" :checked="true" />');

    expect($html)->toContain('checked');
});

test('disabled adds the disabled attribute', function () {
    $html = Blade::render('<x-checkbox name="terms" label="I agree" :disabled="true" />');

    expect($html)->toContain('disabled');
});

test('uses the forms plugin class strategy marker', function () {
    $html = Blade::render('<x-checkbox name="terms" label="I agree" />');

    expect($html)->toMatch('/class="[^"]*\bform-checkbox\b/');
});
