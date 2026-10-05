@use('App\Support\Format')

<x-layouts.panel :title="__('finance.withdrawals_page.title')">
    <div class="hm-page-head">
        <h1 class="hm-h1">{{ __('finance.withdrawals_page.title') }}</h1>
        <p class="hm-muted m-0">{{ __('finance.withdrawals_page.lead') }}</p>
    </div>

    <section class="hm-balance" aria-label="{{ __('finance.withdrawals_page.available') }}">
        <div class="hm-balance__main">
            <p class="hm-eyebrow m-0">{{ __('finance.withdrawals_page.available') }}</p>
            <p class="hm-balance__figure">{{ $money->usd($balances->available) }}</p>
            @if ($money->approximate())
                <p class="hm-balance__note">{{ $money->format($balances->available) }} · {{ __('finance.approx') }}</p>
            @endif
            <p class="hm-balance__note">{{ __('finance.withdrawals_page.minimum', ['amount' => Format::money($minimum)]) }}</p>
        </div>
        <div class="hm-balance__list">
            <h2 class="hm-h3 mt-3 mb-1">{{ __('finance.withdrawals_page.requirements') }}</h2>
            <ul class="hm-checklist">
                @foreach ([
                    ['done' => $requirements['profile'], 'label' => __('finance.withdrawals_page.req_profile'), 'url' => route('panel.finance.profile')],
                    ['done' => $requirements['payout'], 'label' => __('finance.withdrawals_page.req_payout'), 'url' => route('panel.finance.payout')],
                    ['done' => $requirements['tax_form'], 'label' => __('finance.withdrawals_page.req_tax', ['type' => $taxFormType->label()]), 'url' => route('panel.tax-form.create')],
                ] as $req)
                    <li class="hm-checklist__item">
                        @if ($req['done'])
                            <x-lucide-circle-check class="hm-icon hm-checklist__done" width="20" height="20" aria-hidden="true" />
                        @else
                            <x-lucide-circle-dashed class="hm-icon hm-checklist__missing" width="20" height="20" aria-hidden="true" />
                        @endif
                        <span class="hm-checklist__label">{{ $req['label'] }} <span class="hm-sr">({{ $req['done'] ? __('finance.withdrawals_page.req_done') : __('finance.withdrawals_page.req_missing') }})</span></span>
                        <a href="{{ $req['url'] }}" class="hm-link text-sm">{{ $req['done'] ? __('finance.withdrawals_page.req_edit') : __('finance.withdrawals_page.req_complete') }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    @if ($balances->blocked->isPositive())
        <x-ui.alert tone="info" :title="__('finance.withdrawals_page.blocked_notice', ['amount' => Format::money($balances->blocked)])">
            <x-slot:action>
                <x-ui.button :href="route('panel.plans.index')" size="s" variant="secondary">{{ __('finance.earnings.see_plans') }}</x-ui.button>
            </x-slot:action>
        </x-ui.alert>
    @endif

    <section class="hm-card" aria-labelledby="yeni-talep">
        <div class="hm-card__head">
            <h2 id="yeni-talep" class="hm-h3">{{ __('finance.withdrawals_page.form_title') }}</h2>
        </div>
        <div class="hm-card__body">
            @if ($hasOpen)
                <x-ui.alert tone="info" :title="__('finance.withdrawals_page.open_notice')" />
            @else
                <form method="POST" action="{{ route('panel.withdrawals.store') }}" class="grid gap-4" style="max-width: 420px" novalidate
                    data-fee-estimate data-fee-fixed="{{ $feeFixed }}" data-fee-pct="{{ $feePct }}">
                    @csrf
                    <x-ui.field name="amount" :label="__('finance.withdrawals_page.amount')" inputmode="decimal" autocomplete="off" mono required
                        :help="__('finance.withdrawals_page.amount_help', ['min' => Format::money($minimum)])" data-fee-input />
                    <dl class="hm-kv">
                        <dt>{{ __('finance.withdrawals_page.fee_estimate') }}</dt>
                        <dd class="hm-num" data-fee-output>—</dd>
                        <dt>{{ __('finance.withdrawals_page.net_estimate') }}</dt>
                        <dd class="hm-num" data-net-output>—</dd>
                        @if ($payoutMethod)
                            <dt>{{ __('finance.withdrawals_page.to_account') }}</dt>
                            <dd>{{ $payoutMethod->account_holder }} · {{ $payoutMethod->maskedAccount() }} · {{ $payoutMethod->currency }}</dd>
                        @endif
                    </dl>
                    <p class="hm-field__help m-0">{{ __('finance.withdrawals_page.fee_formula', ['fixed' => Format::money($feeFixed), 'pct' => str_replace('.', ',', $feePct)]) }}</p>
                    <div>
                        <x-ui.button type="submit" variant="primary" :loading-text="__('ui.loading')">{{ __('finance.withdrawals_page.submit') }}</x-ui.button>
                    </div>
                </form>
            @endif
        </div>
    </section>

    <section class="hm-card overflow-hidden" aria-labelledby="talepler">
        <div class="hm-card__head">
            <h2 id="talepler" class="hm-h3">{{ __('finance.withdrawals_page.history') }}</h2>
        </div>
        @if ($history->isEmpty())
            <p class="hm-card__body hm-muted m-0">{{ __('finance.withdrawals_page.empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="hm-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('finance.withdrawals_page.col_date') }}</th>
                            <th scope="col" class="text-right">{{ __('finance.withdrawals_page.col_amount') }}</th>
                            <th scope="col" class="text-right">{{ __('finance.withdrawals_page.col_fee') }}</th>
                            <th scope="col" class="text-right">{{ __('finance.withdrawals_page.col_net') }}</th>
                            <th scope="col">{{ __('finance.withdrawals_page.col_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $withdrawal)
                            <tr>
                                <td>{{ Format::shortDate($withdrawal->created_at?->timezone(config('hova.display_timezone'))) }}</td>
                                <td class="text-right hm-num">{{ Format::money((string) $withdrawal->amount_usd) }}</td>
                                <td class="text-right hm-num">
                                    @if ($withdrawal->fee_usd !== null)
                                        {{ Format::money((string) $withdrawal->fee_usd) }}
                                    @else
                                        {{ Format::money((string) $withdrawal->estimated_fee_usd) }} <span class="hm-muted text-xs">{{ __('finance.withdrawals_page.fee_estimated') }}</span>
                                    @endif
                                </td>
                                <td class="text-right hm-num">{{ $withdrawal->net_usd !== null ? Format::money((string) $withdrawal->net_usd) : '—' }}</td>
                                <td>
                                    <span @class(['hm-badge', 'hm-badge--success' => $withdrawal->status->value === 'paid', 'hm-badge--warning' => in_array($withdrawal->status->value, ['pending', 'approved'], true), 'hm-badge--danger' => $withdrawal->status->value === 'rejected'])>{{ $withdrawal->status->label() }}</span>
                                    @if ($withdrawal->reject_reason)
                                        <span class="hm-muted block text-sm">{{ __('finance.withdrawals_page.reject_reason', ['reason' => $withdrawal->reject_reason]) }}</span>
                                    @elseif ($withdrawal->paid_at)
                                        <span class="hm-muted block text-sm">{{ __('finance.withdrawals_page.paid_on', ['date' => Format::shortDate($withdrawal->paid_at->timezone(config('hova.display_timezone')))]) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    {{ $history->links() }}
</x-layouts.panel>
