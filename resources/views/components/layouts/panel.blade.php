@props([
    'title' => null,
])

@php
    $user = auth()->user();
    $theme = $user?->theme?->value ?? 'system';
    $items = [
        ['route' => 'panel.dashboard', 'icon' => 'layout-dashboard', 'label' => __('panel.nav.dashboard')],
        ['route' => 'panel.releases.index', 'active' => 'panel.releases.*', 'icon' => 'disc-3', 'label' => __('panel.nav.releases')],
        ['route' => 'panel.artists', 'icon' => 'mic-vocal', 'label' => __('panel.nav.artists')],
        ['route' => 'panel.account', 'icon' => 'user-cog', 'label' => __('panel.nav.account')],
    ];
@endphp

<x-layouts.base :title="$title" :noindex="true" :theme="$theme">
    <a href="#icerik" class="hm-skip">{{ __('ui.skip_to_content') }}</a>
    <div class="hm-panel">
        <aside class="hm-panel__sidebar" aria-label="{{ __('panel.nav.label') }}">
            <x-ui.logo :href="route('panel.dashboard')" />
            @include('panel.partials.nav', ['items' => $items])
            @include('panel.partials.user-chip', ['user' => $user])
        </aside>

        <header class="hm-panel__topbar">
            <x-ui.logo :href="route('panel.dashboard')" />
            <button type="button" class="hm-btn hm-btn--ghost hm-btn--s" data-drawer-open="panel-drawer" aria-controls="panel-drawer" aria-label="{{ __('panel.nav.open') }}">
                <x-lucide-menu class="hm-icon" width="20" height="20" aria-hidden="true" />
            </button>
        </header>

        <div id="panel-drawer" class="hm-drawer" role="dialog" aria-modal="true" aria-label="{{ __('panel.nav.label') }}">
            <div class="hm-drawer__backdrop" data-drawer-close></div>
            <div class="hm-drawer__panel">
                <div class="flex items-center justify-between">
                    <x-ui.logo :href="route('panel.dashboard')" />
                    <button type="button" class="hm-btn hm-btn--ghost hm-btn--s" data-drawer-close aria-label="{{ __('panel.nav.close') }}">
                        <x-lucide-x class="hm-icon" width="20" height="20" aria-hidden="true" />
                    </button>
                </div>
                @include('panel.partials.nav', ['items' => $items])
                @include('panel.partials.user-chip', ['user' => $user])
            </div>
        </div>

        <main class="hm-panel__main" id="icerik">
            <div class="hm-panel__inner">
                <x-ui.flash />
                {{ $slot }}
            </div>
        </main>
    </div>
</x-layouts.base>
