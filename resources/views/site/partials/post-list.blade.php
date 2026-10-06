@use('App\Support\Format')

<ul class="hm-posts">
    @foreach ($posts as $post)
        <li class="hm-posts__item">
            <p class="hm-posts__meta m-0">
                <time datetime="{{ $post->published_at?->toDateString() }}">{{ Format::longDate($post->published_at?->timezone(config('hova.display_timezone'))) }}</time>
                @if ($post->category)<br>{{ $post->category->name }}@endif
            </p>
            <div>
                <h3 class="hm-posts__title"><a href="{{ $post->url() }}">{{ $post->title }}</a></h3>
                @if ($post->excerpt)<p class="hm-posts__excerpt">{{ $post->excerpt }}</p>@endif
            </div>
        </li>
    @endforeach
</ul>
