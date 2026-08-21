{{-- resources/views/components/date-input.blade.php — Content_guidelines.md §5.7.
     Passport and birth dates are never free text: three separate numeric-mode
     fields, with the format always shown rather than inferred from locale. --}}
@props([
    'name',
    'label',
    'day' => null,
    'month' => null,
    'year' => null,
    'error' => null,
])

@php
    $hintId = "{$name}-hint";
    $errorId = "{$name}-error";
    $describedBy = collect([$hintId, $error ? $errorId : null])->filter()->implode(' ');

    $fieldClasses = 'input form-input block rounded-md border border-border-strong bg-surface text-base px-3 py-3 min-h-tap text-ink';
@endphp

<div {{ $attributes }}>
    <span class="block text-base font-semibold text-ink mb-2" id="{{ $name }}-legend">{{ $label }}</span>
    <p id="{{ $hintId }}" class="text-sm text-ink-muted mb-2">{{ __('For example, 14 03 1991') }}</p>

    <div class="flex gap-3" role="group" aria-labelledby="{{ $name }}-legend" aria-describedby="{{ $describedBy }}">
        <div>
            <label for="{{ $name }}_day" class="block text-sm text-ink-muted mb-1">{{ __('Day') }}</label>
            <input type="text" inputmode="numeric" id="{{ $name }}_day" name="{{ $name }}[day]" value="{{ $day }}" maxlength="2" class="{{ $fieldClasses }} w-16" />
        </div>
        <div>
            <label for="{{ $name }}_month" class="block text-sm text-ink-muted mb-1">{{ __('Month') }}</label>
            <input type="text" inputmode="numeric" id="{{ $name }}_month" name="{{ $name }}[month]" value="{{ $month }}" maxlength="2" class="{{ $fieldClasses }} w-16" />
        </div>
        <div>
            <label for="{{ $name }}_year" class="block text-sm text-ink-muted mb-1">{{ __('Year') }}</label>
            <input type="text" inputmode="numeric" id="{{ $name }}_year" name="{{ $name }}[year]" value="{{ $year }}" maxlength="4" class="{{ $fieldClasses }} w-20" />
        </div>
    </div>

    @if($error)
        <p id="{{ $errorId }}" class="mt-2 text-sm text-danger flex items-start gap-2">
            <x-icon name="exclamation-circle" class="w-5 h-5 shrink-0" aria-hidden="true" />
            <span>{{ $error }}</span>
        </p>
    @endif
</div>
