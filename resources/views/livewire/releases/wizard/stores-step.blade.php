<div class="grid gap-6" x-data x-on:wizard-step-invalid.window="$nextTick(() => $refs.summary?.focus())">
    @include('livewire.releases.wizard.partials.step-errors')

    <section class="hm-card" aria-labelledby="magazalar-baslik">
        <div class="hm-card__head">
            <h2 id="magazalar-baslik" class="hm-h3">{{ __('release.stores.title') }}</h2>
            <p class="hm-muted m-0 text-sm">{{ __('release.stores.help') }}</p>
        </div>
        <div class="hm-card__body">
            @if ($availablePlatforms->isEmpty())
                <x-ui.alert tone="info" :title="__('release.stores.no_platforms')" />
            @else
                <fieldset class="m-0 grid gap-3 border-0 p-0" @error('platforms') aria-describedby="platforms-error" @enderror>
                    <legend class="hm-sr">{{ __('release.stores.title') }}</legend>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="s" variant="ghost" icon="list-checks" wire:click="selectAllPlatforms">{{ __('release.stores.select_all') }}</x-ui.button>
                        <x-ui.button size="s" variant="ghost" icon="x" wire:click="clearPlatforms">{{ __('release.stores.select_none') }}</x-ui.button>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($availablePlatforms as $platform)
                            <div wire:key="platform-{{ $platform->id }}">
                                <x-ui.checkbox name="platforms[]" :id="'c-platform-'.$platform->id" :value="(string) $platform->id"
                                    :checked="in_array((string) $platform->id, $platforms, true)" bag="_" wire:model.live="platforms">
                                    <span class="inline-flex items-center gap-2">
                                        @if ($platform->logoUrl())
                                            <img src="{{ $platform->logoUrl() }}" alt="" width="20" height="20" class="size-5 rounded-xs object-contain">
                                        @endif
                                        {{ $platform->name }}
                                    </span>
                                </x-ui.checkbox>
                            </div>
                        @endforeach
                    </div>
                    <x-ui.field-error id="platforms-error" :message="$errors->first('platforms')" />
                </fieldset>
            @endif
        </div>
    </section>

    <section class="hm-card" aria-labelledby="bolgeler-baslik">
        <div class="hm-card__head">
            <h2 id="bolgeler-baslik" class="hm-h3">{{ __('release.stores.territories') }}</h2>
        </div>
        <div class="hm-card__body">
            <div class="grid gap-2 sm:grid-cols-3" role="radiogroup" aria-labelledby="bolgeler-baslik">
                @foreach ($modes as $mode)
                    <x-ui.choice name="territory_mode" :value="$mode->value" :title="$mode->label()" :checked="$territoryMode === $mode->value" wire:model.live="territoryMode" />
                @endforeach
            </div>

            @if ($territoryMode !== 'worldwide')
                <fieldset class="m-0 grid gap-3 border-0 p-0" x-data="{ q: '' }" @error('territories') aria-describedby="territories-error" @enderror>
                    <legend class="hm-label">{{ __('release.stores.countries') }}</legend>
                    <div class="flex flex-wrap items-center gap-3">
                        <label for="country-search" class="hm-sr">{{ __('release.stores.country_search') }}</label>
                        <div class="relative min-w-0 flex-1 sm:max-w-xs">
                            <input id="country-search" type="search" class="hm-input" placeholder="{{ __('release.stores.country_search') }}" x-model="q" autocomplete="off">
                        </div>
                        <span class="text-sm text-ink-muted" aria-live="polite">{{ __('release.stores.selected_count', ['count' => count($territories)]) }}</span>
                        @if ($territories !== [])
                            <x-ui.button size="s" variant="ghost" icon="x" wire:click="clearTerritories">{{ __('release.stores.select_none') }}</x-ui.button>
                        @endif
                    </div>
                    <ul class="m-0 grid max-h-96 list-none gap-1 overflow-y-auto rounded-md border border-line p-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($countries as $code => $name)
                            @php($needle = mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], $name)))
                            <li wire:key="country-{{ $code }}" data-name="{{ $needle }}" x-show="q === '' || $el.dataset.name.includes(q.toLocaleLowerCase('tr'))">
                                <label class="flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1 text-sm hover:bg-surface-hover">
                                    <input type="checkbox" value="{{ $code }}" wire:model.live="territories" class="size-4 accent-(--accent)">
                                    <span>{{ $name }}</span>
                                    <span class="ml-auto font-mono text-xs text-ink-subtle">{{ $code }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                    <x-ui.field-error id="territories-error" :message="$errors->first('territories')" />
                </fieldset>
            @endif
        </div>
    </section>

    @include('livewire.releases.wizard.partials.footer', ['release' => $release, 'step' => 4])
</div>
