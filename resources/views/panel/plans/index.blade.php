@use('App\Support\Format')

<x-layouts.panel :title="__('plans.index.title')">
    <div class="hm-page-head">
        <h1 class="hm-h1">{{ __('plans.index.title') }}</h1>
    </div>

    @if ($subscription)
        @php
            $plan = $subscription->plan;
            $end = $subscription->current_period_end?->timezone(config('hova.display_timezone'));
        @endphp
        <section class="hm-card" aria-labelledby="mevcut-plan">
            <div class="hm-card__head">
                <p class="hm-eyebrow">{{ __('plans.index.current') }}</p>
                <h2 id="mevcut-plan" class="hm-h2">{{ $plan?->name }}</h2>
                <p class="hm-muted m-0">
                    @if ($subscription->status === \App\Enums\SubscriptionStatus::PastDue)
                        {{ __('plans.index.past_due', ['date' => Format::longDate($end)]) }}
                    @elseif ($subscription->willRenew())
                        {{ __('plans.index.renews_on', ['date' => Format::longDate($end)]) }}
                    @else
                        {{ __('plans.index.ends_on', ['date' => Format::longDate($end)]) }}
                    @endif
                </p>
            </div>
            <div class="hm-card__body">
                <dl class="m-0 grid gap-4 sm:grid-cols-3">
                    <div class="hm-kv">
                        <dt>{{ __('plans.index.releases') }}</dt>
                        <dd>
                            @if ($usage['release_limit'] === null)
                                {{ $usage['releases_used'] }} · {{ __('plans.index.unlimited') }}
                            @else
                                {{ __('plans.index.used_of', ['used' => $usage['releases_used'], 'limit' => $usage['release_limit']]) }}
                            @endif
                        </dd>
                    </div>
                    <div class="hm-kv">
                        <dt>{{ __('plans.index.artists') }}</dt>
                        <dd>
                            @if ($usage['artist_limit'] === null)
                                {{ $usage['artists_used'] }} · {{ __('plans.index.unlimited') }}
                            @else
                                {{ __('plans.index.used_of', ['used' => $usage['artists_used'], 'limit' => $usage['artist_limit']]) }}
                            @endif
                        </dd>
                    </div>
                    <div class="hm-kv">
                        <dt>{{ __('plans.index.revenue_share') }}</dt>
                        <dd>%{{ Format::decimal((float) $plan?->revenue_share_pct, 2) }}</dd>
                    </div>
                </dl>
                @if ($portalAvailable)
                    <form method="POST" action="{{ route('panel.plans.portal') }}" class="grid gap-2">
                        @csrf
                        <div><x-ui.button type="submit" icon="external-link">{{ __('plans.index.manage') }}</x-ui.button></div>
                        <p class="hm-field__help m-0">{{ __('plans.index.manage_help') }}</p>
                    </form>
                @endif
            </div>
        </section>
    @else
        <x-ui.alert tone="warning" :title="__('plans.index.none_title')">{{ __('plans.index.none_body') }}</x-ui.alert>
    @endif

    @if (! $subscription)
        <section aria-labelledby="planlar-baslik" class="grid gap-4">
            <h2 id="planlar-baslik" class="hm-h3">{{ __('plans.index.plans_title') }}</h2>
            @if ($plans->isEmpty())
                <p class="hm-muted m-0">{{ __('plans.index.plans_empty') }}</p>
            @else
                <ul class="m-0 grid list-none gap-4 p-0 md:grid-cols-2">
                    @foreach ($plans as $plan)
                        <li class="hm-card">
                            <div class="hm-card__head">
                                <h3 class="hm-h3">{{ $plan->name }}</h3>
                                <p class="m-0">
                                    <span class="text-2xl font-semibold">{{ Format::money((string) $plan->price_usd) }}</span>
                                    <span class="hm-muted">{{ __('plans.per.'.$plan->interval->value) }}</span>
                                </p>
                            </div>
                            <div class="hm-card__body">
                                @if ($plan->description)
                                    <p class="m-0 text-ink-muted">{{ $plan->description }}</p>
                                @endif
                                <ul class="m-0 grid gap-1 pl-5 text-sm">
                                    <li>{{ $plan->release_limit === null ? __('plans.index.unlimited_releases') : __('plans.index.release_limit', ['count' => $plan->release_limit]) }} {{ __('plans.per.'.$plan->interval->value) }}</li>
                                    <li>{{ $plan->artist_limit === null ? __('plans.index.unlimited_artists') : __('plans.index.artist_limit', ['count' => $plan->artist_limit]) }}</li>
                                    <li>{{ __('plans.index.share', ['pct' => Format::decimal((float) $plan->revenue_share_pct, 2)]) }}</li>
                                    @foreach ($plan->features ?? [] as $feature)
                                        <li>{{ $feature }}</li>
                                    @endforeach
                                </ul>
                                <div><x-ui.button :href="route('panel.plans.confirm', $plan)" variant="primary" icon-right="arrow-right">{{ __('plans.index.choose') }}</x-ui.button></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    @if ($orders->isNotEmpty())
        <section class="hm-card overflow-hidden" aria-labelledby="odemeler-baslik">
            <div class="hm-card__head flex flex-wrap items-center justify-between gap-2">
                <h2 id="odemeler-baslik" class="hm-h3">{{ __('plans.index.orders') }}</h2>
                <a class="hm-link text-sm" href="{{ route('panel.plans.orders') }}">{{ __('plans.index.all_orders') }}</a>
            </div>
            @include('panel.plans.partials.orders-table', ['orders' => $orders])
        </section>
    @endif
</x-layouts.panel>
