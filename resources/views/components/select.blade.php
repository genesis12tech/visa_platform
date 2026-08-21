{{-- resources/views/components/select.blade.php — Content_guidelines.md §5.6 input state table. --}}
@props([
    'name',
    'options',        // [value => label]
    'value' => null,
    'placeholder' => null,
    'error' => null,
    'hint' => null,
])

@php
    $describedBy = collect([$hint ? "{$name}-hint" : null, $error ? "{$name}-error" : null])->filter()->implode(' ');

    $stateClasses = (bool) $error ? 'border-danger border-2' : 'border-border-strong bg-surface';
@endphp

<select
    id="{{ $name }}"
    name="{{ $name }}"
    @if($error) aria-invalid="true" @endif
    @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
    {{ $attributes->class([
        'input form-select',
        'block w-full rounded-md border text-base px-3 py-3 min-h-tap text-ink',
        'disabled:bg-disabled disabled:text-disabled-ink disabled:border-disabled-border disabled:cursor-not-allowed',
        $stateClasses,
    ]) }}
>
    @if($placeholder)
        <option value="" disabled{{ $value === null ? ' selected' : '' }}>{{ $placeholder }}</option>
    @endif

    @foreach($options as $optionValue => $label)
        <option value="{{ $optionValue }}"{{ (string) $value === (string) $optionValue ? ' selected' : '' }}>{{ $label }}</option>
    @endforeach
</select>
