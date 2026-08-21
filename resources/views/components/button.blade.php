{{-- resources/views/components/button.blade.php — Content_guidelines.md §4.3, verbatim. --}}
@props([
    'variant' => 'primary',   // primary|secondary|ghost|danger
    'size' => 'md',        // sm|md|lg
    'type' => 'button',
    'disabled' => false,
    'loading' => false,
    'disabledReason' => null,      // REQUIRED when $disabled is true
    'fullWidth' => false,
    'iconStart' => null,
    'iconEnd' => null,
])

@php
    $reasonId = $disabledReason ? 'reason-'.Str::random(6) : null;

    $base = 'inline-flex items-center justify-center gap-2 font-body font-semibold
             rounded-md border transition-colors duration-fast
             min-h-tap focus-visible:outline focus-visible:outline-[3px]
             focus-visible:outline-offset-2 focus-visible:outline-focus';

    $variants = [
        'primary' => 'bg-brand text-ink-inverse border-brand
                        hover:bg-brand-hover active:bg-brand-active',
        'secondary' => 'bg-surface text-brand border-brand
                        hover:bg-brand-subtle',
        'ghost' => 'bg-transparent text-brand border-transparent
                        hover:bg-brand-subtle hover:border-brand-border',
        'danger' => 'bg-danger text-ink-inverse border-danger
                        hover:bg-danger-hover',
    ];

    $sizes = [
        'sm' => 'text-sm px-3 py-2',
        'md' => 'text-base px-4 py-3',
        'lg' => 'text-lg px-6 py-4',
    ];

    $disabledClasses = 'bg-disabled text-disabled-ink border-disabled-border
                        cursor-not-allowed hover:bg-disabled';
@endphp

<button
    type="{{ $type }}"
    @disabled($disabled || $loading)
    @if($reasonId) aria-describedby="{{ $reasonId }}" @endif
    @if($loading) aria-busy="true" @endif
    {{ $attributes->class([
        'btn',
        $base,
        $sizes[$size],
        $variants[$variant] => ! $disabled && ! $loading,
        $disabledClasses => $disabled || $loading,
        'w-full' => $fullWidth,
    ]) }}
>
    @if($loading)
        <x-icon name="arrow-path" class="w-5 h-5 motion-safe:animate-spin" aria-hidden="true" />
        <span>{{ __('Working…') }}</span>
    @else
        @if($iconStart)<x-icon :name="$iconStart" class="w-5 h-5" aria-hidden="true" />@endif
        {{ $slot }}
        @if($iconEnd)<x-icon :name="$iconEnd" class="w-5 h-5" aria-hidden="true" />@endif
    @endif
</button>

@if($reasonId)
    <p id="{{ $reasonId }}" class="mt-2 text-sm text-ink-muted">{{ $disabledReason }}</p>
@endif
