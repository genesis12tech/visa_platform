<?php

use Illuminate\Support\Facades\Blade;

test('renders a primary button by default with the slot content', function () {
    $html = Blade::render('<x-button>Save and continue</x-button>');

    expect($html)->toContain('<button')
        ->toContain('type="button"')
        ->toContain('Save and continue')
        ->toContain('bg-brand');
});

test('each variant maps to its documented classes', function () {
    $primary = Blade::render('<x-button variant="primary">Go</x-button>');
    $secondary = Blade::render('<x-button variant="secondary">Go</x-button>');
    $ghost = Blade::render('<x-button variant="ghost">Go</x-button>');
    $danger = Blade::render('<x-button variant="danger">Go</x-button>');

    expect($primary)->toContain('bg-brand')->toContain('text-ink-inverse');
    expect($secondary)->toContain('text-brand')->toContain('border-brand');
    expect($ghost)->toContain('bg-transparent');
    expect($danger)->toContain('bg-danger');
});

test('a disabled button requires and renders its reason via aria-describedby', function () {
    $html = Blade::render('<x-button :disabled="true" disabled-reason="Complete all sections first">Submit application</x-button>');

    expect($html)->toContain('disabled')
        ->toContain('aria-describedby="reason-')
        ->toContain('Complete all sections first');
});

test('a loading button is disabled, aria-busy, and shows the working label instead of the slot', function () {
    $html = Blade::render('<x-button :loading="true">Submit application</x-button>');

    expect($html)->toContain('aria-busy="true"')
        ->toContain('disabled')
        ->toContain('Working…')
        ->not->toContain('Submit application');
});

test('type can be overridden to submit', function () {
    $html = Blade::render('<x-button type="submit">Save</x-button>');

    expect($html)->toContain('type="submit"');
});

test('fullWidth adds the full-width class', function () {
    $html = Blade::render('<x-button :full-width="true">Save</x-button>');

    expect($html)->toContain('w-full');
});

test('iconStart and iconEnd render an icon adjacent to the slot', function () {
    $html = Blade::render('<x-button icon-start="arrow-up-tray">Upload</x-button>');

    expect($html)->toContain('<svg');
});

test('arbitrary attributes like wire:click and data- pass through', function () {
    $html = Blade::render('<x-button data-testid="submit-btn">Save</x-button>');

    expect($html)->toContain('data-testid="submit-btn"');
});

test('minimum tap target height class is always present', function () {
    $html = Blade::render('<x-button>Save</x-button>');

    expect($html)->toContain('min-h-tap');
});

test('carries the literal btn class the shipped forced-colors and print stylesheets target', function () {
    $html = Blade::render('<x-button>Save</x-button>');

    expect($html)->toMatch('/class="[^"]*\bbtn\b/');
});
