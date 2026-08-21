<?php

use Illuminate\Support\Facades\Blade;

test('renders icon, headline, and body in order', function () {
    $html = Blade::render('<x-empty-state icon="document-duplicate" headline="You haven\'t started any applications" body="When you start one, it\'ll appear here." />');

    $iconPos = strpos($html, '<svg');
    $headlinePos = strpos($html, 'You haven&#039;t started any applications');
    $bodyPos = strpos($html, 'it&#039;ll appear here');

    expect($iconPos)->not->toBeFalse()
        ->and($headlinePos)->not->toBeFalse()
        ->and($bodyPos)->not->toBeFalse()
        ->and($iconPos)->toBeLessThan($headlinePos)
        ->and($headlinePos)->toBeLessThan($bodyPos);
});

test('the icon is decorative, aria-hidden', function () {
    $html = Blade::render('<x-empty-state icon="bell" headline="No notifications yet" />');

    expect($html)->toMatch('/<svg[^>]+aria-hidden="true"/');
});

test('body is optional', function () {
    $html = Blade::render('<x-empty-state icon="bell" headline="No notifications yet" />');

    expect($html)->toContain('No notifications yet');
});

test('an action slot renders when provided', function () {
    $html = Blade::render(
        '<x-empty-state icon="document-duplicate" headline="Get started"><x-slot:action><x-button>Start an application</x-button></x-slot:action></x-empty-state>'
    );

    expect($html)->toContain('Start an application');
});

test('carries the literal empty-state class', function () {
    $html = Blade::render('<x-empty-state icon="bell" headline="No notifications yet" />');

    expect($html)->toMatch('/class="[^"]*\bempty-state\b/');
});
