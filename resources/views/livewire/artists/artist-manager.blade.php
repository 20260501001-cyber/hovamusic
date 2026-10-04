<div class="grid gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="hm-page-head">
            <h1 class="hm-h1">{{ __('artist.title') }}</h1>
            <p class="hm-muted m-0 max-w-2xl">{{ __('artist.lead') }}</p>
        </div>
        @if ($editing === null && $artists->isNotEmpty())
            <x-ui.button variant="primary" icon="user-plus" wire:click="create">{{ __('artist.add') }}</x-ui.button>
        @endif
    </div>

    @if ($notice !== '')
        <x-ui.alert tone="success" :title="$notice" />
    @endif
    @if ($listError !== '')
        <x-ui.alert tone="danger" :title="$listError" />
    @endif

    @if ($editing !== null)
        <section class="hm-card" aria-labelledby="sanatci-form-baslik" wire:key="artist-form-{{ $editing }}">
            <div class="hm-card__head">
                <h2 id="sanatci-form-baslik" class="hm-h3">{{ $editing === 'new' ? __('artist.form_title_new') : __('artist.form_title_edit') }}</h2>
            </div>
            <form class="hm-card__body" wire:submit="save" novalidate>
                <x-ui.field name="name" :label="__('artist.name')" maxlength="150" autocomplete="off" wire:model="name" />

                <fieldset class="hm-field m-0 border-0 p-0">
                    <legend class="hm-label">{{ __('artist.spotify') }}</legend>

                    @if ($spotify)
                        <div class="hm-file hm-file--valid items-center">
                            @if ($spotify['image_url'])
                                <img src="{{ $spotify['image_url'] }}" alt="" width="40" height="40" class="size-10 flex-none rounded-full object-cover" referrerpolicy="no-referrer">
                            @else
                                <x-lucide-circle-check class="hm-file__icon hm-icon" width="20" height="20" aria-hidden="true" />
                            @endif
                            <div class="hm-file__body">
                                <span class="hm-file__name">{{ $spotify['name'] }}</span>
                                <p class="hm-file__meta"><span class="hm-sr">{{ __('artist.spotify_selected') }}: </span><span class="hm-code">{{ $spotify['id'] }}</span></p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ $spotify['url'] }}" target="_blank" rel="noopener noreferrer" class="hm-btn hm-btn--ghost hm-btn--s">
                                    <x-lucide-external-link class="hm-icon" width="16" height="16" aria-hidden="true" /><span>{{ __('artist.spotify_open') }}</span>
                                </a>
                                <x-ui.button size="s" wire:click="clearSpotify">{{ __('artist.spotify_change') }}</x-ui.button>
                            </div>
                        </div>
                    @elseif (! $createNewSpotify)
                        <div class="hm-field">
                            <label for="spotify-query" class="hm-label">{{ __('artist.spotify_search') }}</label>
                            <input id="spotify-query" type="search" class="hm-input" autocomplete="off" maxlength="200"
                                wire:model.live.debounce.400ms="spotifyQuery" aria-describedby="spotify-query-help spotify-status"
                                @error('spotify') aria-invalid="true" @enderror>
                            <p id="spotify-query-help" class="hm-field__help">{{ __('artist.spotify_search_help') }}</p>
                            <p id="spotify-status" class="m-0 flex items-center gap-2 text-sm text-ink-muted" role="status">
                                <x-lucide-loader-circle class="hm-icon hm-spin" width="16" height="16" aria-hidden="true" wire:loading wire:target="spotifyQuery" />
                                <span>{{ $searchMessage }}</span>
                            </p>
                        </div>

                        @if ($results !== [])
                            <ul class="m-0 grid list-none overflow-hidden rounded-md border border-line p-0" aria-label="{{ __('artist.spotify_results') }}">
                                @foreach ($results as $result)
                                    <li class="flex items-center gap-3 border-b border-line bg-surface px-3 py-2 last:border-b-0" wire:key="spotify-{{ $result['id'] }}">
                                        @if ($result['image_url'])
                                            <img src="{{ $result['image_url'] }}" alt="" width="40" height="40" class="size-10 flex-none rounded-full object-cover" loading="lazy" referrerpolicy="no-referrer">
                                        @else
                                            <span class="grid size-10 flex-none place-items-center rounded-full bg-surface-hover text-ink-subtle" aria-hidden="true">
                                                <x-lucide-user-round class="hm-icon" width="18" height="18" />
                                            </span>
                                        @endif
                                        <span class="min-w-0 flex-1 truncate font-medium">{{ $result['name'] }}</span>
                                        <a href="{{ $result['url'] }}" target="_blank" rel="noopener noreferrer" class="hm-btn hm-btn--ghost hm-btn--s">
                                            <x-lucide-external-link class="hm-icon" width="16" height="16" aria-hidden="true" />
                                            <span class="hm-sr">{{ __('artist.spotify_open') }}: {{ $result['name'] }}</span>
                                        </a>
                                        <x-ui.button size="s" wire:click="selectSpotify('{{ $result['id'] }}')">
                                            {{ __('artist.spotify_select') }}<span class="hm-sr">: {{ $result['name'] }}</span>
                                        </x-ui.button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif

                    <x-ui.checkbox name="createNewSpotify" id="c-create-new-spotify" :checked="$createNewSpotify" bag="_" wire:model.live="createNewSpotify">
                        {{ __('artist.spotify_create_new') }}
                    </x-ui.checkbox>
                    <x-ui.field-error id="spotify-error" :message="$errors->first('spotify')" />
                </fieldset>

                <fieldset class="hm-field m-0 border-0 p-0">
                    <legend class="hm-label">{{ __('artist.apple') }}</legend>
                    @unless ($createNewApple)
                        <x-ui.field name="apple" :label="__('artist.apple_id')" :help="__('artist.apple_help')" :mono="true" maxlength="200" autocomplete="off" wire:model="appleInput" />
                    @endunless
                    <x-ui.checkbox name="createNewApple" id="c-create-new-apple" :checked="$createNewApple" bag="_" wire:model.live="createNewApple">
                        {{ __('artist.apple_create_new') }}
                    </x-ui.checkbox>
                    @if ($createNewApple)
                        <x-ui.field-error id="apple-error" :message="$errors->first('apple')" />
                    @endif
                </fieldset>

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('artist.save') }}</x-ui.button>
                    <x-ui.button wire:click="cancel">{{ __('artist.cancel') }}</x-ui.button>
                </div>
            </form>
        </section>
    @endif

    @if ($artists->isEmpty() && $editing === null)
        <x-ui.empty-state :title="__('artist.empty_title')" icon="mic-vocal">
            {{ __('artist.empty_body') }}
            <x-slot:action>
                <x-ui.button variant="primary" icon="user-plus" wire:click="create">{{ __('artist.add') }}</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @elseif ($artists->isNotEmpty())
        <ul class="m-0 grid list-none overflow-hidden rounded-md border border-line p-0">
            @foreach ($artists as $artist)
                <li class="flex flex-wrap items-center gap-3 border-b border-line bg-surface px-4 py-3 last:border-b-0" wire:key="artist-{{ $artist->ulid }}">
                    @if ($artist->spotify_image_url)
                        <img src="{{ $artist->spotify_image_url }}" alt="" width="40" height="40" class="size-10 flex-none rounded-full object-cover" loading="lazy" referrerpolicy="no-referrer">
                    @else
                        <span class="grid size-10 flex-none place-items-center rounded-full bg-surface-hover text-ink-subtle" aria-hidden="true">
                            <x-lucide-user-round class="hm-icon" width="18" height="18" />
                        </span>
                    @endif
                    <div class="grid min-w-0 flex-1 gap-0.5">
                        <span class="truncate font-semibold">{{ $artist->name }}</span>
                        <span class="text-sm text-ink-muted">
                            {{ __('artist.spotify') }}: <span @class(['hm-code' => $artist->spotify_artist_id])>{{ $artist->spotifyLabel() }}</span>
                            · {{ __('artist.apple') }}: <span @class(['hm-code' => $artist->apple_music_id])>{{ $artist->appleLabel() }}</span>
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <x-ui.button size="s" icon="pencil" wire:click="edit('{{ $artist->ulid }}')">
                            {{ __('artist.edit') }}<span class="hm-sr">: {{ $artist->name }}</span>
                        </x-ui.button>
                        <x-ui.button size="s" variant="ghost" icon="trash-2" wire:click="delete('{{ $artist->ulid }}')"
                            wire:confirm="{{ __('artist.delete_confirm', ['name' => $artist->name]) }}">
                            {{ __('artist.delete') }}<span class="hm-sr">: {{ $artist->name }}</span>
                        </x-ui.button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
