{{-- resources/views/components/skeleton.blade.php — Content_guidelines.md §5.11.
     Mirrors the final layout's shape via forwarded width/height classes at the
     call site — never a spinner on a blank region. --}}
@props([])

<div
    aria-busy="true"
    {{ $attributes->class(['skeleton bg-surface-sunken rounded-md motion-safe:animate-pulse']) }}
>
    <span class="sr-only">{{ __('Loading') }}</span>
</div>
