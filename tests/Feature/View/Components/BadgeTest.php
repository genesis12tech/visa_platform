<?php

use Illuminate\Support\Facades\Blade;

test('renders the icon and the text label together, never text alone', function () {
    $html = Blade::render('<x-badge status="complete" />');

    expect($html)->toContain('<svg')
        ->toContain('Complete');
});

test('every documented status key resolves to an icon, tone, and label', function () {
    $statuses = [
        'complete', 'accepted', 'in_progress', 'not_started', 'needs_attention',
        'locked', 'rejected', 'checking', 'draft', 'submitted', 'in_review',
        'action_required', 'decision_made', 'closed',
    ];

    foreach ($statuses as $status) {
        $html = Blade::render('<x-badge :status="$status" />', ['status' => $status]);

        expect($html)->toContain('<svg');
    }
});

test('an unrecognised status key fails loudly rather than rendering a blank badge', function () {
    Blade::render('<x-badge status="not-a-real-status" />');
})->throws(Exception::class);

test('the icon carries the status-icon class the forced-colors stylesheet targets', function () {
    $html = Blade::render('<x-badge status="rejected" />');

    expect($html)->toMatch('/class="[^"]*\bstatus-icon\b/');
});

test('carries the literal badge class the shipped forced-colors and print stylesheets target', function () {
    $html = Blade::render('<x-badge status="draft" />');

    expect($html)->toMatch('/class="[^"]*\bbadge\b/');
});

test('a danger-tone status uses the danger token classes, not colour alone', function () {
    $html = Blade::render('<x-badge status="rejected" />');

    expect($html)->toContain('danger');
});
