{{-- resources/views/components/textarea.blade.php — Content_guidelines.md §5.6 input state table. --}}
@props([
    'name',
    'value' => null,
    'error' => null,
    'hint' => null,
    'readonly' => false,
    'rows' => 4,
])

@php
    $describedBy = collect([$hint ? "{$name}-hint" : null, $error ? "{$name}-error" : null])->filter()->implode(' ');

    $stateClasses = match (true) {
        (bool) $error => 'border-danger border-2',
        (bool) $readonly => 'border-border bg-surface-sunken',
        default => 'border-border-strong bg-surface',
    };
@endphp

<textarea
    id="{{ $name }}"
    name="{{ $name }}"
    rows="{{ $rows }}"
    @readonly($readonly)
    @if($error) aria-invalid="true" @endif
    @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
    {{ $attributes->class([
        'input form-textarea',
        'block w-full rounded-md border text-base px-3 py-3 text-ink',
        'disabled:bg-disabled disabled:text-disabled-ink disabled:border-disabled-border disabled:cursor-not-allowed',
        $stateClasses,
    ]) }}
>{{ $value }}</textarea>
