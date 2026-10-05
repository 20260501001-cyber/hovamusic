<x-layouts.public :title="$document->title">
    <article class="hm-container grid gap-6 py-12" style="max-width: calc(var(--text-max) + 64px)">
        <p class="hm-eyebrow">{{ __('site.legal.eyebrow') }}</p>
        <h1 class="hm-display-l m-0">{{ $document->title }}</h1>
        @if ($version)
            <p class="m-0 text-sm text-ink-muted">
                {{ __('site.legal.version', ['version' => $version->version, 'date' => \App\Support\Format::longDate($version->published_at?->timezone(config('hova.display_timezone')))]) }}
            </p>
            <div class="hm-prose">{!! $version->html() !!}</div>
        @else
            <x-ui.alert tone="warning" :title="__('site.legal.pending_title')">{{ __('site.legal.pending_body') }}</x-ui.alert>
        @endif
    </article>
</x-layouts.public>
