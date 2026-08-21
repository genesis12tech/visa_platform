{{-- resources/views/components/empty-state.blade.php — Content_guidelines.md §5.9.
     Copy comes from §7.4 — never invented at the call site. --}}
@props([
    'type' => 'first_run',   // first_run|filtered|permission|positive
    'icon',
    'headline',
    'body' => null,
])

<div {{ $attributes->class(['empty-state flex flex-col items-center text-center py-12 px-4']) }}>
    <x-icon :name="$icon" class="w-12 h-12 text-ink-subtle mb-4" aria-hidden="true" />

    <p class="text-xl font-semibold text-ink mb-2">{{ $headline }}</p>

    @if($body)
        <p class="text-base text-ink-muted mb-4 max-w-content">{{ $body }}</p>
    @endif

    @isset($action)
        <div>{{ $action }}</div>
    @endisset
</div>
