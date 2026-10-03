<x-layouts.auth :title="__('auth.verify.title')">
    <div class="hm-page-head">
        <h1 class="hm-h1">{{ __('auth.verify.title') }}</h1>
        <p class="hm-muted m-0">{{ __('auth.verify.lead', ['email' => auth()->user()->email]) }}</p>
    </div>

    @if (session('status') === 'verification-link-sent')
        <x-ui.alert tone="success" :title="__('auth.verify.link_sent')" />
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <x-ui.button type="submit" variant="primary">{{ __('auth.verify.resend') }}</x-ui.button>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-ui.button type="submit" variant="ghost">{{ __('panel.nav.logout') }}</x-ui.button>
    </form>
</x-layouts.auth>
