@use('App\Support\Format')

<x-layouts.panel :title="__('finance.earnings.title')">
    <div class="hm-page-head">
        <h1 class="hm-h1">{{ __('finance.earnings.title') }}</h1>
        <p class="hm-muted m-0">{{ __('finance.earnings.lead') }}</p>
    </div>

    @if ($missingRate)
        <x-ui.alert tone="info" :title="__('finance.usd_only_note')" />
    @endif

    <section class="hm-balance" aria-label="{{ __('finance.earnings.available') }}">
        <div class="hm-balance__main">
            <p class="hm-eyebrow m-0">{{ __('finance.earnings.available') }}</p>
            <p class="hm-balance__figure">{{ $money->format($balances->available) }}</p>
            @if ($money->approximate())
                <p class="hm-balance__note">{{ $money->usd($balances->available) }} · {{ __('finance.approx') }}</p>
            @endif
            <div class="hm-balance__action">
                <x-ui.button :href="route('panel.withdrawals.index')" variant="primary" icon="banknote">{{ __('finance.earnings.withdraw') }}</x-ui.button>
            </div>
        </div>
        <dl class="hm-balance__list">
            <div class="hm-balance__row">
                <dt>{{ __('finance.earnings.total_balance') }}</dt>
                <dd>{{ $money->format($balances->total()) }}</dd>
            </div>
            @if (! $balances->blocked->isZero())
                <div class="hm-balance__row">
                    <dt>{{ __('finance.earnings.blocked') }}<span class="hm-balance__hint">{{ __('finance.earnings.blocked_help') }}</span></dt>
                    <dd>{{ $money->format($balances->blocked) }}</dd>
                </div>
            @endif
            @if (! $balances->reserved->isZero())
                <div class="hm-balance__row">
                    <dt>{{ __('finance.earnings.reserved') }}</dt>
                    <dd>{{ $money->format($balances->reserved) }}</dd>
                </div>
            @endif
            <div class="hm-balance__row">
                <dt>{{ __('finance.earnings.lifetime') }}</dt>
                <dd>{{ $money->format($lifetime) }}</dd>
            </div>
        </dl>
    </section>

    @if ($balances->blocked->isPositive() && ! $hasActivePlan)
        <x-ui.alert tone="warning" :title="__('finance.earnings.blocked')">
            {{ __('finance.earnings.blocked_help') }}
            <x-slot:action>
                <x-ui.button :href="route('panel.plans.index')" size="s" variant="primary">{{ __('finance.earnings.see_plans') }}</x-ui.button>
            </x-slot:action>
        </x-ui.alert>
    @endif

    @if ($months === [])
        <x-ui.empty-state :title="__('finance.earnings.empty_title')" icon="chart-column">{{ __('finance.earnings.empty_body') }}</x-ui.empty-state>
    @else
        @php
            $monthOptions = collect($months)->mapWithKeys(fn (string $m): array => [$m => \Carbon\CarbonImmutable::createFromFormat('!Y-m', $m)->translatedFormat('F Y')])->all();
        @endphp
        <form method="GET" action="{{ route('panel.earnings.index') }}" class="hm-filter" aria-label="{{ __('finance.earnings.filter.apply') }}">
            <x-ui.select name="from" :label="__('finance.earnings.filter.from')" :options="$monthOptions" :selected="$from->format('Y-m')" />
            <x-ui.select name="to" :label="__('finance.earnings.filter.to')" :options="$monthOptions" :selected="$to->format('Y-m')" />
            <x-ui.button type="submit" variant="secondary">{{ __('finance.earnings.filter.apply') }}</x-ui.button>
            <x-ui.button :href="route('panel.earnings.export', ['from' => $from->format('Y-m'), 'to' => $to->format('Y-m')])" variant="ghost" icon="download">{{ __('finance.earnings.filter.download') }}</x-ui.button>
        </form>

        <section class="hm-card" aria-labelledby="aylik-gelir">
            <div class="hm-card__head">
                <h2 id="aylik-gelir" class="hm-h3">{{ __('finance.earnings.chart.title') }}</h2>
                <p class="hm-muted m-0 text-sm">{{ $from->translatedFormat('F Y') }} – {{ $to->translatedFormat('F Y') }}</p>
            </div>
            <div class="hm-card__body">
                @include('panel.earnings.partials.chart', ['monthly' => $monthly, 'money' => $money, 'from' => $from, 'to' => $to])
            </div>
        </section>

        <section class="hm-card overflow-hidden" aria-labelledby="aylik-tablo">
            <div class="hm-card__head">
                <h2 id="aylik-tablo" class="hm-h3">{{ __('finance.earnings.monthly.title') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="hm-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('finance.earnings.monthly.month') }}</th>
                            <th scope="col" class="text-right">{{ __('finance.earnings.monthly.quantity') }}</th>
                            <th scope="col" class="text-right">{{ __('finance.earnings.monthly.revenue') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($monthly) as $row)
                            <tr>
                                <td>{{ $row['month']->translatedFormat('F Y') }}</td>
                                <td class="text-right hm-num">{{ number_format($row['quantity'], 0, ',', '.') }}</td>
                                <td class="text-right hm-num">{{ $money->format($row['revenue']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            @include('panel.earnings.partials.breakdown', ['id' => 'kirilim-platform', 'title' => __('finance.earnings.breakdown.platform'), 'data' => $platforms, 'money' => $money])
            @include('panel.earnings.partials.breakdown', ['id' => 'kirilim-ulke', 'title' => __('finance.earnings.breakdown.country'), 'data' => $countries, 'money' => $money])
        </div>
        @include('panel.earnings.partials.breakdown', ['id' => 'kirilim-parca', 'title' => __('finance.earnings.breakdown.track'), 'data' => $tracks, 'money' => $money])
    @endif

    <section class="hm-card overflow-hidden" aria-labelledby="bakiye-hareketleri">
        <div class="hm-card__head">
            <h2 id="bakiye-hareketleri" class="hm-h3">{{ __('finance.earnings.history.title') }}</h2>
        </div>
        @if ($entries->isEmpty())
            <p class="hm-card__body hm-muted m-0">{{ __('finance.earnings.history.empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="hm-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('finance.earnings.history.date') }}</th>
                            <th scope="col">{{ __('finance.earnings.history.description') }}</th>
                            <th scope="col">{{ __('finance.earnings.history.bucket') }}</th>
                            <th scope="col" class="text-right">{{ __('finance.earnings.history.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td>{{ Format::shortDate($entry->created_at?->timezone(config('hova.display_timezone'))) }}</td>
                                <td>{{ $entry->type->label() }}@if ($entry->description)<span class="hm-muted block text-sm">{{ $entry->description }}</span>@endif</td>
                                <td>{{ $entry->bucket->label() }}</td>
                                <td class="text-right hm-num">{{ $money->format((string) $entry->amount_usd) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($money->approximate())
        <p class="hm-muted text-sm m-0">{{ __('finance.approx_note', ['currency' => $money->currency, 'date' => $money->rateMonth?->translatedFormat('F Y')]) }}</p>
    @endif
</x-layouts.panel>
