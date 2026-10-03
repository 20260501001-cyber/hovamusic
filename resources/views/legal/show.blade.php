<x-layouts.public :title="$title">
    <article class="hm-container py-12 grid gap-6" style="max-width: calc(var(--text-max) + 64px)">
        <p class="hm-eyebrow">{{ __('site.legal.eyebrow') }}</p>
        <h1 class="hm-display-l m-0">{{ $title }}</h1>
        @if ($body)
            <div class="grid gap-4 hm-lead">{{ $body }}</div>
        @else
            <x-ui.alert tone="warning" :title="__('site.legal.pending_title')">{{ __('site.legal.pending_body') }}</x-ui.alert>
        @endif
    </article>
</x-layouts.public>
