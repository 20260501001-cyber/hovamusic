<x-layouts.public :seo="$seo">
    <section class="hm-container hm-page-hero" aria-labelledby="sayfa-baslik">
        @include('site.partials.breadcrumbs')
        <p class="hm-eyebrow">{{ __('site.how.eyebrow') }}</p>
        <h1 id="sayfa-baslik" class="hm-display-l m-0">{{ __('site.how.title') }}</h1>
        <p class="hm-lead m-0">{{ __('site.how.lead') }}</p>
    </section>

    <section class="hm-container pb-16" aria-label="{{ __('site.how.eyebrow') }}">
        <ol class="hm-index">
            @foreach (__('site.how.details') as $i => $step)
                <li class="hm-index__row">
                    <span class="hm-index__num">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <h2 class="hm-index__title">{{ $step['title'] }}</h2>
                    <div class="hm-index__body">
                        <ul>
                            @foreach ($step['items'] as $item)
                                <li>{{ str_replace(':days', (string) $leadDays, $item) }}</li>
                            @endforeach
                        </ul>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="hm-section" aria-label="{{ __('site.home.panel_eyebrow') }}">
        <div class="hm-container grid gap-6">
            <x-site.screenshot name="yayinlar" :alt="__('panel.nav.releases')" :caption="__('panel.nav.releases')" />
        </div>
    </section>

    <section class="hm-container hm-closing" aria-labelledby="kapanis-baslik">
        <h2 id="kapanis-baslik" class="hm-display-l m-0">{{ __('site.home.closing_title') }}</h2>
        <p class="hm-lead m-0">{{ __('site.home.closing_body') }}</p>
        <div class="hm-hero__actions">
            <x-ui.button :href="route('register')" variant="primary" icon-right="arrow-right">{{ __('site.home.cta_primary') }}</x-ui.button>
            <x-ui.button :href="route('faq')" variant="secondary">{{ __('site.nav.faq') }}</x-ui.button>
        </div>
    </section>
</x-layouts.public>
