{{-- resources/views/livewire/shared/toast.blade.php — Content_guidelines.md §5.13.
     Bottom on mobile (above the tab bar), top-right on desktop. --}}
<div
    class="toast-stack fixed z-50 flex flex-col gap-3 bottom-20 inset-x-4 md:bottom-auto md:inset-x-auto md:top-4 md:right-4"
    role="status"
    aria-live="polite"
>
    @foreach($toasts as $toast)
        <div
            wire:key="toast-{{ $toast['id'] }}"
            x-data="{
                timer: null,
                start() { this.timer = setTimeout(() => $wire.dismiss('{{ $toast['id'] }}'), 4000) },
                stop() { clearTimeout(this.timer) },
            }"
            x-init="start()"
            x-on:mouseenter="stop()"
            x-on:mouseleave="start()"
            x-on:focusin="stop()"
            x-on:focusout="start()"
            class="toast flex items-start gap-3 rounded-lg border p-4 bg-success-subtle border-success-border shadow-2"
        >
            <x-icon name="check-circle" solid class="w-5 h-5 status-icon shrink-0 text-success" aria-hidden="true" />

            <p class="flex-1 text-base text-ink">{{ $toast['message'] }}</p>

            <button
                type="button"
                wire:click="dismiss('{{ $toast['id'] }}')"
                aria-label="{{ __('Dismiss') }}"
                class="shrink-0 text-ink-muted hover:text-ink"
            >
                <x-icon name="x-mark" class="w-5 h-5" aria-hidden="true" />
            </button>
        </div>
    @endforeach
</div>
