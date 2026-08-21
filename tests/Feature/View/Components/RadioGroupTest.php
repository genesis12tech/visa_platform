<?php

use Illuminate\Support\Facades\Blade;

test('renders a fieldset with a legend and one radio per option', function () {
    $html = Blade::render(
        '<x-radio-group name="sex" legend="Sex" :options="$options" />',
        ['options' => ['male' => 'Male', 'female' => 'Female']]
    );

    expect($html)->toContain('<fieldset')
        ->toContain('<legend')
        ->toContain('Sex')
        ->toContain('Male')
        ->toContain('Female')
        ->and(substr_count($html, 'type="radio"'))->toBe(2);
});

test('the value prop marks only the matching radio checked', function () {
    $html = Blade::render(
        '<x-radio-group name="sex" legend="Sex" value="female" :options="$options" />',
        ['options' => ['male' => 'Male', 'female' => 'Female']]
    );

    expect($html)->toMatch('/value="female"[^>]*checked/')
        ->not->toMatch('/value="male"[^>]*checked/');
});

test('every radio shares the same name so only one can be selected', function () {
    $html = Blade::render(
        '<x-radio-group name="sex" legend="Sex" :options="$options" />',
        ['options' => ['male' => 'Male', 'female' => 'Female']]
    );

    expect(substr_count($html, 'name="sex"'))->toBe(2);
});
