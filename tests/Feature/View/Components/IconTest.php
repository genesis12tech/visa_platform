<?php

use Illuminate\Support\Facades\Blade;

test('renders the outline variant by default', function () {
    $html = Blade::render('<x-icon name="check-circle" />');

    expect($html)->toContain('<svg');
});

test('renders the solid variant when the solid prop is present', function () {
    $outline = Blade::render('<x-icon name="check-circle" />');
    $solid = Blade::render('<x-icon name="check-circle" solid />');

    expect($solid)->not->toBe($outline);
});

test('forwards class and aria attributes to the underlying svg', function () {
    $html = Blade::render('<x-icon name="check-circle" class="w-5 h-5" aria-hidden="true" />');

    expect($html)->toContain('aria-hidden="true"')
        ->and($html)->toContain('w-5 h-5');
});

test('an unknown icon name fails loudly rather than rendering nothing', function () {
    Blade::render('<x-icon name="not-a-real-icon" />');
})->throws(Exception::class);
