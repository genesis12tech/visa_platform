<?php

namespace App\Livewire\Shared;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Content_guidelines.md §5.13. Confirms completed, reversible actions only —
 * never carries information that exists nowhere else, since it's gone after
 * 4 seconds. Dispatch with: $this->dispatch('toast', message: '...');
 */
class Toast extends Component
{
    /** @var array<int, array{id: string, message: string}> */
    public array $toasts = [];

    private const MAX_STACKED = 3;

    #[On('toast')]
    public function push(string $message): void
    {
        $this->toasts[] = [
            'id' => (string) Str::uuid(),
            'message' => $message,
        ];

        if (count($this->toasts) > self::MAX_STACKED) {
            array_shift($this->toasts);
        }
    }

    public function dismiss(string $id): void
    {
        $this->toasts = array_values(array_filter(
            $this->toasts,
            fn (array $toast): bool => $toast['id'] !== $id
        ));
    }

    public function render(): View
    {
        return view('livewire.shared.toast');
    }
}
