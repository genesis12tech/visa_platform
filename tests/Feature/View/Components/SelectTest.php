<?php

use Illuminate\Support\Facades\Blade;

test('renders an option per entry in the options prop', function () {
    $html = Blade::render(
        '<x-select name="country" :options="$options" />',
        ['options' => ['IN' => 'India', 'US' => 'United States']]
    );

    expect($html)->toContain('<option value="IN">India</option>')
        ->toContain('<option value="US">United States</option>');
});

test('a placeholder renders as a disabled, unselectable first option', function () {
    $html = Blade::render(
        '<x-select name="country" placeholder="Choose a country" :options="$options" />',
        ['options' => ['IN' => 'India']]
    );

    expect($html)->toContain('Choose a country')
        ->toContain('disabled selected');
});

test('the value prop marks the matching option selected', function () {
    $html = Blade::render(
        '<x-select name="country" value="US" :options="$options" />',
        ['options' => ['IN' => 'India', 'US' => 'United States']]
    );

    expect($html)->toMatch('/<option value="US" selected>/');
});

test('an error state adds aria-invalid and describes the error id', function () {
    $html = Blade::render(
        '<x-select name="country" error="Choose a country" :options="$options" />',
        ['options' => ['IN' => 'India']]
    );

    expect($html)->toContain('aria-invalid="true"')
        ->toContain('aria-describedby="country-error"');
});

test('carries the literal input class the shipped forced-colors stylesheet targets', function () {
    $html = Blade::render('<x-select name="country" :options="$options" />', ['options' => ['IN' => 'India']]);

    expect($html)->toMatch('/class="[^"]*\binput\b/');
});
