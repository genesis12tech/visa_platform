<?php

use Illuminate\Support\Facades\Blade;

test('carries aria-busy and a visually hidden loading label, never a bare spinner', function () {
    $html = Blade::render('<x-skeleton class="w-full h-6" />');

    expect($html)->toContain('aria-busy="true"')
        ->toContain('sr-only')
        ->toContain('Loading');
});

test('uses motion-safe animation, honouring prefers-reduced-motion', function () {
    $html = Blade::render('<x-skeleton />');

    expect($html)->toContain('motion-safe:animate-pulse');
});

test('carries the literal skeleton class the shipped print stylesheet targets', function () {
    $html = Blade::render('<x-skeleton />');

    expect($html)->toMatch('/class="[^"]*\bskeleton\b/');
});

test('forwarded width/height classes size the placeholder', function () {
    $html = Blade::render('<x-skeleton class="w-32 h-4" />');

    expect($html)->toContain('w-32')
        ->toContain('h-4');
});
