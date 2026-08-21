{{-- resources/views/components/radio-group.blade.php — Content_guidelines.md §5.6. --}}
@props([
    'name',
    'legend',
    'options',        // [value => label]
    'value' => null,
    'error' => null,
])

<fieldset {{ $attributes }}>
    <legend class="text-base font-semibold text-ink mb-2">{{ $legend }}</legend>

    <div class="flex flex-col gap-3">
        @foreach($options as $optionValue => $label)
            @php $id = $name.'-'.Str::slug((string) $optionValue); @endphp
            <div class="flex items-center gap-2">
                <input
                    type="radio"
                    id="{{ $id }}"
                    name="{{ $name }}"
                    value="{{ $optionValue }}"
                    @checked((string) $value === (string) $optionValue)
                    class="input form-radio w-5 h-5 border-border-strong text-brand"
                />
                <label for="{{ $id }}" class="text-base text-ink">{{ $label }}</label>
            </div>
        @endforeach
    </div>

    @if($error)
        <p class="mt-2 text-sm text-danger flex items-start gap-2">
            <x-icon name="exclamation-circle" class="w-5 h-5 shrink-0" aria-hidden="true" />
            <span>{{ $error }}</span>
        </p>
    @endif
</fieldset>
