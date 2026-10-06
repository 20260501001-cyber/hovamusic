@props([
    'title' => null,
    'description' => null,
    'seo' => null,
])

@php
    $seo ??= app(\App\Support\Seo\SeoFactory::class)->make($title ?? __('seo.site_name'), $description);
    $nav = [
        ['route' => 'pricing', 'label' => __('site.nav.pricing')],
        ['route' => 'how', 'label' => __('site.nav.how')],
        ['route' => 'platforms', 'label' => __('site.nav.platforms')],
        ['route' => 'blog.index', 'active' => 'blog.*', 'label' => __('site.nav.blog')],
        ['route' => 'faq', 'label' => __('site.nav.faq')],
    ];
    $legalLinks = \App\Support\Content\ContentCache::remember('footer:legal', fn (): array => \App\Models\LegalDocument::query()
        ->where('is_public', true)->orderBy('sort')->get(['slug', 'title'])
        ->map(fn ($doc): array => ['slug' => $doc->slug, 'title' => $doc->title])->all());
@endphp

<x-layouts.base :seo="$seo" theme="dark">
    <a href="#icerik" class="hm-skip">{{ __('ui.skip_to_content') }}</a>
    <header class="hm-site-header">
        <div class="hm-container hm-site-header__inner">
            <x-ui.logo :href="route('home')" />
            <nav class="hm-site-nav" aria-label="{{ __('site.nav.label') }}">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}" @if (request()->routeIs($item['active'] ?? $item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="hm-site-actions">
                @auth
                    <x-ui.button :href="route('panel.dashboard')" variant="primary" size="s">{{ __('site.nav.panel') }}</x-ui.button>
                @else
                    <x-ui.button :href="route('login')" variant="ghost" size="s">{{ __('site.nav.login') }}</x-ui.button>
                    <x-ui.button :href="route('register')" variant="primary" size="s">{{ __('site.nav.register') }}</x-ui.button>
                @endauth
                <button type="button" class="hm-btn hm-btn--ghost hm-btn--s hm-site-menu" data-drawer-open="site-drawer" aria-controls="site-drawer" aria-label="{{ __('site.nav.open') }}">
                    <x-lucide-menu class="hm-icon" width="20" height="20" aria-hidden="true" />
                </button>
            </div>
        </div>
    </header>

    <div id="site-drawer" class="hm-drawer" role="dialog" aria-modal="true" aria-label="{{ __('site.nav.label') }}">
        <div class="hm-drawer__backdrop" data-drawer-close></div>
        <div class="hm-drawer__panel">
            <div class="flex items-center justify-between">
                <x-ui.logo :href="route('home')" />
                <button type="button" class="hm-btn hm-btn--ghost hm-btn--s" data-drawer-close aria-label="{{ __('site.nav.close') }}">
                    <x-lucide-x class="hm-icon" width="20" height="20" aria-hidden="true" />
                </button>
            </div>
            <nav class="hm-site-drawer-nav" aria-label="{{ __('site.nav.label') }}">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                @endforeach
                <a href="{{ route('about') }}">{{ __('site.nav.about') }}</a>
                <a href="{{ route('contact') }}">{{ __('site.nav.contact') }}</a>
            </nav>
        </div>
    </div>

    <main id="icerik">
        {{ $slot }}
    </main>

    <footer class="hm-site-footer">
        <div class="hm-container">
            <div class="hm-footer-grid">
                <div class="hm-footer-col">
                    <x-ui.logo :href="route('home')" />
                    <p class="m-0">{{ __('site.footer.statement') }}</p>
                </div>
                <div class="hm-footer-col">
                    <h2>{{ __('site.footer.product') }}</h2>
                    <ul>
                        <li><a href="{{ route('pricing') }}">{{ __('site.nav.pricing') }}</a></li>
                        <li><a href="{{ route('how') }}">{{ __('site.nav.how') }}</a></li>
                        <li><a href="{{ route('platforms') }}">{{ __('site.nav.platforms') }}</a></li>
                    </ul>
                </div>
                <div class="hm-footer-col">
                    <h2>{{ __('site.footer.resources') }}</h2>
                    <ul>
                        <li><a href="{{ route('blog.index') }}">{{ __('site.nav.blog') }}</a></li>
                        <li><a href="{{ route('faq') }}">{{ __('site.nav.faq') }}</a></li>
                    </ul>
                </div>
                <div class="hm-footer-col">
                    <h2>{{ __('site.footer.company') }}</h2>
                    <ul>
                        <li><a href="{{ route('about') }}">{{ __('site.nav.about') }}</a></li>
                        <li><a href="{{ route('contact') }}">{{ __('site.nav.contact') }}</a></li>
                    </ul>
                </div>
                <div class="hm-footer-col">
                    <h2>{{ __('site.footer.legal') }}</h2>
                    <ul>
                        @foreach ($legalLinks as $link)
                            <li><a href="{{ route('legal.show', $link['slug']) }}">{{ $link['title'] }}</a></li>
                        @endforeach
                        <li><button type="button" data-cookie-settings>{{ __('site.footer.cookies') }}</button></li>
                    </ul>
                </div>
            </div>
            <div class="hm-footer-bottom">
                <p class="m-0">{{ __('site.footer.rights', ['year' => now()->year]) }}</p>
                <p class="m-0 hm-code">hovamusic.com</p>
            </div>
        </div>
    </footer>
</x-layouts.base>
