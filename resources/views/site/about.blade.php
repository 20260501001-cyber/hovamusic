<x-layouts.public :seo="$seo">
    <section class="hm-container hm-page-hero" aria-labelledby="sayfa-baslik">
        @include('site.partials.breadcrumbs')
        <p class="hm-eyebrow">{{ __('site.about.eyebrow') }}</p>
        <h1 id="sayfa-baslik" class="hm-display-l m-0">{{ __('site.about.title') }}</h1>
        <p class="hm-lead m-0">{{ __('site.about.lead') }}</p>
    </section>

    <section class="hm-section" aria-labelledby="hikaye-baslik">
        <div class="hm-container hm-split">
            <h2 id="hikaye-baslik" class="hm-h1">{{ __('site.about.story_title') }}</h2>
            <div class="hm-prose"><p>{{ __('site.about.story') }}</p></div>
        </div>
    </section>

    <section class="hm-section" aria-labelledby="ilke-baslik">
        <div class="hm-container">
            <div class="hm-section__head">
                <h2 id="ilke-baslik" class="hm-display-l m-0">{{ __('site.about.principles_title') }}</h2>
            </div>
            <ol class="hm-index">
                @foreach (__('site.about.principles') as $i => $item)
                    <li class="hm-index__row">
                        <span class="hm-index__num">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <h3 class="hm-index__title">{{ $item['title'] }}</h3>
                        <p class="hm-index__body">{{ $item['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="hm-section" aria-labelledby="sirket-baslik">
        <div class="hm-container hm-split">
            <h2 id="sirket-baslik" class="hm-h1">{{ __('site.about.company_title') }}</h2>
            <div class="grid gap-4">
                <p class="hm-lead m-0">{{ __('site.about.company') }}</p>
                <div><x-ui.button :href="route('contact')" variant="secondary" icon-right="arrow-right">{{ __('site.nav.contact') }}</x-ui.button></div>
            </div>
        </div>
    </section>
</x-layouts.public>
