<?php

use Illuminate\Support\Facades\Blade;

test('renders the slot on a raised surface with a border, radius, and shadow', function () {
    $html = Blade::render('<x-card>Body content</x-card>');

    expect($html)->toContain('Body content')
        ->toContain('surface-raised')
        ->toContain('rounded-lg')
        ->toContain('shadow-1');
});

test('an interactive card wraps the whole card in a single anchor', function () {
    $html = Blade::render('<x-card :interactive="true" href="/applications/1">View</x-card>');

    expect($html)->toContain('<a ')
        ->toContain('href="/applications/1"');
});

test('a non-interactive card never renders an anchor wrapper', function () {
    $html = Blade::render('<x-card>Body</x-card>');

    expect($html)->not->toContain('<a ');
});

test('carries the literal card class the shipped forced-colors and print stylesheets target', function () {
    $html = Blade::render('<x-card>Body</x-card>');

    expect($html)->toMatch('/class="[^"]*\bcard\b/');
});
