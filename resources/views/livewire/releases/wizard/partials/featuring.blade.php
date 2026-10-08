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
                            <div class="hm-field min-w-0 flex-1">
                                <label for="{{ $idPrefix }}-feat-{{ $i }}-name" class="hm-label">{{ __('release.info.guest_name') }}</label>
                                <input id="{{ $idPrefix }}-feat-{{ $i }}-name" type="text" class="hm-input" maxlength="150" autocomplete="off"
                                    wire:model.live.debounce.600ms="{{ $model }}.{{ $i }}.name" aria-describedby="{{ $idPrefix }}-feat-{{ $i }}-name-help">
                                <p id="{{ $idPrefix }}-feat-{{ $i }}-name-help" class="hm-field__help">{{ __('release.info.guest_help') }}</p>
                            </div>
                            <x-ui.button size="s" variant="ghost" icon="x" class="mt-7" wire:click="removeFeaturing({{ $i }})">
                                <span class="hm-sr">{{ __('release.info.remove') }}</span>
                            </x-ui.button>
                        </div>
                        <div class="grid gap-3 lg:grid-cols-2">
                            @foreach (['spotify', 'apple'] as $store)
                                @php
                                    $lookup = $guestLookup[$i] ?? [];
                                    $value = trim((string) ($entry[$store] ?? ''));
                                    $storeId = $store === 'spotify' ? \App\Domain\Spotify\SpotifyLinkParser::artistId($value) : \App\Domain\Artists\AppleMusicLinkParser::artistId($value);
                                    $selected = empty($entry[$store.'_link']) && $storeId !== null ? [
                                        'id' => $storeId,
                                        'name' => $entry[$store.'_name'] ?? ($entry['name'] ?? ''),
                                        'url' => str_starts_with($value, 'http') ? $value : ($store === 'spotify' ? 'https://open.spotify.com/artist/'.$storeId : 'https://music.apple.com/artist/'.$storeId),
                                        'image_url' => $entry[$store.'_image'] ?? null,
                                        'genre' => $entry[$store.'_genre'] ?? null,
                                    ] : null;
                                    $call = fn (string $method, string $extra = ''): string => $method.'('.$i.', \''.$store.'\''.$extra.')';
                                @endphp
                                <div class="hm-store hm-store--compact" role="group" aria-labelledby="{{ $idPrefix }}-feat-{{ $i }}-{{ $store }}-title" wire:key="{{ $idPrefix }}-feat-{{ $i }}-{{ $store }}">
                                    <p id="{{ $idPrefix }}-feat-{{ $i }}-{{ $store }}-title" class="hm-store__title">{{ __('artist.'.$store) }}<span class="hm-label__optional"> · {{ __('ui.optional') }}</span></p>
                                    <x-artist.store-pick
                                        :store="$store"
                                        :selected="$selected"
                                        :results="$lookup[$store] ?? []"
                                        :message="$lookup[$store.'_message'] ?? ''"
                                        :by-link="! empty($entry[$store.'_link']) || ($value !== '' && $storeId === null)"
                                        :link-model="$model.'.'.$i.'.'.$store"
                                        :link-id="$idPrefix.'-feat-'.$i.'-'.$store"
                                        :link-live="false"
                                        select-action="selectGuestProfile"
                                        :select-args="$i.', \''.$store.'\''"
                                        :clear="$call('clearGuestProfile')"
                                        :to-link="$call('guestLinkMode', ', true')"
                                        :to-search="$call('guestLinkMode', ', false')"
                                        :loading-target="$model.'.'.$i.'.name'"
                                    />
                                    <x-ui.field-error :id="$idPrefix.'-feat-'.$i.'-'.$store.'-error'" :message="$errors->first('featuring.'.$i.'.'.$store)" />
                                </div>
                            @endforeach
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
