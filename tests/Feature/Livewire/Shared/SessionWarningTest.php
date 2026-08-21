<?php

use App\Livewire\Shared\SessionWarning;
use Livewire\Livewire;

test('is hidden until the session-expiring event is dispatched', function () {
    Livewire::test(SessionWarning::class)
        ->assertSet('show', false);
});

test('the session-expiring event opens the warning', function () {
    Livewire::test(SessionWarning::class)
        ->dispatch('session-expiring')
        ->assertSet('show', true);
});

test('staying signed in closes the modal and tells the client to reset its timer', function () {
    Livewire::test(SessionWarning::class)
        ->dispatch('session-expiring')
        ->call('stayeSignedIn')
        ->assertSet('show', false)
        ->assertDispatched('session-extended');
});

test('an autosave-completed event records when answers were saved', function () {
    Livewire::test(SessionWarning::class)
        ->dispatch('autosave-completed', savedAt: '14:32')
        ->assertSet('savedAt', '14:32')
        ->assertSet('autosaveFailed', false);
});

test('an autosave-failed event names the section and offers retry', function () {
    $component = Livewire::test(SessionWarning::class)
        ->dispatch('autosave-failed', section: 'Passport details');

    $component->assertSet('autosaveFailed', true)
        ->assertSet('failedSection', 'Passport details');
});

test('retrying dispatches a retry-autosave event', function () {
    Livewire::test(SessionWarning::class)
        ->call('retryAutosave')
        ->assertDispatched('retry-autosave');
});

test('the "Stay signed in" control is first in DOM order among the dialog actions', function () {
    $html = Livewire::test(SessionWarning::class)->dispatch('session-expiring')->html();

    $stayPos = strpos($html, 'stayeSignedIn');
    $signOutPos = strpos($html, __('Sign out now'));

    expect($stayPos)->not->toBeFalse()
        ->and($signOutPos)->not->toBeFalse()
        ->and($stayPos)->toBeLessThan($signOutPos);
});

test('renders as a dialog with focus trapped', function () {
    $html = Livewire::test(SessionWarning::class)->dispatch('session-expiring')->html();

    expect($html)->toContain('role="dialog"')
        ->toContain('x-trap');
});
