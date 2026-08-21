<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

test('renders nothing when there are no errors', function () {
    $html = Blade::render('<x-error-summary :errors="$errors" />', ['errors' => new MessageBag]);

    expect(trim($html))->toBe('');
});

test('renders the heading, role=alert, and one linked list item per error', function () {
    $errors = new MessageBag([
        'email' => ['Enter a valid email address'],
        'password' => ['Enter a password'],
    ]);

    $html = Blade::render('<x-error-summary :errors="$errors" />', ['errors' => $errors]);

    expect($html)->toContain('role="alert"')
        ->toContain('<h2')
        ->toContain('There is a problem')
        ->toContain('href="#email"')
        ->toContain('Enter a valid email address')
        ->toContain('href="#password"')
        ->toContain('Enter a password')
        ->toContain('<ol');
});

test('handles the real $errors variable Blade shares — a ViewErrorBag wrapping the MessageBag, not a bare MessageBag', function () {
    $viewErrorBag = new ViewErrorBag;
    $viewErrorBag->put('default', new MessageBag(['email' => ['Enter a valid email address']]));

    $html = Blade::render('<x-error-summary :errors="$errors" />', ['errors' => $viewErrorBag]);

    expect($html)->toContain('role="alert"')
        ->toContain('href="#email"')
        ->toContain('Enter a valid email address');
});

test('an empty ViewErrorBag renders nothing', function () {
    $html = Blade::render('<x-error-summary :errors="$errors" />', ['errors' => new ViewErrorBag]);

    expect(trim($html))->toBe('');
});

test('the heading is focusable so focus can move to it on a failed submit', function () {
    $errors = new MessageBag(['email' => ['Enter a valid email address']]);

    $html = Blade::render('<x-error-summary :errors="$errors" />', ['errors' => $errors]);

    expect($html)->toMatch('/<h2[^>]+tabindex="-1"/');
});
