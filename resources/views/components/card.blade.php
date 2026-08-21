{{-- resources/views/components/card.blade.php — Content_guidelines.md §5.5. --}}
@props([
    'padding' => 'md',       // sm|md
    'interactive' => false,
    'tone' => 'default',
    'href' => null,          // required when interactive is true
])

@php
    $paddingClasses = $padding === 'sm' ? 'p-4' : 'p-4 lg:p-6';

    $base = [
        'card',
        'bg-surface-raised border border-border rounded-lg shadow-1',
        $paddingClasses,
    ];
@endphp

@if($interactive)
    {{-- The whole card is the link — never nest another interactive element
         inside it, which would produce an unreachable control for keyboard
         users (§5.5). --}}
    <a href="{{ $href }}" {{ $attributes->class(array_merge($base, ['block hover:shadow-2 transition-shadow duration-fast'])) }}>
        {{ $slot }}
    </a>
@else
    <div {{ $attributes->class($base) }}>
        {{ $slot }}
    </div>
@endif
