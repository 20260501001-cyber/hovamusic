<x-layouts.panel :title="__('plans.orders.title')">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="hm-page-head">
            <h1 class="hm-h1">{{ __('plans.orders.title') }}</h1>
            <p class="hm-muted m-0">{{ __('plans.orders.invoice_help') }}</p>
        </div>
        @if ($portalAvailable)
            <form method="POST" action="{{ route('panel.plans.portal') }}">
                @csrf
                <x-ui.button type="submit" icon="external-link">{{ __('plans.index.manage') }}</x-ui.button>
            </form>
        @endif
    </div>

    @if ($orders->isEmpty())
        <x-ui.empty-state :title="__('plans.orders.empty_title')" icon="receipt">{{ __('plans.orders.empty_body') }}</x-ui.empty-state>
    @else
        <section class="hm-card overflow-hidden">
            @include('panel.plans.partials.orders-table', ['orders' => $orders])
        </section>
        {{ $orders->links() }}
    @endif
</x-layouts.panel>
