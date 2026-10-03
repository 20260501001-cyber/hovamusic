@props([
    'tone' => 'info',
    'title' => null,
])

@php
    $icon = ['info' => 'info', 'success' => 'circle-check', 'warning' => 'triangle-alert', 'danger' => 'circle-x'][$tone] ?? 'info';
@endphp

<div {{ $attributes->class(['hm-alert', 'hm-alert--'.$tone]) }} role="{{ $tone === 'danger' ? 'alert' : 'status' }}">
    <span class="hm-alert__icon"><x-dynamic-component :component="'lucide-'.$icon" class="hm-icon" width="20" height="20" aria-hidden="true" /></span>
    <div class="hm-alert__body">
        @if ($title)<p class="hm-alert__title">{{ $title }}</p>@endif
        @if ($slot->isNotEmpty())<div class="hm-alert__text">{{ $slot }}</div>@endif
        @isset($action)<div class="hm-alert__action">{{ $action }}</div>@endisset
    </div>
</div>
