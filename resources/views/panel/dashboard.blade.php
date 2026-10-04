<x-layouts.panel :title="__('panel.nav.dashboard')">
    <div class="hm-page-head">
        <p class="hm-eyebrow">{{ $user->account_type->label() }}</p>
        <h1 class="hm-h1">{{ __('panel.dashboard.greeting', ['name' => $user->name]) }}</h1>
    </div>

    @if ($releases->isEmpty())
        <x-ui.empty-state :title="__('panel.dashboard.empty_title')" icon="disc">
            {{ __('panel.dashboard.empty_body') }}
            <x-slot:action>
                <form method="POST" action="{{ route('panel.releases.store') }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary" icon="plus">{{ __('release.list.create') }}</x-ui.button>
                </form>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <section class="grid gap-3" aria-labelledby="son-yayinlar">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="son-yayinlar" class="hm-h3">{{ __('panel.dashboard.recent') }}</h2>
                <a href="{{ route('panel.releases.index') }}" class="hm-link text-sm">{{ __('panel.dashboard.all_releases') }}</a>
            </div>
            <div class="overflow-hidden rounded-md border border-line border-b-0">
                @foreach ($releases as $release)
                    @include('panel.releases.partials.row', ['release' => $release])
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.panel>
