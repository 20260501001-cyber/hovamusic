<x-layouts.public :seo="$seo">
    <section class="hm-container hm-page-hero" aria-labelledby="sayfa-baslik">
        @include('site.partials.breadcrumbs')
        <p class="hm-eyebrow">{{ __('site.faq.eyebrow') }}</p>
        <h1 id="sayfa-baslik" class="hm-display-l m-0">{{ __('site.faq.title') }}</h1>
        <p class="hm-lead m-0">{{ __('site.faq.lead') }} <a href="{{ route('contact') }}" class="hm-link">{{ __('site.faq.contact') }}</a></p>
    </section>

    <section class="hm-container pb-16" aria-label="{{ __('site.faq.title') }}">
        <div style="max-width: 920px">
        @forelse ($groups as $group => $faqs)
            @if ($groups->count() > 1)
                <h2 class="hm-eyebrow hm-faq__group">{{ $group }}</h2>
            @endif
            @include('site.partials.faq-list', ['faqs' => $faqs])
        @empty
            <p class="hm-lead m-0">{{ __('site.faq.empty') }}</p>
        @endforelse
        </div>
    </section>
</x-layouts.public>
