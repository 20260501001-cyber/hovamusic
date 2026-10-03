<x-layouts.panel :title="__('panel.nav.dashboard')">
    <div class="hm-page-head">
        <p class="hm-eyebrow">{{ $user->account_type->label() }}</p>
        <h1 class="hm-h1">{{ __('panel.dashboard.greeting', ['name' => $user->name]) }}</h1>
    </div>

    <x-ui.empty-state :title="__('panel.dashboard.empty_title')" icon="disc">
        {{ __('panel.dashboard.empty_body') }}
    </x-ui.empty-state>
</x-layouts.panel>
