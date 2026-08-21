{{-- resources/views/components/alert.blade.php — Content_guidelines.md §5.4. --}}
@props([
    'tone' => 'info',        // info|success|warning|danger
    'title' => null,
    'dismissible' => false,
    'level' => 'inline',      // inline|page
])

@php
    // §5.4 states role="status" for info/success and role="alert" for danger
    // explicitly, without naming warning. Warning ("something needs attention
    // but nothing is broken") reads as non-urgent, so it takes role="status"
    // alongside info/success rather than the interrupting role="alert" danger
    // gets — never role="alert" on a persistent, non-error alert (§5.4).
    $toneMap = [
        'info' => ['classes' => 'bg-info-subtle border-info-border text-info', 'icon' => 'information-circle', 'role' => 'status'],
        'success' => ['classes' => 'bg-success-subtle border-success-border text-success', 'icon' => 'check-circle', 'role' => 'status'],
        'warning' => ['classes' => 'bg-warning-subtle border-warning-border text-warning', 'icon' => 'exclamation-triangle', 'role' => 'status'],
        'danger' => ['classes' => 'bg-danger-subtle border-danger-border text-danger', 'icon' => 'x-circle', 'role' => 'alert'],
    ];

    throw_unless(isset($toneMap[$tone]), new InvalidArgumentException("Unknown alert tone: [{$tone}]"));

    $entry = $toneMap[$tone];
@endphp

<div
    role="{{ $entry['role'] }}"
    {{ $attributes->class([
        'alert',
        'flex items-start gap-3 rounded-lg border p-4',
        $entry['classes'],
        'w-full' => $level === 'page',
    ]) }}
>
    <x-icon :name="$entry['icon']" solid class="w-6 h-6 status-icon shrink-0" aria-hidden="true" />

    <div class="flex-1 min-w-0">
        @if($title)
            <p class="text-base font-semibold text-ink">{{ $title }}</p>
        @endif

        <div @class(['text-base', 'mt-1' => $title])>
            {{ $slot }}
        </div>

        @isset($actions)
            <div class="mt-3 flex gap-3">{{ $actions }}</div>
        @endisset
    </div>

    @if($dismissible)
        <button
            type="button"
            x-on:click="$el.closest('[role=\'status\'], [role=\'alert\']').remove()"
            class="shrink-0 text-ink-muted hover:text-ink"
            aria-label="{{ __('Dismiss') }}"
        >
            <x-icon name="x-mark" class="w-5 h-5" aria-hidden="true" />
        </button>
    @endif
</div>
