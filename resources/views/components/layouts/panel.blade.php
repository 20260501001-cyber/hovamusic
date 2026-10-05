@props([
    'title' => null,
])

@php
    $user = auth()->user();
    $theme = $user?->theme?->value ?? 'system';
    $unread = $user?->unreadNotifications()->count() ?? 0;
    $impersonating = app(\App\Domain\Users\Impersonation::class)->active();
    $items = [
        ['route' => 'panel.dashboard', 'icon' => 'layout-dashboard', 'label' => __('panel.nav.dashboard')],
        ['route' => 'panel.releases.index', 'active' => 'panel.releases.*', 'icon' => 'disc-3', 'label' => __('panel.nav.releases')],
        ['route' => 'panel.artists', 'icon' => 'mic-vocal', 'label' => __('panel.nav.artists')],
        ['route' => 'panel.plans.index', 'active' => 'panel.plans.*', 'icon' => 'credit-card', 'label' => __('panel.nav.plan')],
        ['route' => 'panel.notifications.index', 'active' => 'panel.notifications.*', 'icon' => 'bell', 'label' => __('panel.nav.notifications'),
            'badge' => $unread > 0 ? ($unread > 99 ? '99+' : (string) $unread) : null, 'badge_label' => __('panel.nav.unread', ['count' => $unread])],
        ['route' => 'panel.account', 'icon' => 'user-cog', 'label' => __('panel.nav.account')],
    ];
@endphp

<x-layouts.base :title="$title" :noindex="true" :theme="$theme">
    <a href="#icerik" class="hm-skip">{{ __('ui.skip_to_content') }}</a>
    @if ($impersonating)
        <div class="hm-impersonation" role="status">
            <x-lucide-eye class="hm-icon flex-none" width="18" height="18" aria-hidden="true" />
            <p class="m-0 min-w-0 flex-1">{{ __('panel.impersonation.banner', ['name' => $user->name, 'email' => $user->email]) }}</p>
            <form method="POST" action="{{ route('impersonation.end') }}">
                @csrf
                <x-ui.button type="submit" size="s" variant="secondary" icon="log-out">{{ __('panel.impersonation.end') }}</x-ui.button>
            </form>
        </div>
    @endif
    <div class="hm-panel">
        <aside class="hm-panel__sidebar" aria-label="{{ __('panel.nav.label') }}">
            <x-ui.logo :href="route('panel.dashboard')" />
            @include('panel.partials.nav', ['items' => $items])
            @include('panel.partials.user-chip', ['user' => $user, 'impersonating' => $impersonating])
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
                @include('panel.partials.user-chip', ['user' => $user, 'impersonating' => $impersonating])
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
