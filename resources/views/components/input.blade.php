{{-- resources/views/components/input.blade.php — Content_guidelines.md §5.6 input state table. --}}
@props([
    'name',
    'type' => 'text',
    'value' => null,
    'error' => null,
    'hint' => null,
    'readonly' => false,
])

@php
    $describedBy = collect([$hint ? "{$name}-hint" : null, $error ? "{$name}-error" : null])->filter()->implode(' ');

    $stateClasses = match (true) {
        (bool) $error => 'border-danger border-2',
        (bool) $readonly => 'border-border bg-surface-sunken',
        default => 'border-border-strong bg-surface',
    };
@endphp

<input
    id="{{ $name }}"
    name="{{ $name }}"
    type="{{ $type }}"
    @if($value !== null) value="{{ $value }}" @endif
    @readonly($readonly)
    @if($error) aria-invalid="true" @endif
    @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
    {{ $attributes->class([
        'input form-input',
        'block w-full rounded-md border text-base px-3 py-3 min-h-tap text-ink',
        'disabled:bg-disabled disabled:text-disabled-ink disabled:border-disabled-border disabled:cursor-not-allowed',
        $stateClasses,
    ]) }}
/>
