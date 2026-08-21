{{-- resources/views/components/field-group.blade.php — Content_guidelines.md §5.6, verbatim.
     The only permitted way to render a form field: label → input → error → hint. --}}
@props(['label', 'name', 'hint' => null, 'required' => false, 'error' => null])

@php
    $id = $name;
    $hintId = $hint ? "{$name}-hint" : null;
    $errorId = $error ? "{$name}-error" : null;
    $describedBy = collect([$hintId, $errorId])->filter()->implode(' ');
@endphp

<div {{ $attributes->class(['field-group mb-5']) }}>
    <label for="{{ $id }}" class="block text-base font-semibold text-ink mb-2">
        {{ $label }}
        @if($required)
            <span class="text-danger" aria-hidden="true">*</span>
            <span class="sr-only">{{ __('required') }}</span>
        @endif
    </label>

    {{ $slot }}   {{-- the input, receiving $id and $describedBy --}}

    @if($error)
        <p id="{{ $errorId }}" class="mt-2 text-sm text-danger flex items-start gap-2">
            <x-icon name="exclamation-circle" class="w-5 h-5 shrink-0" aria-hidden="true" />
            <span>{{ $error }}</span>
        </p>
    @endif

    @if($hint)
        <p id="{{ $hintId }}" class="mt-2 text-sm text-ink-muted">{{ $hint }}</p>
    @endif
</div>
