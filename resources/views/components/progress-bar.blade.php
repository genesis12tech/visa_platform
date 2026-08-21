{{-- resources/views/components/progress-bar.blade.php — Content_guidelines.md §5.10.
     The bar is supplementary — the visible text equivalent is what actually
     conveys progress, e.g. "4 of 7 sections complete". --}}
@props(['value', 'max', 'label'])

@php
    $percentage = $max > 0 ? min(100, max(0, ($value / $max) * 100)) : 0;
    $text = __(':value of :max :label', ['value' => $value, 'max' => $max, 'label' => $label]);
@endphp

<div {{ $attributes->class(['flex flex-col gap-2']) }}>
    <p class="text-sm text-ink-muted">{{ $text }}</p>

    <div
        role="progressbar"
        aria-valuenow="{{ $value }}"
        aria-valuemin="0"
        aria-valuemax="{{ $max }}"
        aria-label="{{ $text }}"
        data-print="hide"
        class="progress-bar h-2 rounded-full bg-surface-sunken overflow-hidden"
    >
        <div class="h-full rounded-full bg-brand" style="width: {{ $percentage }}%"></div>
    </div>
</div>
