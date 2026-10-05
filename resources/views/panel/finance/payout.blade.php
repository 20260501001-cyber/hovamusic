<x-layouts.panel :title="__('finance.payout_page.title')">
    <div class="hm-page-head">
        <h1 class="hm-h1">{{ __('finance.payout_page.title') }}</h1>
        <p class="hm-muted m-0">{{ __('finance.payout_page.lead') }}</p>
    </div>

    @if ($method)
        <x-ui.alert tone="info" :title="__('finance.payout_page.current')">
            {{ $method->account_holder }} · {{ $method->maskedAccount() }} · {{ $method->currency }}@if ($method->bank_name) · {{ $method->bank_name }}@endif
        </x-ui.alert>
    @endif

    @php
        $countryOptions = ['' => '—'] + $countries;
    @endphp

    <section class="hm-card">
        <form method="POST" action="{{ route('panel.finance.payout.update') }}" class="hm-card__body" novalidate>
            @csrf
            @method('PUT')

            <x-ui.field name="account_holder" :label="__('finance.payout_page.account_holder')" :value="$method?->account_holder" :help="__('finance.payout_page.account_holder_help')" autocomplete="name" required />

            <div class="hm-form-grid hm-form-grid--2">
                <x-ui.select name="bank_country" :label="__('finance.payout_page.bank_country')" :options="$countryOptions" :selected="$method?->bank_country" required />
                <x-ui.select name="currency" :label="__('finance.payout_page.currency')" :options="$currencies" :selected="$method?->currency ?? 'USD'" required />
            </div>

            <x-ui.field name="iban" :label="__('finance.payout_page.iban')" :help="__('finance.payout_page.iban_help')" mono autocomplete="off" spellcheck="false" />

            <div class="hm-form-grid hm-form-grid--2">
                <x-ui.field name="account_number" :label="__('finance.payout_page.account_number')" :help="__('finance.payout_page.account_number_help')" mono autocomplete="off" optional />
                <x-ui.field name="routing_number" :label="__('finance.payout_page.routing_number')" :help="__('finance.payout_page.routing_number_help')" mono autocomplete="off" optional />
                <x-ui.field name="swift_bic" :label="__('finance.payout_page.swift_bic')" :value="$method?->swift_bic" mono autocomplete="off" optional />
                <x-ui.field name="bank_name" :label="__('finance.payout_page.bank_name')" :value="$method?->bank_name" optional />
            </div>

            <x-ui.field name="current_password" type="password" :label="__('finance.payout_page.current_password')" :help="__('finance.payout_page.current_password_help')" autocomplete="current-password" required />

            <div><x-ui.button type="submit" variant="primary">{{ __('ui.save') }}</x-ui.button></div>
        </form>
    </section>
</x-layouts.panel>
