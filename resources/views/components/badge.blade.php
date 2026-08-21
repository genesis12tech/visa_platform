{{-- resources/views/components/badge.blade.php — StatusBadge, Content_guidelines.md §5.3. --}}
@props([
    'status',
    'size' => 'md',   // sm|md
])

@php
    $map = [
        'complete' => ['icon' => 'check-circle', 'solid' => true, 'tone' => 'success', 'label' => __('Complete')],
        'accepted' => ['icon' => 'check-circle', 'solid' => true, 'tone' => 'success', 'label' => __('Accepted')],
        'in_progress' => ['icon' => 'clock', 'solid' => false, 'tone' => 'warning', 'label' => __('In progress')],
        'not_started' => ['icon' => 'minus-circle', 'solid' => false, 'tone' => 'neutral', 'label' => __('Not started')],
        'needs_attention' => ['icon' => 'exclamation-triangle', 'solid' => true, 'tone' => 'danger', 'label' => __('Needs attention')],
        'locked' => ['icon' => 'lock-closed', 'solid' => true, 'tone' => 'neutral', 'label' => __('Locked')],
        'rejected' => ['icon' => 'x-circle', 'solid' => true, 'tone' => 'danger', 'label' => __('Rejected')],
        'checking' => ['icon' => 'clock', 'solid' => false, 'tone' => 'info', 'label' => __('Checking')],
        'draft' => ['icon' => 'pencil-square', 'solid' => false, 'tone' => 'neutral', 'label' => __('Draft')],
        'submitted' => ['icon' => 'paper-airplane', 'solid' => false, 'tone' => 'info', 'label' => __('Submitted')],
        'in_review' => ['icon' => 'magnifying-glass', 'solid' => false, 'tone' => 'info', 'label' => __('In review')],
        'action_required' => ['icon' => 'exclamation-triangle', 'solid' => true, 'tone' => 'warning', 'label' => __('Action required')],
        'decision_made' => ['icon' => 'check-badge', 'solid' => false, 'tone' => 'info', 'label' => __('Decision made')],
        'closed' => ['icon' => 'archive-box', 'solid' => false, 'tone' => 'neutral', 'label' => __('Closed')],
    ];

    throw_unless(isset($map[$status]), new InvalidArgumentException("Unknown badge status key: [{$status}]. Add it to the map in resources/views/components/badge.blade.php against Content_guidelines.md §5.3 rather than inventing a rendering at the call site."));

    $entry = $map[$status];

    $toneClasses = [
        'success' => 'bg-success-subtle text-success border-success-border',
        'warning' => 'bg-warning-subtle text-warning border-warning-border',
        'danger' => 'bg-danger-subtle text-danger border-danger-border',
        'info' => 'bg-info-subtle text-info border-info-border',
        'neutral' => 'bg-surface-sunken text-ink-muted border-border',
    ];

    $sizeClasses = [
        'sm' => 'text-xs px-2 py-1 gap-1',
        'md' => 'text-sm px-3 py-1 gap-2',
    ];

    $iconSizeClasses = $size === 'sm' ? 'w-4 h-4' : 'w-5 h-5';
@endphp

<span {{ $attributes->class([
    'badge',
    'inline-flex items-center rounded-sm border font-semibold',
    $toneClasses[$entry['tone']],
    $sizeClasses[$size],
]) }}>
    <x-icon :name="$entry['icon']" :solid="$entry['solid']" :class="$iconSizeClasses.' status-icon shrink-0'" aria-hidden="true" />
    <span>{{ $entry['label'] }}</span>
</span>
