<x-layouts.panel :title="__('plans.processing.title')">
    @push('head')
        @unless ($failed || $waitedLong)
            <meta http-equiv="refresh" content="5">
        @endunless
    @endpush

    <div class="hm-page-head">
        <h1 class="hm-h1">{{ __('plans.processing.title') }}</h1>
        <p class="hm-muted m-0">{{ $checkout->plan->name }}</p>
    </div>

    @if ($failed)
        <x-ui.alert tone="danger" :title="__('plans.processing.failed')" />
    @elseif ($waitedLong)
        <x-ui.alert tone="warning" :title="__('plans.processing.slow')" />
    @else
        <div class="hm-card">
            <div class="hm-card__body flex items-center gap-3" role="status">
                <x-lucide-loader-circle class="hm-icon hm-spin" width="20" height="20" aria-hidden="true" />
                <p class="m-0">{{ __('plans.processing.body') }}</p>
            </div>
        </div>
    @endif

    <div><x-ui.button :href="route('panel.plans.index')" variant="ghost" icon="arrow-left">{{ __('plans.processing.back') }}</x-ui.button></div>
</x-layouts.panel>
