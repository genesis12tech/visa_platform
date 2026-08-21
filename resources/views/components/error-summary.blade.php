{{-- resources/views/components/error-summary.blade.php — Content_guidelines.md §5.8.
     Rendered at the top of a form after a failed submit. Focus moves to the
     <h2> on render (WCAG 3.3.1) via the x-init watcher below. --}}
@props(['errors'])

@php
    // Blade's own $errors is a ViewErrorBag (a container of named MessageBags,
    // 'default' unless a request used ->errorBag()), not a MessageBag itself —
    // casting THAT to an array instead of unwrapping it produces the class's
    // mangled internal properties, not field => messages, and breaks below.
    $bag = $errors instanceof \Illuminate\Support\ViewErrorBag ? $errors->getBag('default') : $errors;
    $messages = $bag instanceof \Illuminate\Support\MessageBag ? $bag->messages() : (array) $bag;
@endphp

@if(! empty($messages))
    <div
        role="alert"
        {{ $attributes->class(['error-summary rounded-lg border-2 border-danger bg-danger-subtle p-4']) }}
    >
        <h2
            tabindex="-1"
            x-data
            x-init="$el.focus()"
            class="text-xl font-semibold text-danger mb-3"
        >{{ __('There is a problem') }}</h2>

        <ol class="list-decimal list-inside flex flex-col gap-2">
            @foreach($messages as $field => $fieldMessages)
                <li>
                    <a href="#{{ $field }}" class="text-danger underline underline-offset-2 hover:text-danger-hover">
                        {{ is_array($fieldMessages) ? $fieldMessages[0] : $fieldMessages }}
                    </a>
                </li>
            @endforeach
        </ol>
    </div>
@endif
