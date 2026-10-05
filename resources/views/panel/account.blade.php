<x-layouts.panel :title="__('panel.nav.account')">
    <div class="hm-page-head">
        <h1 class="hm-h1">{{ __('panel.nav.account') }}</h1>
        <p class="hm-muted m-0">{{ $user->account_type->label() }} · <span class="hm-code">{{ $user->email }}</span></p>
    </div>

    <section class="hm-card" aria-labelledby="profil-baslik">
        <div class="hm-card__head">
            <h2 id="profil-baslik" class="hm-h3">{{ __('panel.account.profile') }}</h2>
            <p class="hm-muted m-0 text-sm">{{ __('panel.account.profile_hint') }}</p>
        </div>
        <form method="POST" action="{{ route('user-profile-information.update') }}" class="hm-card__body" novalidate>
            @csrf
            @method('PUT')
            <x-ui.field name="name" :label="__('auth.register.name')" :value="$user->name" bag="updateProfileInformation" autocomplete="name" required />
            <x-ui.field name="email" type="email" :label="__('auth.fields.email')" :value="$user->email" bag="updateProfileInformation" autocomplete="email" required :help="__('panel.account.email_change_hint')" />
            <div><x-ui.button type="submit" variant="primary">{{ __('ui.save') }}</x-ui.button></div>
        </form>
    </section>

    <section class="hm-card" aria-labelledby="sifre-baslik">
        <div class="hm-card__head">
            <h2 id="sifre-baslik" class="hm-h3">{{ __('panel.account.password') }}</h2>
        </div>
        <form method="POST" action="{{ route('user-password.update') }}" class="hm-card__body" novalidate>
            @csrf
            @method('PUT')
            <x-ui.field name="current_password" type="password" :label="__('auth.fields.current_password')" bag="updatePassword" autocomplete="current-password" required />
            <x-ui.field name="password" type="password" :label="__('auth.fields.new_password')" bag="updatePassword" autocomplete="new-password" required :help="__('auth.register.password_hint')" />
            <x-ui.field name="password_confirmation" type="password" :label="__('auth.fields.password_confirmation')" bag="updatePassword" autocomplete="new-password" required />
            <div><x-ui.button type="submit" variant="primary">{{ __('panel.account.password_submit') }}</x-ui.button></div>
        </form>
    </section>

    <section class="hm-card" aria-labelledby="iki-adimli-baslik">
        <div class="hm-card__head">
            <h2 id="iki-adimli-baslik" class="hm-h3">{{ __('panel.account.two_factor') }}</h2>
            <p class="hm-muted m-0 text-sm">{{ __('panel.account.two_factor_hint') }}</p>
        </div>
        <div class="hm-card__body">
            @if ($user->hasTwoFactorEnabled())
                <x-ui.alert tone="success" :title="__('panel.account.two_factor_on')" />

                @if ($showRecoveryCodes)
                    <div class="grid gap-2">
                        <p class="m-0 text-sm">{{ __('panel.account.recovery_codes_hint') }}</p>
                        <div class="hm-recovery">
                            @foreach ($user->recoveryCodes() as $code)
                                <span>{{ $code }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" icon="refresh-cw">{{ __('panel.account.recovery_codes_regenerate') }}</x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('two-factor.disable') }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="danger">{{ __('panel.account.two_factor_disable') }}</x-ui.button>
                    </form>
                </div>
            @elseif ($twoFactorPending)
                <ol class="grid gap-4 m-0 pl-5">
                    <li>{{ __('panel.account.two_factor_step_scan') }}</li>
                    <li>{{ __('panel.account.two_factor_step_code') }}</li>
                </ol>
                <div class="flex flex-wrap items-start gap-6">
                    <div class="hm-qr">{!! $user->twoFactorQrCodeSvg() !!}</div>
                    <dl class="hm-kv">
                        <dt>{{ __('panel.account.two_factor_setup_key') }}</dt>
                        <dd class="hm-code">{{ decrypt($user->two_factor_secret) }}</dd>
                    </dl>
                </div>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="grid gap-4" style="max-width: 320px" novalidate>
                    @csrf
                    <x-ui.field name="code" :label="__('auth.two_factor.code')" bag="confirmTwoFactorAuthentication" inputmode="numeric" autocomplete="one-time-code" mono required />
                    <div class="flex flex-wrap gap-3">
                        <x-ui.button type="submit" variant="primary">{{ __('panel.account.two_factor_confirm') }}</x-ui.button>
                    </div>
                </form>
                <form method="POST" action="{{ route('two-factor.disable') }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
                </form>
            @else
                <form method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary" icon="shield-check">{{ __('panel.account.two_factor_enable') }}</x-ui.button>
                </form>
            @endif
        </div>
    </section>

    <section class="hm-card" aria-labelledby="tercihler-baslik">
        <div class="hm-card__head">
            <h2 id="tercihler-baslik" class="hm-h3">{{ __('panel.account.preferences') }}</h2>
        </div>
        <form method="POST" action="{{ route('panel.account.preferences') }}" class="hm-card__body" novalidate>
            @csrf
            @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select name="theme" :label="__('panel.account.theme')" :selected="$user->theme->value"
                    :options="collect($themes)->mapWithKeys(fn ($t) => [$t->value => __('panel.account.themes.'.$t->value)])->all()" />
                <x-ui.select name="display_currency" :label="__('panel.account.currency')" :selected="$user->display_currency->value"
                    :options="collect($currencies)->mapWithKeys(fn ($c) => [$c->value => $c->value])->all()"
                    :help="__('panel.account.currency_hint')" />
            </div>
            <div><x-ui.button type="submit" variant="primary">{{ __('ui.save') }}</x-ui.button></div>
        </form>
    </section>

    @include('panel.partials.privacy', ['user' => $user, 'dataRequests' => $dataRequests])
</x-layouts.panel>
