<x-layouts.auth :title="__('auth.login.title')">
    <div class="hm-page-head">
        <h1 class="hm-h1">{{ __('auth.login.title') }}</h1>
        <p class="hm-muted m-0">{{ __('auth.login.no_account') }} <a href="{{ route('register') }}" class="hm-link">{{ __('auth.login.register_link') }}</a></p>
    </div>

    @if (session('status'))
        <x-ui.alert tone="success" :title="session('status')" />
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="grid gap-4" novalidate>
        @csrf
        <x-ui.field name="email" type="email" :label="__('auth.fields.email')" autocomplete="email" required autofocus />
        <x-ui.field name="password" type="password" :label="__('auth.fields.password')" autocomplete="current-password" required />
        <div class="flex items-center justify-between gap-4">
            <x-ui.checkbox name="remember">{{ __('auth.login.remember') }}</x-ui.checkbox>
            <a href="{{ route('password.request') }}" class="hm-link text-sm">{{ __('auth.login.forgot') }}</a>
        </div>
        <x-ui.turnstile />
        <x-ui.button type="submit" variant="primary" :loading-text="__('auth.login.submitting')">{{ __('auth.login.submit') }}</x-ui.button>
    </form>
</x-layouts.auth>
