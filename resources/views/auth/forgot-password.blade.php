<x-layouts.auth :title="__('auth.forgot.title')">
    <div class="hm-page-head">
        <h1 class="hm-h1">{{ __('auth.forgot.title') }}</h1>
        <p class="hm-muted m-0">{{ __('auth.forgot.lead') }}</p>
    </div>

    @if (session('status'))
        <x-ui.alert tone="success" :title="session('status')" />
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="grid gap-4" novalidate>
        @csrf
        <x-ui.field name="email" type="email" :label="__('auth.fields.email')" autocomplete="email" required autofocus />
        <x-ui.turnstile />
        <x-ui.button type="submit" variant="primary">{{ __('auth.forgot.submit') }}</x-ui.button>
    </form>

    <p class="m-0"><a href="{{ route('login') }}" class="hm-link">{{ __('auth.forgot.back') }}</a></p>
</x-layouts.auth>
