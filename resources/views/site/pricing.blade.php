<x-layouts.public :seo="$seo">
    <section class="hm-container hm-page-hero" aria-labelledby="sayfa-baslik">
        @include('site.partials.breadcrumbs')
        <p class="hm-eyebrow">{{ __('site.pricing.eyebrow') }}</p>
        <h1 id="sayfa-baslik" class="hm-display-l m-0">{{ __('site.pricing.title') }}</h1>
        <p class="hm-lead m-0">{{ __('site.pricing.lead') }}</p>
    </section>

    <div class="hm-container grid gap-9 pb-16">
        @php($any = false)
        @foreach (['artist' => __('site.pricing.artist'), 'label' => __('site.pricing.label')] as $audience => $caption)
            @if ($plans[$audience]->isNotEmpty())
                @php($any = true)
                <section aria-label="{{ $caption }}" class="overflow-x-auto">
                    @include('site.partials.plan-table', ['plans' => $plans[$audience], 'caption' => $caption, 'compact' => false])
                </section>
            @endif
        @endforeach

        @unless ($any)
            <p class="hm-lead m-0">{{ __('site.pricing.empty') }}</p>
        @endunless

        <section class="grid gap-4" aria-labelledby="notlar-baslik">
            <h2 id="notlar-baslik" class="hm-h2">{{ __('site.pricing.notes_title') }}</h2>
            <ul class="hm-index__body grid gap-2 m-0 pl-5">
                @foreach (__('site.pricing.notes') as $note)
                    <li>{{ $note }}</li>
                @endforeach
            </ul>
        </section>
    </div>
</x-layouts.public>
