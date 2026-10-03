@props([
    'title' => null,
    'description' => null,
])

<x-layouts.base :title="$title" :description="$description" theme="dark">
    <a href="#icerik" class="hm-skip">{{ __('ui.skip_to_content') }}</a>
    <header class="hm-site-header">
        <div class="hm-container hm-site-header__inner">
            <x-ui.logo :href="route('home')" />
            <nav class="flex items-center gap-2" aria-label="{{ __('ui.account_nav') }}">
                @auth
                    <x-ui.button :href="route('panel.dashboard')" variant="primary" size="s">{{ __('site.nav.panel') }}</x-ui.button>
                @else
                    <x-ui.button :href="route('login')" variant="ghost" size="s">{{ __('site.nav.login') }}</x-ui.button>
                    <x-ui.button :href="route('register')" variant="primary" size="s">{{ __('site.nav.register') }}</x-ui.button>
                @endauth
            </nav>
        </div>
    </header>

    <main id="icerik">
        {{ $slot }}
    </main>

    <footer class="hm-site-footer">
        <div class="hm-container flex flex-wrap items-center justify-between gap-4">
            <x-ui.logo :word="true" />
            <p class="m-0">© {{ now()->year }} Hova Music</p>
        </div>
    </footer>
</x-layouts.base>
