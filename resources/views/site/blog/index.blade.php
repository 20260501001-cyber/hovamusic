<x-layouts.public :seo="$seo">
    <section class="hm-container hm-page-hero" aria-labelledby="sayfa-baslik">
        @include('site.partials.breadcrumbs')
        <p class="hm-eyebrow">{{ __('site.blog.eyebrow') }}</p>
        <h1 id="sayfa-baslik" class="hm-display-l m-0">{{ $current?->name ?? __('site.blog.title') }}</h1>
        <p class="hm-lead m-0">{{ $current?->description ?: __('site.blog.lead') }}</p>
        @if ($categories->isNotEmpty())
            <nav class="hm-chips" aria-label="{{ __('site.blog.categories') }}">
                <a href="{{ route('blog.index') }}" class="hm-chip" @if ($current === null) aria-current="page" @endif>{{ __('site.blog.all') }}</a>
                @foreach ($categories as $category)
                    <a href="{{ route('blog.category', $category) }}" class="hm-chip" @if ($current?->is($category)) aria-current="page" @endif>{{ $category->name }}</a>
                @endforeach
            </nav>
        @endif
    </section>

    <section class="hm-container pb-16" aria-label="{{ __('site.blog.title') }}">
        @if ($posts->isEmpty())
            <p class="hm-lead m-0">{{ __('site.blog.empty') }}</p>
        @else
            @include('site.partials.post-list', ['posts' => $posts])
            <div class="mt-8">{{ $posts->links() }}</div>
        @endif
    </section>
</x-layouts.public>
