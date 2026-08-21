<?php

use Illuminate\Support\Facades\Blade;

test('renders role=progressbar with valuenow/min/max and an aria-label', function () {
    $html = Blade::render('<x-progress-bar :value="4" :max="7" label="sections complete" />');

    expect($html)->toContain('role="progressbar"')
        ->toContain('aria-valuenow="4"')
        ->toContain('aria-valuemin="0"')
        ->toContain('aria-valuemax="7"')
        ->toContain('aria-label=');
});

test('always pairs the bar with a visible text equivalent', function () {
    $html = Blade::render('<x-progress-bar :value="4" :max="7" label="sections complete" />');

    expect($html)->toContain('4 of 7 sections complete');
});

test('the bar is hidden in print, the text equivalent is retained', function () {
    $html = Blade::render('<x-progress-bar :value="4" :max="7" label="sections complete" />');

    expect($html)->toMatch('/role="progressbar"[^>]*data-print="hide"/');
});
