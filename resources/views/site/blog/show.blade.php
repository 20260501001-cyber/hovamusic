@use('App\Support\Format')

<x-layouts.public :seo="$seo">
    <article class="hm-container hm-article">
        <header class="hm-article__head">
            @include('site.partials.breadcrumbs')
            <h1 class="hm-display-l m-0">{{ $post->title }}</h1>
            @if ($post->excerpt)<p class="hm-lead m-0">{{ $post->excerpt }}</p>@endif
            <div class="hm-article__meta">
                <time datetime="{{ $post->published_at?->toIso8601String() }}">{{ Format::longDate($post->published_at?->timezone(config('hova.display_timezone'))) }}</time>
                @if ($post->category)<a href="{{ route('blog.category', $post->category) }}" class="hm-link">{{ $post->category->name }}</a>@endif
                <span>{{ __('site.blog.minutes', ['count' => $post->readingMinutes()]) }}</span>
                @if ($post->author_name)<span>{{ __('site.blog.by', ['name' => $post->author_name]) }}</span>@endif
            </div>
        </header>

        @if ($cover['fallback'])
            <figure class="hm-article__cover">
                <picture>
                    @if ($cover['avif'])
                        <source type="image/avif" srcset="{{ collect($cover['avif'])->map(fn ($url, $w) => $url.' '.$w.'w')->implode(', ') }}" sizes="(min-width: 1200px) 1150px, 100vw">
                    @endif
                    @if ($cover['webp'])
                        <source type="image/webp" srcset="{{ collect($cover['webp'])->map(fn ($url, $w) => $url.' '.$w.'w')->implode(', ') }}" sizes="(min-width: 1200px) 1150px, 100vw">
                    @endif
                    <img src="{{ $cover['webp'][1280] ?? $cover['fallback'] }}" alt="{{ $post->cover_alt ?? '' }}"
                        @if ($post->cover_width) width="{{ $post->cover_width }}" height="{{ $post->cover_height }}" @endif
                        fetchpriority="high" decoding="async">
                </picture>
            </figure>
        @endif

        <div class="hm-prose">{!! $post->html() !!}</div>

        @if ($related->isNotEmpty())
            <section class="grid gap-5 pt-8" aria-labelledby="ilgili-baslik">
                <h2 id="ilgili-baslik" class="hm-h2">{{ __('site.blog.related') }}</h2>
                @include('site.partials.post-list', ['posts' => $related])
            </section>
        @endif

        <div><a href="{{ route('blog.index') }}" class="hm-section__more"><x-lucide-arrow-left class="hm-icon" width="16" height="16" aria-hidden="true" /> {{ __('site.blog.back') }}</a></div>
    </article>
</x-layouts.public>
