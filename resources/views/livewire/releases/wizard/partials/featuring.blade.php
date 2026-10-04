{{--
    Featuring düzenleyici. Değişkenler: $model (Livewire yolu), $entries, $profiles,
    $exclude (seçilemeyecek profil ULID'leri), $idPrefix.
--}}
<fieldset class="hm-field m-0 border-0 p-0">
    <legend class="hm-label">{{ __('release.info.featuring') }}<span class="hm-label__optional"> · {{ __('ui.optional') }}</span></legend>
    <p class="hm-field__help mt-0">{{ __('release.info.featuring_help') }}</p>

    @if ($entries !== [])
        <ul class="m-0 grid list-none gap-3 p-0">
            @foreach ($entries as $i => $entry)
                <li class="grid gap-3 rounded-md border border-line bg-surface p-3" wire:key="{{ $idPrefix }}-feat-{{ $i }}">
                    @if (($entry['kind'] ?? '') === 'profile')
                        <div class="flex items-end gap-2">
                            <div class="hm-field min-w-0 flex-1">
                                <label for="{{ $idPrefix }}-feat-{{ $i }}-artist" class="hm-label">{{ __('release.info.select_artist') }}</label>
                                <select id="{{ $idPrefix }}-feat-{{ $i }}-artist" class="hm-select" wire:model.live="{{ $model }}.{{ $i }}.artist"
                                    @error("featuring.$i.artist") aria-invalid="true" aria-describedby="{{ $idPrefix }}-feat-{{ $i }}-artist-error" @enderror>
                                    <option value="">{{ __('release.info.select_artist') }}</option>
                                    @foreach ($profiles as $profile)
                                        @continue(in_array($profile->ulid, $exclude, true) && $profile->ulid !== ($entry['artist'] ?? null))
                                        <option value="{{ $profile->ulid }}">{{ $profile->name }}</option>
                                    @endforeach
                                </select>
                                <x-ui.field-error :id="$idPrefix.'-feat-'.$i.'-artist-error'" :message="$errors->first('featuring.'.$i.'.artist')" />
                            </div>
                            <x-ui.button size="s" variant="ghost" icon="x" wire:click="removeFeaturing({{ $i }})">
                                <span class="hm-sr">{{ __('release.info.remove') }}</span>
                            </x-ui.button>
                        </div>
                    @else
                        <div class="flex items-start gap-2">
                            <div class="grid min-w-0 flex-1 gap-3 sm:grid-cols-3">
                                <div class="hm-field">
                                    <label for="{{ $idPrefix }}-feat-{{ $i }}-name" class="hm-label">{{ __('release.info.guest_name') }}</label>
                                    <input id="{{ $idPrefix }}-feat-{{ $i }}-name" type="text" class="hm-input" maxlength="150" wire:model.live.blur="{{ $model }}.{{ $i }}.name">
                                </div>
                                <div class="hm-field">
                                    <label for="{{ $idPrefix }}-feat-{{ $i }}-spotify" class="hm-label">{{ __('release.info.guest_spotify') }}<span class="hm-label__optional"> · {{ __('ui.optional') }}</span></label>
                                    <input id="{{ $idPrefix }}-feat-{{ $i }}-spotify" type="text" inputmode="url" class="hm-input hm-input--mono @error("featuring.$i.spotify") is-invalid @enderror" maxlength="200" wire:model.live.blur="{{ $model }}.{{ $i }}.spotify"
                                        @error("featuring.$i.spotify") aria-invalid="true" aria-describedby="{{ $idPrefix }}-feat-{{ $i }}-spotify-error" @enderror>
                                    <x-ui.field-error :id="$idPrefix.'-feat-'.$i.'-spotify-error'" :message="$errors->first('featuring.'.$i.'.spotify')" />
                                </div>
                                <div class="hm-field">
                                    <label for="{{ $idPrefix }}-feat-{{ $i }}-apple" class="hm-label">{{ __('release.info.guest_apple') }}<span class="hm-label__optional"> · {{ __('ui.optional') }}</span></label>
                                    <input id="{{ $idPrefix }}-feat-{{ $i }}-apple" type="text" inputmode="url" class="hm-input hm-input--mono @error("featuring.$i.apple") is-invalid @enderror" maxlength="200" wire:model.live.blur="{{ $model }}.{{ $i }}.apple"
                                        @error("featuring.$i.apple") aria-invalid="true" aria-describedby="{{ $idPrefix }}-feat-{{ $i }}-apple-error" @enderror>
                                    <x-ui.field-error :id="$idPrefix.'-feat-'.$i.'-apple-error'" :message="$errors->first('featuring.'.$i.'.apple')" />
                                </div>
                            </div>
                            <x-ui.button size="s" variant="ghost" icon="x" class="mt-7" wire:click="removeFeaturing({{ $i }})">
                                <span class="hm-sr">{{ __('release.info.remove') }}</span>
                            </x-ui.button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <div class="flex flex-wrap gap-2">
        @if ($profiles->isNotEmpty())
            <x-ui.button size="s" icon="user-plus" wire:click="addFeaturing('profile')">{{ __('release.info.add_featuring_profile') }}</x-ui.button>
        @endif
        <x-ui.button size="s" icon="plus" wire:click="addFeaturing('guest')">{{ __('release.info.add_featuring_guest') }}</x-ui.button>
    </div>
</fieldset>
