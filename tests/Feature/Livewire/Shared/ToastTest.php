<?php

use App\Livewire\Shared\Toast;
use Livewire\Livewire;

test('dispatching a toast event adds a message to the queue', function () {
    Livewire::test(Toast::class)
        ->dispatch('toast', message: 'Draft saved.')
        ->assertSet('toasts.0.message', 'Draft saved.');
});

test('a maximum of three toasts are stacked, the oldest dismisses first', function () {
    $component = Livewire::test(Toast::class)
        ->dispatch('toast', message: 'First')
        ->dispatch('toast', message: 'Second')
        ->dispatch('toast', message: 'Third')
        ->dispatch('toast', message: 'Fourth');

    expect($component->get('toasts'))->toHaveCount(3);
    $messages = collect($component->get('toasts'))->pluck('message')->all();
    expect($messages)->toBe(['Second', 'Third', 'Fourth']);
});

test('dismiss removes a toast by id', function () {
    $component = Livewire::test(Toast::class)->dispatch('toast', message: 'Draft saved.');
    $id = $component->get('toasts.0.id');

    $component->call('dismiss', $id)
        ->assertSet('toasts', []);
});

test('the container is role=status with aria-live=polite', function () {
    Livewire::test(Toast::class)
        ->assertSeeHtml('role="status"')
        ->assertSeeHtml('aria-live="polite"');
});

test('each toast is dismissible with an accessible control', function () {
    Livewire::test(Toast::class)
        ->dispatch('toast', message: 'Draft saved.')
        ->assertSeeHtml('wire:click="dismiss(');
});
