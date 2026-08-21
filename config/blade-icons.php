<?php

// Overrides vendor defaults (merged via mergeConfigFrom — only listed keys
// take effect, everything else falls back to the package's own config).
//
// blade-ui-kit/blade-icons (a transitive dependency of
// rappasoft/laravel-livewire-tables, which needs blade-heroicons for its own
// UI) registers a generic <x-icon name="..."> component by default. That
// claims the exact tag name Content_guidelines.md §5 specifies for this
// project's own icon wrapper (resources/views/components/icon.blade.php),
// which maps the §2.7 fixed icon vocabulary onto self-hosted Heroicons via
// <x-dynamic-component>. Disabling the vendor's auto-registration here is
// what frees <x-icon> for that component — neither Livewire nor
// laravel-livewire-tables use the generic tag in their own views.
return [
    'components' => [
        'default' => null,
    ],
];
