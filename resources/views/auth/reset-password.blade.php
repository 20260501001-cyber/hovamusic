<x-layouts.auth :title="__('auth.reset.title')">
    <div class="hm-page-head">
        <h1 class="hm-h1">{{ __('auth.reset.title') }}</h1>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="grid gap-4" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-ui.field name="email" type="email" :label="__('auth.fields.email')" :value="$request->email" autocomplete="email" required />
        <x-ui.field name="password" type="password" :label="__('auth.fields.new_password')" autocomplete="new-password" required :help="__('auth.register.password_hint')" />
        <x-ui.field name="password_confirmation" type="password" :label="__('auth.fields.password_confirmation')" autocomplete="new-password" required />
        <x-ui.button type="submit" variant="primary">{{ __('auth.reset.submit') }}</x-ui.button>
    </form>
</x-layouts.auth>
