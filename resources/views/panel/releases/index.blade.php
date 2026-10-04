<x-layouts.panel :title="__('release.list.title')">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="hm-page-head">
            <h1 class="hm-h1">{{ __('release.list.title') }}</h1>
        </div>
        @if ($releases->isNotEmpty())
            <form method="POST" action="{{ route('panel.releases.store') }}">
                @csrf
                <x-ui.button type="submit" variant="primary" icon="plus">{{ __('release.list.create') }}</x-ui.button>
            </form>
        @endif
    </div>

    @if ($releases->isEmpty())
        <x-ui.empty-state :title="__('release.list.empty_title')" icon="disc">
            {{ __('release.list.empty_body') }}
            <x-slot:action>
                <form method="POST" action="{{ route('panel.releases.store') }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary" icon="plus">{{ __('release.list.create') }}</x-ui.button>
                </form>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <div class="overflow-hidden rounded-md border border-line border-b-0">
            @foreach ($releases as $release)
                @include('panel.releases.partials.row', ['release' => $release])
            @endforeach
        </div>
        {{ $releases->links() }}
    @endif
</x-layouts.panel>
