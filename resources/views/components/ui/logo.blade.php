@props([
    'href' => null,
    'word' => true,
])

@php($tag = $href ? 'a' : 'span')

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class('hm-brand') }} @if (! $word) aria-label="Hova Music" @endif>
    <svg class="hm-brand__mark" viewBox="0 0 384 384" aria-hidden="true" focusable="false">
        <rect width="384" height="384" fill="#000000" />
        <path fill="#F2F1EF" d="M129 127h34v47h49v-47h34v128h-34v-56h-49v56h-34z" />
    </svg>
    @if ($word)<span class="hm-brand__word">Hova Music</span>@endif
</{{ $tag }}>
