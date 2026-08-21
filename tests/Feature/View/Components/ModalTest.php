<?php

use Illuminate\Support\Facades\Blade;

test('renders role=dialog, aria-modal, and aria-labelledby pointing to the title', function () {
    $html = Blade::render('<x-modal name="confirm-delete" title="Delete draft VISA-IND-2026-8K3F2Q?">Body</x-modal>');

    expect($html)->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toMatch('/aria-labelledby="([a-z0-9_-]+)"/');

    preg_match('/aria-labelledby="([a-z0-9_-]+)"/', $html, $m);
    expect($html)->toContain('id="'.$m[1].'"')
        ->toContain('Delete draft VISA-IND-2026-8K3F2Q?');
});

test('the title is an h2, focusable so focus can move to it on open', function () {
    $html = Blade::render('<x-modal name="confirm-delete" title="Delete draft?">Body</x-modal>');

    expect($html)->toMatch('/<h2[^>]+tabindex="-1"/');
});

test('each size maps to its documented max-width token', function () {
    $sm = Blade::render('<x-modal name="m" title="T" size="sm">Body</x-modal>');
    $md = Blade::render('<x-modal name="m" title="T" size="md">Body</x-modal>');
    $lg = Blade::render('<x-modal name="m" title="T" size="lg">Body</x-modal>');

    expect($sm)->toContain('max-w-modal-sm');
    expect($md)->toContain('max-w-modal-md');
    expect($lg)->toContain('max-w-modal-lg');
});

test('dismissible renders a close control and escape/backdrop handlers', function () {
    $dismissible = Blade::render('<x-modal name="m" title="T" :dismissible="true">Body</x-modal>');
    $notDismissible = Blade::render('<x-modal name="m" title="T" :dismissible="false">Body</x-modal>');

    expect($dismissible)->toContain('aria-label="'.__('Close').'"')
        ->toContain('keydown.escape');
    expect($notDismissible)->not->toContain('aria-label="'.__('Close').'"');
});

test('body scroll locks and focus traps while open via x-trap', function () {
    $html = Blade::render('<x-modal name="m" title="T">Body</x-modal>');

    expect($html)->toContain('x-trap')
        ->toContain('noscroll');
});

test('carries the literal modal-backdrop class the shipped print stylesheet hides', function () {
    $html = Blade::render('<x-modal name="m" title="T">Body</x-modal>');

    expect($html)->toMatch('/class="[^"]*\bmodal-backdrop\b/');
});

test('an optional footer slot renders for confirmation actions', function () {
    $html = Blade::render(
        '<x-modal name="m" title="T">Body<x-slot:footer><x-button variant="secondary">Cancel</x-button><x-button variant="danger">Delete draft</x-button></x-slot:footer></x-modal>'
    );

    expect($html)->toContain('Cancel')
        ->toContain('Delete draft')
        ->and(strpos($html, 'Cancel'))->toBeLessThan(strpos($html, 'Delete draft'));
});
