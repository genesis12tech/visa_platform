<?php

use Illuminate\Support\Facades\Blade;

test('renders three separate numeric-mode inputs for day, month, and year', function () {
    $html = Blade::render('<x-date-input name="date_of_birth" label="Date of birth" />');

    expect(substr_count($html, 'inputmode="numeric"'))->toBe(3)
        ->and($html)->toContain('name="date_of_birth[day]"')
        ->toContain('name="date_of_birth[month]"')
        ->toContain('name="date_of_birth[year]"');
});

test('the format hint is always visible, never relying on locale inference', function () {
    $html = Blade::render('<x-date-input name="date_of_birth" label="Date of birth" />');

    expect($html)->toContain('14 03 1991');
});

test('pre-fills day, month, and year when given', function () {
    $html = Blade::render('<x-date-input name="date_of_birth" label="Date of birth" day="14" month="3" year="1991" />');

    expect($html)->toContain('value="14"')
        ->toContain('value="3"')
        ->toContain('value="1991"');
});

test('an error renders below the fields with an icon', function () {
    $html = Blade::render('<x-date-input name="date_of_birth" label="Date of birth" error="Enter a real date" />');

    expect($html)->toContain('Enter a real date')
        ->toContain('<svg')
        ->toContain('id="date_of_birth-error"');
});

test('never a bare free-text field', function () {
    $html = Blade::render('<x-date-input name="date_of_birth" label="Date of birth" />');

    expect($html)->not->toContain('type="date"')
        ->not->toMatch('/<input[^>]+type="text"(?![^>]*inputmode)/');
});
