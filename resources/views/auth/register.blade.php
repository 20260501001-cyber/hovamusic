<x-layouts.auth :title="__('auth.register.title')">
    <div class="hm-page-head">
        <h1 class="hm-h1">{{ __('auth.register.title') }}</h1>
        <p class="hm-muted m-0">{{ __('auth.register.has_account') }} <a href="{{ route('login') }}" class="hm-link">{{ __('auth.register.login_link') }}</a></p>
    </div>

    <form method="POST" action="{{ route('register.store') }}" class="grid gap-5" novalidate>
        @csrf

        <fieldset class="grid gap-2 border-0 p-0 m-0">
            <legend class="hm-label mb-2">{{ __('auth.register.account_type') }}</legend>
            <div class="grid gap-2 sm:grid-cols-2">
                <x-ui.choice name="account_type" value="artist" :title="__('account.types.artist')" :description="__('auth.register.artist_hint')" :checked="true" />
                <x-ui.choice name="account_type" value="label" :title="__('account.types.label')" :description="__('auth.register.label_hint')" />
            </div>
            @error('account_type')
                <p class="hm-field__error"><x-lucide-circle-x class="hm-icon" width="16" height="16" aria-hidden="true" /><span>{{ $message }}</span></p>
            @enderror
        </fieldset>

        <x-ui.field name="name" :label="__('auth.register.name')" autocomplete="name" required :help="__('auth.register.name_hint')" />
        <x-ui.field name="email" type="email" :label="__('auth.fields.email')" autocomplete="email" required />
        <x-ui.field name="password" type="password" :label="__('auth.fields.password')" autocomplete="new-password" required :help="__('auth.register.password_hint')" />
        <x-ui.field name="password_confirmation" type="password" :label="__('auth.fields.password_confirmation')" autocomplete="new-password" required />

        <div class="grid gap-3">
            <x-ui.checkbox name="consents[kvkk-aydinlatma]" id="consent-kvkk">
                {{ __('auth.register.consent_kvkk_before') }} <a href="{{ route('legal.show', 'kvkk-aydinlatma-metni') }}" class="hm-link" target="_blank" rel="noopener">{{ __('auth.register.consent_kvkk_link') }}</a> {{ __('auth.register.consent_kvkk_after') }}
            </x-ui.checkbox>
            <x-ui.checkbox name="consents[uyelik-sozlesmesi]" id="consent-terms">
                <a href="{{ route('legal.show', 'uyelik-sozlesmesi') }}" class="hm-link" target="_blank" rel="noopener">{{ __('auth.register.consent_terms_link') }}</a> {{ __('auth.register.consent_terms_after') }}
            </x-ui.checkbox>
        </div>

        <x-ui.turnstile />
        <x-ui.button type="submit" variant="primary" :loading-text="__('auth.register.submitting')">{{ __('auth.register.submit') }}</x-ui.button>
    </form>
</x-layouts.auth>
