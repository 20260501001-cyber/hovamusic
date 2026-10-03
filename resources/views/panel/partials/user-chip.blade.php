<div class="hm-user-chip">
    <span class="hm-user-chip__name">{{ $user->name }}</span>
    <span class="hm-user-chip__mail">{{ $user->email }}</span>
    <form method="POST" action="{{ route('logout') }}" class="mt-2">
        @csrf
        <x-ui.button type="submit" variant="ghost" size="s" icon="log-out">{{ __('panel.nav.logout') }}</x-ui.button>
    </form>
</div>
