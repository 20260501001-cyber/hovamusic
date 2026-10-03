<x-layouts.auth :title="__('auth.confirm.title')">
    <div class="hm-page-head">
        <h1 class="hm-h1">{{ __('auth.confirm.title') }}</h1>
        <p class="hm-muted m-0">{{ __('auth.confirm.lead') }}</p>
    </div>

    <form method="POST" action="{{ route('password.confirm.store') }}" class="grid gap-4" novalidate>
        @csrf
        <x-ui.field name="password" type="password" :label="__('auth.fields.password')" autocomplete="current-password" required autofocus />
        <x-ui.button type="submit" variant="primary">{{ __('auth.confirm.submit') }}</x-ui.button>
    </form>
</x-layouts.auth>
