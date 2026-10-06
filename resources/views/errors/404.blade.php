@php
    $seo = app(\App\Support\Seo\SeoFactory::class)->make(__('errors.404.title'));
    $seo->noindex = true;
    $seo->breadcrumbs = [];
@endphp

<x-layouts.public :seo="$seo">
    <section class="hm-container hm-error">
        <p class="hm-error__code">HATA 404</p>
        <h1 class="hm-display-l m-0">{{ __('errors.404.title') }}</h1>
        <p class="hm-lead m-0">{{ __('errors.404.body') }}</p>
        <div class="grid gap-3">
            <p class="hm-eyebrow">{{ __('site.not_found.suggestions') }}</p>
            <div class="hm-chips">
                <a class="hm-chip" href="{{ route('home') }}">{{ __('seo.breadcrumb_home') }}</a>
                <a class="hm-chip" href="{{ route('pricing') }}">{{ __('site.nav.pricing') }}</a>
                <a class="hm-chip" href="{{ route('how') }}">{{ __('site.nav.how') }}</a>
                <a class="hm-chip" href="{{ route('blog.index') }}">{{ __('site.nav.blog') }}</a>
                <a class="hm-chip" href="{{ route('faq') }}">{{ __('site.nav.faq') }}</a>
                <a class="hm-chip" href="{{ route('contact') }}">{{ __('site.nav.contact') }}</a>
            </div>
        </div>
    </section>
</x-layouts.public>
