@props(['status'])

@php
    $tones = [
        'draft' => 'neutral',
        'in_review' => 'info',
        'needs_changes' => 'warning',
        'approved' => 'accent',
        'delivered' => 'accent',
        'live' => 'success',
        'rejected' => 'danger',
        'takedown_requested' => 'warning',
        'taken_down' => 'neutral',
    ];
    $key = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $tone = $tones[$key] ?? 'neutral';
    $hollow = in_array($key, ['draft', 'taken_down'], true);
@endphp

<span {{ $attributes->class(['hm-badge', 'hm-badge--'.$tone, 'hm-badge--hollow' => $hollow]) }}>
    <span class="hm-badge__dot" aria-hidden="true"></span>{{ __('release.statuses.'.$key) }}
</span>
