@props([
    'name',
    'alt',
    'caption' => null,
    'eager' => false,
    'sizes' => '(min-width: 1200px) 1100px, 100vw',
])

{{-- Demo verili gerçek panel ekran görüntüsü (php artisan hova:screenshots). Dosya yoksa hiçbir şey çizilmez. --}}
@php
    $shot = \App\Support\Images\Screenshots::find($name);
@endphp

@if ($shot)
    <figure {{ $attributes->class('hm-shot') }}>
        <div class="hm-shot__frame">
            <picture>
                @if ($shot['avif'])
                    <source type="image/avif" srcset="{{ $shot['avif'] }}" sizes="{{ $sizes }}">
                @endif
                <source type="image/webp" srcset="{{ $shot['webp'] }}" sizes="{{ $sizes }}">
                <img src="{{ $shot['src'] }}" width="{{ $shot['width'] }}" height="{{ $shot['height'] }}" alt="{{ $alt }}"
                    @if ($eager) fetchpriority="high" decoding="async" @else loading="lazy" decoding="async" @endif>
            </picture>
        </div>
        <figcaption>{{ $caption ? $caption.' ' : '' }}{{ __('site.screenshot_note') }}</figcaption>
    </figure>
@endif
