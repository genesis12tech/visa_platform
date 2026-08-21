{{-- resources/views/components/modal.blade.php — Content_guidelines.md §5.12.
     Blade shell + Alpine. Opened from anywhere via
     `x-on:click="$dispatch('open-modal', 'name')"`, closed via a dispatched
     'close-modal' event or (when dismissible) Escape/backdrop click. --}}
@props([
    'name',
    'title',
    'size' => 'md',     // sm|md|lg
    'dismissible' => true,
])

@php
    $titleId = 'modal-title-'.Str::slug($name);
    $maxWidth = ['sm' => 'max-w-modal-sm', 'md' => 'max-w-modal-md', 'lg' => 'max-w-modal-lg'][$size];
@endphp

<div
    x-data="{ show: false }"
    x-show="show"
    x-on:open-modal.window="show = ($event.detail === '{{ $name }}')"
    x-on:close-modal.window="if (! $event.detail || $event.detail === '{{ $name }}') show = false"
    @if($dismissible) x-on:keydown.escape.window="show = false" @endif
    x-cloak
    class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center bg-surface-overlay p-4"
    role="presentation"
>
    <div
        @if($dismissible) x-on:click="show = false" @endif
        class="fixed inset-0"
        aria-hidden="true"
    ></div>

    <div
        x-trap.inert.noscroll="show"
        x-on:click.stop
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $titleId }}"
        {{ $attributes->class([
            'modal relative bg-surface-raised rounded-lg shadow-modal w-full flex flex-col max-h-modal',
            $maxWidth,
        ]) }}
    >
        <div class="flex items-start justify-between gap-4 p-6 border-b border-border">
            <h2
                id="{{ $titleId }}"
                tabindex="-1"
                x-init="$watch('show', value => value && $nextTick(() => $el.focus()))"
                class="text-xl font-semibold text-ink"
            >{{ $title }}</h2>

            @if($dismissible)
                <button
                    type="button"
                    x-on:click="show = false"
                    aria-label="{{ __('Close') }}"
                    class="shrink-0 text-ink-muted hover:text-ink"
                >
                    <x-icon name="x-mark" class="w-6 h-6" aria-hidden="true" />
                </button>
            @endif
        </div>

        <div class="p-6 overflow-y-auto">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="p-6 border-t border-border flex justify-end gap-3">{{ $footer }}</div>
        @endisset
    </div>
</div>
