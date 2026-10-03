@props([
    'variant' => 'secondary',
    'size' => 'm',
    'icon' => null,
    'iconRight' => null,
    'href' => null,
    'type' => 'button',
    'loadingText' => null,
])

@php
    $classes = 'hm-btn hm-btn--'.$variant.' hm-btn--'.$size;
    $iconSize = $size === 's' ? 16 : 18;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-dynamic-component :component="'lucide-'.$icon" class="hm-icon" :width="$iconSize" :height="$iconSize" aria-hidden="true" />@endif
        <span>{{ $slot }}</span>
        @if ($iconRight)<x-dynamic-component :component="'lucide-'.$iconRight" class="hm-icon" :width="$iconSize" :height="$iconSize" aria-hidden="true" />@endif
    </a>
@else
    <button type="{{ $type }}" @if ($loadingText) data-loading-text="{{ $loadingText }}" @endif {{ $attributes->class($classes) }}>
        @if ($icon)<x-dynamic-component :component="'lucide-'.$icon" class="hm-icon" :width="$iconSize" :height="$iconSize" aria-hidden="true" />@endif
        <span>{{ $slot }}</span>
        @if ($iconRight)<x-dynamic-component :component="'lucide-'.$iconRight" class="hm-icon" :width="$iconSize" :height="$iconSize" aria-hidden="true" />@endif
    </button>
@endif
