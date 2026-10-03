<x-layouts.auth :title="__('auth.two_factor.title')">
    <div class="hm-page-head">
        <h1 class="hm-h1">{{ __('auth.two_factor.title') }}</h1>
        <p class="hm-muted m-0">{{ __('auth.two_factor.lead') }}</p>
    </div>

    <form method="POST" action="{{ route('two-factor.login.store') }}" class="grid gap-4" novalidate>
        @csrf
        <x-ui.field name="code" :label="__('auth.two_factor.code')" inputmode="numeric" autocomplete="one-time-code" mono autofocus />
        <x-ui.button type="submit" variant="primary">{{ __('auth.two_factor.submit') }}</x-ui.button>
    </form>

    <details class="grid gap-3">
        <summary class="hm-link cursor-pointer">{{ __('auth.two_factor.use_recovery') }}</summary>
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="grid gap-4 mt-4" novalidate>
            @csrf
            <x-ui.field name="recovery_code" :label="__('auth.two_factor.recovery_code')" autocomplete="one-time-code" mono />
            <x-ui.button type="submit" variant="secondary">{{ __('auth.two_factor.submit') }}</x-ui.button>
        </form>
    </details>
</x-layouts.auth>
