<nav class="hm-nav">
    @foreach ($items as $item)
        <a href="{{ route($item['route']) }}" class="hm-nav__item" @if (request()->routeIs($item['active'] ?? $item['route'])) aria-current="page" @endif>
            <x-dynamic-component :component="'lucide-'.$item['icon']" class="hm-icon" width="20" height="20" aria-hidden="true" />
            <span>{{ $item['label'] }}</span>
            @if (! empty($item['badge']))
                <span class="hm-badge hm-badge--accent ml-auto" aria-hidden="true">{{ $item['badge'] }}</span>
                <span class="hm-sr">({{ $item['badge_label'] }})</span>
            @endif
        </a>
    @endforeach
</nav>
