{{-- Sözleşme metninin başında, satın alınan hizmetin kendisine ait bilgiler. --}}
@use('App\Support\Format')

<table>
    <tbody>
        <tr><th scope="row">{{ __('plans.facts.buyer') }}</th><td>{{ auth()->user()->name }} · {{ auth()->user()->email }}</td></tr>
        <tr><th scope="row">{{ __('plans.facts.service') }}</th><td>{{ $plan->name }} ({{ $plan->interval->label() }})</td></tr>
        <tr><th scope="row">{{ __('plans.facts.price') }}</th><td>{{ Format::money((string) $plan->price_usd) }} {{ __('plans.per.'.$plan->interval->value) }}</td></tr>
        <tr><th scope="row">{{ __('plans.facts.date') }}</th><td>{{ Format::longDate(now()->timezone(config('hova.display_timezone'))) }}</td></tr>
    </tbody>
</table>
