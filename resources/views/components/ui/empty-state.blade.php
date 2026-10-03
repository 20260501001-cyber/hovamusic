@props([
    'title',
    'icon' => 'disc',
])

<div {{ $attributes->class('hm-empty') }}>
    <span class="hm-empty__icon" aria-hidden="true"><x-dynamic-component :component="'lucide-'.$icon" class="hm-icon" width="24" height="24" /></span>
    <h3 class="hm-empty__title">{{ $title }}</h3>
    @if ($slot->isNotEmpty())<p class="hm-empty__text">{{ $slot }}</p>@endif
    @isset($action)<div class="hm-empty__action">{{ $action }}</div>@endisset
</div>
