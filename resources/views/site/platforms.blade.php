<x-layouts.public :seo="$seo">
    <section class="hm-container hm-page-hero" aria-labelledby="sayfa-baslik">
        @include('site.partials.breadcrumbs')
        <p class="hm-eyebrow">{{ __('site.platforms.eyebrow') }}</p>
        <h1 id="sayfa-baslik" class="hm-display-l m-0">{{ __('site.platforms.title') }}</h1>
        <p class="hm-lead m-0">{{ __('site.platforms.lead') }}</p>
    </section>

    <section class="hm-container pb-16" aria-label="{{ __('site.platforms.title') }}">
        @if ($platforms->isEmpty())
            <p class="hm-lead m-0">{{ __('site.platforms.empty') }}</p>
        @else
            <p class="hm-eyebrow mb-4">{{ __('site.platforms.count', ['count' => $platforms->count()]) }}</p>
            <ul class="hm-names">
                @foreach ($platforms as $name)
                    <li>{{ $name }}</li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.public>
