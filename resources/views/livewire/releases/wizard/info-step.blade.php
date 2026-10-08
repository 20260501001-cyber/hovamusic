<div class="grid gap-6" x-data x-on:wizard-step-invalid.window="$nextTick(() => $refs.summary?.focus())">
    @include('livewire.releases.wizard.partials.step-errors')

    <section class="hm-card" aria-labelledby="bilgi-baslik">
        <div class="hm-card__head">
            <h2 id="bilgi-baslik" class="hm-h3">{{ __('release.wizard.steps.1') }}</h2>
        </div>
        <div class="hm-card__body">
            <fieldset class="hm-field m-0 border-0 p-0">
                <legend class="hm-label">{{ __('release.info.type') }}</legend>
                <div class="grid gap-2 sm:grid-cols-3" role="radiogroup">
                    @foreach ($types as $option)
                        <x-ui.choice name="type" :value="$option->value" :title="$option->label()" :checked="$type === $option->value" wire:model.live="type" />
                    @endforeach
                </div>
                <x-ui.field-error id="type-error" :message="$errors->first('type')" />
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field name="title" :label="__('release.info.title')" maxlength="200" wire:model.live.blur="title" />
                <x-ui.field name="version" :label="__('release.info.version')" :help="__('release.info.version_help')" :optional="true" maxlength="120" wire:model.live.blur="version" />
            </div>

            <div class="hm-field">
                <span class="hm-label" id="primary-label">{{ __('release.info.primary_artists') }}</span>
                @if ($profiles->isEmpty())
                    <x-ui.alert tone="info" :title="__('release.info.no_artists_title')">
                        {{ __('release.info.no_artists_body') }}
                        <x-slot:action>
                            <x-ui.button :href="route('panel.artists')" size="s" icon="user-plus">{{ __('release.info.go_to_artists') }}</x-ui.button>
                        </x-slot:action>
                    </x-ui.alert>
                @else
                    @if ($primaryArtists !== [])
                        <ul class="m-0 flex list-none flex-wrap gap-2 p-0" aria-labelledby="primary-label">
                            @foreach ($primaryArtists as $ulid)
                                @php($profile = $profilesByUlid->get($ulid))
                                @continue($profile === null)
                                <li wire:key="primary-{{ $ulid }}" class="inline-flex items-center gap-1 rounded-full border border-line bg-surface py-1 pl-3 pr-1 text-sm">
                                    <span>{{ $profile->name }}</span>
                                    <button type="button" class="hm-btn hm-btn--ghost hm-btn--s" wire:click="removePrimaryArtist('{{ $ulid }}')">
                                        <x-lucide-x class="hm-icon" width="14" height="14" aria-hidden="true" />
                                        <span class="hm-sr">{{ __('release.info.remove') }}: {{ $profile->name }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @php($available = $profiles->reject(fn ($p) => in_array($p->ulid, $primaryArtists, true)))
                    @if ($available->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-2">
                            <label for="add-primary" class="hm-sr">{{ __('release.info.select_artist') }}</label>
                            <select id="add-primary" class="hm-select max-w-xs" wire:model="addPrimary"
                                @error('primary_artists') aria-invalid="true" aria-describedby="primary-error" @enderror>
                                <option value="">{{ __('release.info.select_artist') }}</option>
                                @foreach ($available as $profile)
                                    <option value="{{ $profile->ulid }}">{{ $profile->name }}</option>
                                @endforeach
                            </select>
                            <x-ui.button icon="plus" wire:click="addPrimaryArtist">{{ __('release.info.add_primary') }}</x-ui.button>
                        </div>
                    @endif
                    <p class="hm-field__help">{{ __('release.info.primary_artists_help') }}</p>
                @endif
                <x-ui.field-error id="primary-error" :message="$errors->first('primary_artists')" />
            </div>

            @include('livewire.releases.wizard.partials.featuring', [
                'model' => 'featuring',
                'entries' => $featuring,
                'exclude' => array_merge($primaryArtists, collect($featuring)->pluck('artist')->filter()->all()),
                'idPrefix' => 'release',
            ])

            @if ($labelLocked)
                <x-ui.field name="label_name" :label="__('release.info.label_name')" :value="$labelName" readonly
                    :help="__('release.info.label_locked_help', ['label' => $defaultLabel])" />
            @else
                <x-ui.field name="label_name" :label="__('release.info.label_name')" :help="__('release.info.label_help')" maxlength="150" wire:model.live.blur="labelName" />
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select name="genre_id" id="s-genre" :label="__('release.info.genre')" :selected="$genreId"
                    :options="['' => __('release.info.select_genre')] + $genres->pluck('name', 'id')->all()" wire:model.live="genreId" />
                <x-ui.select name="subgenre_id" id="s-subgenre" :label="__('release.info.subgenre')" :selected="$subgenreId"
                    :options="['' => __('release.info.no_subgenre')] + $subgenres->pluck('name', 'id')->all()"
                    wire:model.live="subgenreId" :disabled="$subgenres->isEmpty()" />
            </div>

            <x-ui.select name="language" id="s-language" :label="__('release.info.language')" :selected="$language"
                :options="['' => __('release.info.select_language')] + $languages" wire:model.live="language" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field name="release_date" type="date" :label="__('release.info.release_date')" :min="$earliest->format('Y-m-d')"
                    :help="__('release.info.release_date_help', ['date' => $earliestText])" wire:model.live.blur="releaseDate" />
                <x-ui.field name="original_release_date" type="date" :label="__('release.info.original_release_date')" :optional="true"
                    :help="__('release.info.original_release_date_help')" wire:model.live.blur="originalReleaseDate" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field name="p_line" :label="__('release.info.p_line')" maxlength="200"
                    :help="__('release.info.p_line_help', ['example' => $lineExample])" wire:model.live.blur="pLine" />
                <x-ui.field name="c_line" :label="__('release.info.c_line')" maxlength="200"
                    :help="__('release.info.c_line_help', ['example' => $lineExample])" wire:model.live.blur="cLine" />
            </div>

            <x-ui.field name="upc" :label="__('release.info.upc')" :help="__('release.info.upc_help')" :optional="true" :mono="true"
                inputmode="numeric" maxlength="20" autocomplete="off" wire:model.live.blur="upc" />

            <div>
                <x-ui.checkbox name="explicit" :checked="$explicit" :description="__('release.info.explicit_help')" wire:model.live="explicit">
                    {{ __('release.info.explicit') }}
                </x-ui.checkbox>
            </div>
        </div>
    </section>

    @include('livewire.releases.wizard.partials.footer', ['release' => $release, 'step' => 1])
</div>
