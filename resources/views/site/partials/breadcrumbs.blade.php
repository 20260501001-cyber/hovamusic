@if (count($seo->breadcrumbs) > 1)
    <nav class="hm-breadcrumbs" aria-label="Sayfa yolu">
        <ol>
            @foreach ($seo->breadcrumbs as $crumb)
                <li>
                    @if ($loop->last)
                        <span aria-current="page">{{ $crumb['name'] }}</span>
                    @else
                        <a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
