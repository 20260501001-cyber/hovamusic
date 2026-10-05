@use('App\Support\Format')

<div class="overflow-x-auto">
    <table class="hm-table">
        <thead>
            <tr>
                <th scope="col">{{ __('plans.orders.date') }}</th>
                <th scope="col">{{ __('plans.orders.description') }}</th>
                <th scope="col" class="text-right">{{ __('plans.orders.amount') }}</th>
                <th scope="col">{{ __('plans.orders.status') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($orders as $order)
                <tr>
                    <td>{{ Format::shortDate($order->ordered_at?->timezone(config('hova.display_timezone'))) }}</td>
                    <td>
                        {{ $order->product_name ?? $order->plan?->name ?? '—' }}
                        @if ($order->billing_reason)<span class="hm-muted"> · {{ __('plans.billing_reasons.'.$order->billing_reason) }}</span>@endif
                        @if ($order->invoice_number)<span class="hm-code block text-xs">{{ $order->invoice_number }}</span>@endif
                    </td>
                    <td class="text-right tabular-nums">{{ Format::money((string) $order->total, $order->currency) }}</td>
                    <td><span @class(['hm-badge', 'hm-badge--success' => $order->status->value === 'paid', 'hm-badge--warning' => $order->status->value === 'pending', 'hm-badge--hollow' => str_contains($order->status->value, 'refunded')])>{{ $order->status->label() }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
