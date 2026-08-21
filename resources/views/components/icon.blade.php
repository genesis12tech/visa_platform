{{--
    resources/views/components/icon.blade.php — Content_guidelines.md §2.7.

    Self-hosted Heroicons 2.x via blade-heroicons (a transitive dependency —
    see config/blade-icons.php for why the generic vendor <x-icon> tag had to
    be disabled to free this name). Outline by default, solid when the
    `solid` prop is present, per the §2.7 fixed icon vocabulary table.
    Callers control size/colour via forwarded attributes (`class="w-5 h-5"`,
    `aria-hidden="true"` or `aria-label="..."`) — see every call site in §5.
--}}
@props([
    'name',
    'solid' => false,
])

<x-dynamic-component :component="'heroicon-'.($solid ? 's' : 'o').'-'.$name" {{ $attributes }} />
