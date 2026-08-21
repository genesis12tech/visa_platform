{{-- resources/views/components/checkbox.blade.php — Content_guidelines.md §5.6. A real
     <label>, never a placeholder standing in for one. --}}
@props([
    'name',
    'label',
    'value' => '1',
    'checked' => false,
    'disabled' => false,
])

@php
    $id = $name.'-'.Str::slug((string) $value);
@endphp

<div class="flex items-center gap-2">
    <input
        type="checkbox"
        id="{{ $id }}"
        name="{{ $name }}"
        value="{{ $value }}"
        @checked($checked)
        @disabled($disabled)
        {{ $attributes->class([
            'input form-checkbox w-5 h-5 rounded border-border-strong text-brand',
            'disabled:bg-disabled disabled:border-disabled-border disabled:cursor-not-allowed',
        ]) }}
    />
    <label for="{{ $id }}" class="text-base text-ink">{{ $label }}</label>
</div>
