<nav class="hm-nav">
    @foreach ($items as $item)
        <a href="{{ route($item['route']) }}" class="hm-nav__item" @if (request()->routeIs($item['active'] ?? $item['route'])) aria-current="page" @endif>
            <x-dynamic-component :component="'lucide-'.$item['icon']" class="hm-icon" width="20" height="20" aria-hidden="true" />
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
