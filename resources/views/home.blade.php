<x-layouts.public :seo="$seo">
    <section class="hm-container hm-hero" aria-labelledby="hero-baslik">
        <p class="hm-eyebrow">{{ __('site.home.eyebrow') }}</p>
        <h1 id="hero-baslik" class="hm-display-xl hm-hero__title">{{ __('site.home.title') }}</h1>
        <p class="hm-lead m-0">{{ __('site.home.lead') }}</p>
        <div class="hm-hero__actions">
            <x-ui.button :href="route('register')" variant="primary" icon-right="arrow-right">{{ __('site.home.cta_primary') }}</x-ui.button>
            <x-ui.button :href="route('pricing')" variant="secondary">{{ __('site.home.cta_secondary') }}</x-ui.button>
        </div>
    </section>

    <div class="hm-container pb-16">
        <x-site.screenshot name="sihirbaz" :alt="__('site.home.hero_caption')" :caption="__('site.home.hero_caption')" :eager="true" />
    </div>

    <section class="hm-section" aria-labelledby="surec-baslik">
        <div class="hm-container">
            <div class="hm-section__head hm-section__head--split">
                <div class="grid gap-3">
                    <p class="hm-eyebrow">{{ __('site.home.process_eyebrow') }}</p>
                    <h2 id="surec-baslik" class="hm-display-l m-0">{{ __('site.home.process_title') }}</h2>
                </div>
                <a href="{{ route('how') }}" class="hm-section__more">{{ __('site.home.process_link') }} <x-lucide-arrow-right class="hm-icon" width="16" height="16" aria-hidden="true" /></a>
            </div>
            <ol class="hm-index">
                @foreach (__('site.steps') as $i => $step)
                    <li class="hm-index__row">
                        <span class="hm-index__num">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <h3 class="hm-index__title">{{ $step['title'] }}</h3>
                        <p class="hm-index__body">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="hm-section" aria-labelledby="panel-baslik">
        <div class="hm-container hm-split">
            <div class="grid gap-5">
                <p class="hm-eyebrow">{{ __('site.home.panel_eyebrow') }}</p>
                <h2 id="panel-baslik" class="hm-h1">{{ __('site.home.panel_title') }}</h2>
                <dl class="hm-defs">
                    @foreach (__('site.home.panel_points') as $point)
                        <div>
                            <dt>{{ $point['title'] }}</dt>
                            <dd>{{ $point['body'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            <x-site.screenshot name="kazanclar" :alt="__('site.home.panel_caption')" :caption="__('site.home.panel_caption')" sizes="(min-width: 1024px) 760px, 100vw" />
        </div>
    </section>

    @php($hasPlans = collect($plans)->flatten()->isNotEmpty())
    <section class="hm-section" aria-labelledby="plan-baslik">
        <div class="hm-container">
            <div class="hm-section__head hm-section__head--split">
                <div class="grid gap-3">
                    <p class="hm-eyebrow">{{ __('site.home.plans_eyebrow') }}</p>
                    <h2 id="plan-baslik" class="hm-display-l m-0">{{ __('site.home.plans_title') }}</h2>
                </div>
                <a href="{{ route('pricing') }}" class="hm-section__more">{{ __('site.home.plans_link') }} <x-lucide-arrow-right class="hm-icon" width="16" height="16" aria-hidden="true" /></a>
            </div>
            @if ($hasPlans)
                <div class="grid gap-7 lg:grid-cols-2">
                    @foreach (['artist' => __('site.pricing.artist'), 'label' => __('site.pricing.label')] as $audience => $caption)
                        @if ($plans[$audience]->isNotEmpty())
                            @include('site.partials.plan-table', ['plans' => $plans[$audience], 'caption' => $caption, 'compact' => true])
                        @endif
                    @endforeach
                </div>
            @else
                <p class="hm-lead m-0">{{ __('site.pricing.empty') }}</p>
            @endif
        </div>
    </section>

    @if ($platforms->isNotEmpty())
        <section class="hm-section" aria-labelledby="platform-baslik">
            <div class="hm-container">
                <div class="hm-section__head hm-section__head--split">
                    <div class="grid gap-3">
                        <p class="hm-eyebrow">{{ __('site.home.platforms_eyebrow') }}</p>
                        <h2 id="platform-baslik" class="hm-display-l m-0">{{ __('site.home.platforms_title') }}</h2>
                    </div>
                    <a href="{{ route('platforms') }}" class="hm-section__more">{{ __('site.home.platforms_link') }} <x-lucide-arrow-right class="hm-icon" width="16" height="16" aria-hidden="true" /></a>
                </div>
                <p class="hm-inline-names">{{ $platforms->take(14)->implode(' · ') }}@if ($platforms->count() > 14) · …@endif</p>
            </div>
        </section>
    @endif

    @if ($posts->isNotEmpty())
        <section class="hm-section" aria-labelledby="rehber-baslik">
            <div class="hm-container">
                <div class="hm-section__head hm-section__head--split">
                    <div class="grid gap-3">
                        <p class="hm-eyebrow">{{ __('site.blog.eyebrow') }}</p>
                        <h2 id="rehber-baslik" class="hm-display-l m-0">{{ __('site.blog.lead') }}</h2>
                    </div>
                    <a href="{{ route('blog.index') }}" class="hm-section__more">{{ __('site.blog.back') }} <x-lucide-arrow-right class="hm-icon" width="16" height="16" aria-hidden="true" /></a>
                </div>
                @include('site.partials.post-list', ['posts' => $posts])
            </div>
        </section>
    @endif

    @if ($faqs->isNotEmpty())
        <section class="hm-section" aria-labelledby="sss-baslik">
            <div class="hm-container">
                <div class="hm-section__head hm-section__head--split">
                    <div class="grid gap-3">
                        <p class="hm-eyebrow">{{ __('site.home.faq_eyebrow') }}</p>
                        <h2 id="sss-baslik" class="hm-display-l m-0">{{ __('site.home.faq_title') }}</h2>
                    </div>
                    <a href="{{ route('faq') }}" class="hm-section__more">{{ __('site.home.faq_link') }} <x-lucide-arrow-right class="hm-icon" width="16" height="16" aria-hidden="true" /></a>
                </div>
                @include('site.partials.faq-list', ['faqs' => $faqs])
            </div>
        </section>
    @endif

    <section class="hm-container hm-closing" aria-labelledby="kapanis-baslik">
        <h2 id="kapanis-baslik" class="hm-display-l m-0">{{ __('site.home.closing_title') }}</h2>
        <p class="hm-lead m-0">{{ __('site.home.closing_body') }}</p>
        <div><x-ui.button :href="route('register')" variant="primary" icon-right="arrow-right">{{ __('site.home.cta_primary') }}</x-ui.button></div>
    </section>
</x-layouts.public>
