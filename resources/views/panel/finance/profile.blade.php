<x-layouts.panel :title="__('finance.profile.title')">
    <div class="hm-page-head">
        <h1 class="hm-h1">{{ __('finance.profile.title') }}</h1>
        <p class="hm-muted m-0">{{ __('finance.profile.lead') }}</p>
    </div>

    @php
        $countryOptions = ['' => '—'] + $countries;
        $individual = __('finance.entity_types.individual');
        $company = __('finance.entity_types.company');
    @endphp

    <section class="hm-card">
        <form method="POST" action="{{ route('panel.finance.profile.update') }}" class="hm-card__body" novalidate>
            @csrf
            @method('PUT')

            <fieldset class="hm-fieldset">
                <legend>{{ __('finance.profile.entity_type') }}</legend>
                <div class="hm-form-grid hm-form-grid--2">
                    <x-ui.choice name="entity_type" value="individual" :title="$individual" :checked="$profile->entity_type->value === 'individual'" />
                    <x-ui.choice name="entity_type" value="company" :title="$company" :checked="$profile->entity_type->value === 'company'" />
                </div>
                <x-ui.field-error id="entity-type-error" :message="$errors->first('entity_type')" />
            </fieldset>

            <div class="hm-form-grid hm-form-grid--2">
                <x-ui.field name="legal_name" :label="__('finance.profile.legal_name')" :value="$profile->legal_name" :help="__('finance.profile.legal_name_help')" autocomplete="name" required />
                <x-ui.field name="company_name" :label="__('finance.profile.company_name')" :value="$profile->company_name" :help="$company" autocomplete="organization" />
                <x-ui.select name="country" :label="__('finance.profile.country')" :options="$countryOptions" :selected="$profile->country" required />
                <x-ui.select name="citizenship" :label="__('finance.profile.citizenship')" :options="$countryOptions" :selected="$profile->citizenship" :help="$individual" />
            </div>

            <x-ui.field name="address_line" :label="__('finance.profile.address_line')" :value="$profile->address_line" autocomplete="street-address" required />

            <div class="hm-form-grid hm-form-grid--2">
                <x-ui.field name="city" :label="__('finance.profile.city')" :value="$profile->city" autocomplete="address-level2" required />
                <x-ui.field name="postal_code" :label="__('finance.profile.postal_code')" :value="$profile->postal_code" autocomplete="postal-code" optional />
                <x-ui.field name="phone" type="tel" :label="__('finance.profile.phone')" :value="$profile->phone" autocomplete="tel" optional />
                <x-ui.field name="date_of_birth" type="date" :label="__('finance.profile.date_of_birth')" :value="$profile->date_of_birth" :help="$individual" autocomplete="bday" />
                <x-ui.field name="tax_id" :label="__('finance.profile.tax_id')" :value="$profile->tax_id" :help="__('finance.profile.tax_id_help')" mono />
                <x-ui.field name="tax_office" :label="__('finance.profile.tax_office')" :value="$profile->tax_office" optional />
            </div>

            <div><x-ui.button type="submit" variant="primary">{{ __('ui.save') }}</x-ui.button></div>
        </form>
    </section>
</x-layouts.panel>
