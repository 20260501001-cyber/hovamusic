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
                <x-ui.field name="name" :label="__('artist.name')" :help="__('artist.name_help')" maxlength="150" autocomplete="off" wire:model.live.debounce.500ms="name" />

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="hm-store" role="group" aria-labelledby="magaza-spotify">
                        <p id="magaza-spotify" class="hm-store__title">{{ __('artist.spotify') }}</p>
                        @unless ($createNewSpotify)
                            <x-artist.store-pick
                                store="spotify"
                                :selected="$spotify"
                                :results="$spotifyResults"
                                :message="$spotifyMessage"
                                :by-link="$spotifyByLink"
                                link-model="spotifyLink"
                                link-id="spotify-link"
                                select-action="selectSpotify"
                                clear="clearSpotify"
                                to-link="spotifyLinkMode(true)"
                                to-search="spotifyLinkMode(false)"
                                loading-target="name"
                            />
                        @endunless
                        <x-ui.checkbox name="createNewSpotify" id="c-create-new-spotify" :checked="$createNewSpotify" bag="_" wire:model.live="createNewSpotify">
                            {{ __('artist.spotify_create_new') }}
                        </x-ui.checkbox>
                        <x-ui.field-error id="spotify-error" :message="$errors->first('spotify')" />
                    </div>

                    <div class="hm-store" role="group" aria-labelledby="magaza-apple">
                        <p id="magaza-apple" class="hm-store__title">{{ __('artist.apple') }}</p>
                        @unless ($createNewApple)
                            <x-artist.store-pick
                                store="apple"
                                :selected="$apple"
                                :results="$appleResults"
                                :message="$appleMessage"
                                :by-link="$appleByLink"
                                link-model="appleInput"
                                link-id="apple-link"
                                :link-live="false"
                                select-action="selectApple"
                                clear="clearApple"
                                to-link="appleLinkMode(true)"
                                to-search="appleLinkMode(false)"
                                loading-target="name"
                            />
                        @endunless
                        <x-ui.checkbox name="createNewApple" id="c-create-new-apple" :checked="$createNewApple" bag="_" wire:model.live="createNewApple">
                            {{ __('artist.apple_create_new') }}
                        </x-ui.checkbox>
                        <x-ui.field-error id="apple-error" :message="$errors->first('apple')" />
                    </div>
                </div>

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
