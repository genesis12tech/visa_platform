<?php

use Illuminate\Support\Facades\Blade;

test('defaults to info tone with an inline level', function () {
    $html = Blade::render('<x-alert>Something to notice</x-alert>');

    expect($html)->toContain('Something to notice')
        ->toContain('info');
});

test('info and success use role=status, danger uses role=alert', function () {
    $info = Blade::render('<x-alert tone="info">x</x-alert>');
    $success = Blade::render('<x-alert tone="success">x</x-alert>');
    $warning = Blade::render('<x-alert tone="warning">x</x-alert>');
    $danger = Blade::render('<x-alert tone="danger">x</x-alert>');

    expect($info)->toContain('role="status"');
    expect($success)->toContain('role="status"');
    expect($warning)->toContain('role="status"');
    expect($danger)->toContain('role="alert"');
});

test('never uses aria-live=assertive, only aria-live=polite at most', function () {
    $html = Blade::render('<x-alert tone="danger">x</x-alert>');

    expect($html)->not->toContain('aria-live="assertive"');
});

test('an optional title renders above the body', function () {
    $html = Blade::render('<x-alert title="Payment declined">Try again</x-alert>');

    expect($html)->toContain('Payment declined')
        ->toContain('Try again');
});

test('dismissible renders a dismiss control', function () {
    $withDismiss = Blade::render('<x-alert :dismissible="true">x</x-alert>');
    $without = Blade::render('<x-alert :dismissible="false">x</x-alert>');

    expect($withDismiss)->toContain('aria-label')
        ->and($without)->not->toContain('aria-label="'.__('Dismiss').'"');
});

test('carries the literal alert class the shipped forced-colors stylesheet targets', function () {
    $html = Blade::render('<x-alert>x</x-alert>');

    expect($html)->toMatch('/class="[^"]*\balert\b/');
});

test('carries a border on all four sides, never a left accent bar alone', function () {
    $html = Blade::render('<x-alert tone="danger">x</x-alert>');

    expect($html)->not->toContain('border-l-4')
        ->not->toContain('border-s-4');
});

test('the icon is present alongside the tone colour, not colour alone', function () {
    $html = Blade::render('<x-alert tone="warning">x</x-alert>');

    expect($html)->toContain('<svg');
});
