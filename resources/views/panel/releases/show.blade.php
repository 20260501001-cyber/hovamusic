<x-layouts.panel :title="$release->displayTitle()">
    <div class="flex flex-wrap items-start gap-5">
        @if ($coverUrl)
            <img src="{{ $coverUrl }}" alt="{{ __('release.cover.preview_alt') }}" width="128" height="128" class="size-32 flex-none rounded-sm border border-line object-cover">
        @else
            <span class="grid size-32 flex-none place-items-center rounded-sm border border-line bg-surface-hover text-ink-subtle" aria-hidden="true">
                <x-lucide-disc class="hm-icon" width="32" height="32" />
            </span>
        @endif
        <div class="hm-page-head min-w-0 flex-1">
            <p class="hm-eyebrow" @if ($release->type === \App\Enums\ReleaseType::Single) lang="en" @endif>{{ $release->type?->label() ?? __('release.list.title') }}</p>
            <h1 class="hm-h1">{{ $release->displayTitle() }}</h1>
            <p class="m-0 text-ink-muted">{{ $release->artistLine() ?: '—' }}</p>
            <div><x-ui.status-badge :status="$release->status" /></div>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $release)
                <x-ui.button :href="route('panel.releases.edit', ['release' => $release->ulid, 'step' => $release->wizard_step])" variant="primary" icon="pencil">
                    {{ $release->status === \App\Enums\ReleaseStatus::Draft ? __('release.show.continue') : __('release.show.edit') }}
                </x-ui.button>
            @endcan
            @can('delete', $release)
                <form method="POST" action="{{ route('panel.releases.destroy', $release) }}" data-confirm="{{ __('release.show.delete_confirm') }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="ghost" icon="trash-2">{{ __('release.show.delete') }}</x-ui.button>
                </form>
            @endcan
        </div>
    </div>

    @cannot('update', $release)
        <x-ui.alert tone="info" :title="__('release.show.locked')" />
    @endcannot

    @if ($storeLinks->isNotEmpty())
        <section class="hm-card" aria-labelledby="magaza-baslik">
            <div class="hm-card__head">
                <h2 id="magaza-baslik" class="hm-h3">{{ __('release.show.store_links') }}</h2>
                <p class="hm-muted m-0 text-sm">{{ __('release.show.store_links_help') }}</p>
            </div>
            <div class="hm-card__body">
                <ul class="m-0 flex list-none flex-wrap gap-2 p-0">
                    @foreach ($storeLinks as $link)
                        <li>
                            <x-ui.button :href="$link->url" icon-right="external-link" target="_blank" rel="noopener noreferrer">
                                {{ $link->platform->name }}<span class="hm-sr"> {{ __('release.show.new_tab') }}</span>
                            </x-ui.button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <section class="hm-card" aria-labelledby="bilgiler-baslik">
        <div class="hm-card__head">
            <h2 id="bilgiler-baslik" class="hm-h3">{{ __('release.show.details') }}</h2>
        </div>
        <div class="hm-card__body">
            @include('panel.releases.partials.details', ['release' => $release])
        </div>
    </section>

    <section class="hm-card overflow-hidden" aria-labelledby="parcalar-baslik">
        <div class="hm-card__head">
            <h2 id="parcalar-baslik" class="hm-h3">{{ __('release.show.tracks') }}</h2>
        </div>
        <div class="h-3" aria-hidden="true"></div>
        @if ($release->tracks->isEmpty())
            <div class="hm-card__body"><p class="hm-muted m-0">{{ __('release.tracks.empty_title') }}</p></div>
        @else
            @include('panel.releases.partials.tracks', ['release' => $release])
        @endif
    </section>

    @if ($requestsVisible)
        @include('panel.releases.partials.requests', ['release' => $release, 'canCorrection' => $canCorrection, 'canTakedown' => $canTakedown])
    @endif

    <section class="hm-card" aria-labelledby="gecmis-baslik">
        <div class="hm-card__head">
            <h2 id="gecmis-baslik" class="hm-h3">{{ __('release.show.history') }}</h2>
        </div>
        <div class="hm-card__body">
            @if ($release->statusLogs->isEmpty())
                <p class="hm-muted m-0">{{ __('release.show.history_empty') }}</p>
            @else
                <ol class="m-0 grid list-none gap-4 p-0">
                    @foreach ($release->statusLogs as $log)
                        <li class="grid gap-1 border-l-2 border-line pl-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.status-badge :status="$log->to_status" />
                                <span class="text-sm text-ink-muted">
                                    @php($at = $log->created_at->timezone(config('hova.display_timezone')))
                                    <time datetime="{{ $log->created_at->toIso8601String() }}">{{ \App\Support\Format::longDate($at) }} {{ $at->format('H:i') }}</time>
                                    · {{ __('release.show.actors.'.$log->actor_type) }}
                                </span>
                            </div>
                            @if ($log->note)
                                <p class="m-0 text-sm">{{ $log->note }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </section>
</x-layouts.panel>
