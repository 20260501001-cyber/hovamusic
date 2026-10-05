@use('App\Support\Format')

<x-layouts.panel :title="__('finance.tax_form_page.title')">
    <div class="hm-page-head">
        <p class="hm-eyebrow">{{ $type->label() }}</p>
        <h1 class="hm-h1">{{ __('finance.tax_form_page.title') }}</h1>
        <p class="hm-muted m-0">{{ __('finance.tax_form_page.lead') }}</p>
    </div>

    @if ($current)
        <x-ui.alert tone="success" :title="__('finance.tax_form_page.current')">
            {{ __('finance.tax_form_page.signed_on', ['date' => Format::shortDate($current->signed_at->timezone(config('hova.display_timezone'))), 'expires' => Format::shortDate($current->expires_at)]) }}
            <x-slot:action>
                <x-ui.button :href="route('panel.tax-form.download', $current)" size="s" variant="secondary" icon="download">{{ __('finance.tax_form_page.download') }}</x-ui.button>
            </x-slot:action>
        </x-ui.alert>
    @endif

    @if (! $profileComplete)
        <x-ui.alert tone="warning" :title="__('finance.tax_form_page.profile_missing')">
            <x-slot:action>
                <x-ui.button :href="route('panel.finance.profile')" size="s" variant="primary">{{ __('finance.profile.title') }}</x-ui.button>
            </x-slot:action>
        </x-ui.alert>
    @else
        @php
            $countryOptions = ['' => '—'] + $countries;
            $entity = $type->value === 'w8bene';
        @endphp

        <section class="hm-card">
            <form method="POST" action="{{ route('panel.tax-form.store') }}" class="hm-card__body" novalidate>
                @csrf

                <fieldset class="hm-fieldset">
                    <legend>{{ __('finance.tax_form_page.section_identity') }}</legend>
                    <div class="hm-form-grid hm-form-grid--2">
                        @if ($entity)
                            <x-ui.field name="organization_name" :label="__('finance.tax_form_page.organization_name')" :value="$prefill['organization_name']" required />
                            <x-ui.select name="incorporation_country" :label="__('finance.tax_form_page.incorporation_country')" :options="$countryOptions" :selected="$prefill['incorporation_country']" required />
                        @else
                            <x-ui.field name="name" :label="__('finance.tax_form_page.name')" :value="$prefill['name']" autocomplete="name" required />
                            <x-ui.select name="citizenship" :label="__('finance.tax_form_page.citizenship')" :options="$countryOptions" :selected="$prefill['citizenship']" required />
                            <x-ui.field name="date_of_birth" type="date" :label="__('finance.tax_form_page.date_of_birth')" :value="$prefill['date_of_birth']" required />
                        @endif
                    </div>
                </fieldset>

                @if ($entity)
                    <fieldset class="hm-fieldset">
                        <legend>{{ __('finance.tax_form_page.section_status') }}</legend>
                        <div class="hm-form-grid hm-form-grid--2">
                            <x-ui.select name="chapter3_status" :label="__('finance.tax_form_page.chapter3_status')" :options="['' => '—'] + $chapter3" selected="corporation" required />
                            <x-ui.select name="chapter4_status" :label="__('finance.tax_form_page.chapter4_status')" :options="['' => '—'] + $chapter4" selected="active_nffe" required />
                            <x-ui.field name="chapter4_other" :label="__('finance.tax_form_page.chapter4_other')" optional />
                            <x-ui.field name="giin" :label="__('finance.tax_form_page.giin')" mono optional />
                        </div>
                    </fieldset>
                @endif

                <fieldset class="hm-fieldset">
                    <legend>{{ __('finance.tax_form_page.section_address') }}</legend>
                    <x-ui.field name="address" :label="__('finance.tax_form_page.address')" :value="$prefill['address']" :help="__('finance.tax_form_page.address_help')" required />
                    <div class="hm-form-grid hm-form-grid--2">
                        <x-ui.field name="city" :label="__('finance.tax_form_page.city')" :value="$prefill['city']" required />
                        <x-ui.select name="country" :label="__('finance.tax_form_page.country')" :options="$countryOptions" :selected="$prefill['country']" required />
                    </div>
                    <x-ui.field name="mailing_address" :label="__('finance.tax_form_page.mailing_address')" optional />
                </fieldset>

                <fieldset class="hm-fieldset">
                    <legend>{{ __('finance.tax_form_page.section_tax') }}</legend>
                    <div class="hm-form-grid hm-form-grid--2">
                        <x-ui.field name="foreign_tin" :label="__('finance.tax_form_page.foreign_tin')" :value="$prefill['foreign_tin']" mono />
                        <x-ui.field name="us_tin" :label="__('finance.tax_form_page.us_tin')" mono optional />
                    </div>
                </fieldset>

                @unless ($entity)
                    <fieldset class="hm-fieldset">
                        <legend>{{ __('finance.tax_form_page.section_treaty') }}</legend>
                        <p class="hm-field__help m-0">{{ __('finance.tax_form_page.treaty_help') }}</p>
                        <div class="hm-form-grid hm-form-grid--2">
                            <x-ui.select name="treaty_country" :label="__('finance.tax_form_page.treaty_country')" :options="$countryOptions" :selected="$prefill['treaty_country']" />
                            <x-ui.field name="treaty_article" :label="__('finance.tax_form_page.treaty_article')" optional />
                            <x-ui.field name="treaty_rate" :label="__('finance.tax_form_page.treaty_rate')" inputmode="decimal" mono optional />
                            <x-ui.field name="treaty_income_type" :label="__('finance.tax_form_page.treaty_income_type')" optional />
                        </div>
                    </fieldset>
                @endunless

                <fieldset class="hm-fieldset">
                    <legend>{{ __('finance.tax_form_page.section_sign') }}</legend>
                    <div class="hm-form-grid hm-form-grid--2">
                        <x-ui.field name="signed_name" :label="__('finance.tax_form_page.signed_name')" autocomplete="off" required />
                        @if ($entity)
                            <x-ui.field name="signer_capacity" :label="__('finance.tax_form_page.signer_capacity')" required />
                        @endif
                    </div>
                    <x-ui.checkbox name="certify" required>{{ __('finance.tax_form_page.certify') }}</x-ui.checkbox>
                    <x-ui.checkbox name="esign" required>{{ __('finance.tax_form_page.esign') }}</x-ui.checkbox>
                </fieldset>

                <div><x-ui.button type="submit" variant="primary" icon="file-pen-line">{{ __('finance.tax_form_page.submit') }}</x-ui.button></div>
            </form>
        </section>
    @endif
</x-layouts.panel>
