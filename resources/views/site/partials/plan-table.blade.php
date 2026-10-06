@use('App\Support\Format')

{{-- Plan tablosu: fiyat, yayın ve sanatçı hakkı, gelir payı. $compact ana sayfa özeti içindir. --}}
<table class="hm-plans">
    <caption>{{ $caption }}</caption>
    <thead>
        <tr>
            <th scope="col">{{ __('site.pricing.col_plan') }}</th>
            <th scope="col">{{ __('site.pricing.col_price') }}</th>
            @unless ($compact ?? false)
                <th scope="col">{{ __('site.pricing.col_releases') }}</th>
                <th scope="col">{{ __('site.pricing.col_artists') }}</th>
            @endunless
            <th scope="col">{{ __('site.pricing.col_share') }}</th>
            @unless ($compact ?? false)<th scope="col"><span class="hm-sr">{{ __('site.pricing.choose') }}</span></th>@endunless
        </tr>
    </thead>
    <tbody>
        @foreach ($plans as $plan)
            <tr>
                <th scope="row">
                    {{ $plan->name }}
                    @if (! ($compact ?? false) && $plan->description)
                        <p class="hm-muted m-0 text-sm font-normal">{{ $plan->description }}</p>
                    @endif
                    @if (! ($compact ?? false) && ! empty($plan->features))
                        <ul class="hm-plans__features">
                            @foreach ($plan->features as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>
                    @endif
                </th>
                <td data-label="{{ __('site.pricing.col_price') }}">
                    <span class="hm-plans__price">{{ Format::money((string) $plan->price_usd) }}</span>
                    <span class="hm-muted">{{ __('plans.per.'.$plan->interval->value) }}</span>
                </td>
                @unless ($compact ?? false)
                    <td data-label="{{ __('site.pricing.col_releases') }}">{{ $plan->release_limit === null ? __('site.pricing.unlimited') : __('site.pricing.per_period', ['count' => $plan->release_limit]) }}</td>
                    <td data-label="{{ __('site.pricing.col_artists') }}">{{ $plan->artist_limit ?? __('site.pricing.unlimited') }}</td>
                @endunless
                <td data-label="{{ __('site.pricing.col_share') }}" class="hm-num">%{{ str_replace('.', ',', rtrim(rtrim((string) $plan->revenue_share_pct, '0'), '.')) }}</td>
                @unless ($compact ?? false)
                    <td><x-ui.button :href="route('register')" variant="secondary" size="s" icon-right="arrow-right">{{ __('site.pricing.choose') }}</x-ui.button></td>
                @endunless
            </tr>
        @endforeach
    </tbody>
</table>
