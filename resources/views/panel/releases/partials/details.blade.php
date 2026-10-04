{{-- Yayın bilgileri; $release ilişkileri (artists, genre, subgenre, platforms) yüklü olmalı. --}}
@use('App\Support\Format')
@use('App\Support\Locale\Countries')
@use('App\Support\Locale\Languages')

@php
    $territories = $release->territory_mode->value === 'worldwide' || ! $release->territories
        ? $release->territory_mode->label()
        : $release->territory_mode->label().': '.collect($release->territories)->map(fn ($code) => Countries::name($code))->implode(', ');
    $dash = '—';
@endphp

<dl class="m-0 grid gap-4 sm:grid-cols-2">
    <div class="hm-kv"><dt>{{ __('release.info.type') }}</dt><dd>{{ $release->type?->label() ?? $dash }}</dd></div>
    <div class="hm-kv"><dt>{{ __('release.info.label_name') }}</dt><dd>{{ $release->label_name ?: $dash }}</dd></div>
    <div class="hm-kv">
        <dt>{{ __('release.info.genre') }}</dt>
        <dd>{{ $release->genre?->name ?? $dash }}@if ($release->subgenre) · {{ $release->subgenre->name }}@endif</dd>
    </div>
    <div class="hm-kv"><dt>{{ __('release.info.language') }}</dt><dd>{{ Languages::name($release->language) ?? $dash }}</dd></div>
    <div class="hm-kv"><dt>{{ __('release.info.release_date') }}</dt><dd>{{ Format::longDate($release->release_date) ?: $dash }}</dd></div>
    @if ($release->original_release_date)
        <div class="hm-kv"><dt>{{ __('release.info.original_release_date') }}</dt><dd>{{ Format::longDate($release->original_release_date) }}</dd></div>
    @endif
    <div class="hm-kv"><dt>{{ __('release.info.p_line') }}</dt><dd>{{ $release->p_line ? '℗ '.$release->p_line : $dash }}</dd></div>
    <div class="hm-kv"><dt>{{ __('release.info.c_line') }}</dt><dd>{{ $release->c_line ? '© '.$release->c_line : $dash }}</dd></div>
    <div class="hm-kv">
        <dt>{{ __('release.info.upc') }}</dt>
        <dd>@if ($release->upc)<span class="hm-code">{{ $release->upc }}</span>@else<span class="text-ink-subtle">{{ __('release.upc_pending') }}</span>@endif</dd>
    </div>
    <div class="hm-kv"><dt>{{ __('release.info.explicit') }}</dt><dd>{{ $release->explicit ? __('release.show.yes') : __('release.show.no') }}</dd></div>
    <div class="hm-kv sm:col-span-2">
        <dt>{{ __('release.show.stores') }}</dt>
        <dd>{{ $release->platforms->isNotEmpty() ? $release->platforms->sortBy('sort')->pluck('name')->implode(', ') : $dash }}</dd>
    </div>
    <div class="hm-kv sm:col-span-2"><dt>{{ __('release.show.territories') }}</dt><dd>{{ $territories }}</dd></div>
</dl>
