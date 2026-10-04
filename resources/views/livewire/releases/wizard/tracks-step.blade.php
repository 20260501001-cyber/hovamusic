<div class="grid gap-6"
    x-data
    x-on:wizard-step-invalid.window="$nextTick(() => $refs.summary?.focus())"
    x-on:track-moved.window="$nextTick(() => document.getElementById('grip-' + $event.detail.ulid)?.focus())"
    @if ($hasPending) wire:poll.3s @endif>
    @include('livewire.releases.wizard.partials.step-errors')

    <section class="hm-card overflow-hidden" aria-labelledby="parcalar-baslik">
        <div class="hm-card__head">
            <h2 id="parcalar-baslik" class="hm-h3">{{ __('release.tracks.title') }}</h2>
            <p class="hm-muted m-0 text-sm">{{ __('release.tracks.credits_help') }}</p>
        </div>

        <p class="hm-sr" aria-live="polite">{{ $announcement }}</p>

        @if ($tracks->isEmpty())
            <div class="hm-card__body">
                <x-ui.empty-state :title="__('release.tracks.empty_title')" icon="list-music">
                    {{ __('release.tracks.empty_body') }}
                    <x-slot:action>
                        <x-ui.button variant="primary" icon="plus" wire:click="addTrack">{{ __('release.tracks.add') }}</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @else
            <div class="px-4 pt-4 sm:px-5">
                <p id="reorder-hint" class="hm-field__help">{{ __('release.tracks.reorder_hint') }}</p>
            </div>

            <ol class="m-0 mt-3 list-none border-t border-line p-0" wire:sort="reorder">
                @foreach ($tracks as $track)
                    @php
                        $isEditing = $editing === $track->ulid;
                        $featuringLine = $track->featuringLine();
                        $audio = $track->audio;
                        $hasIssues = $showAll && ($trackIssueCount[$track->ulid] ?? 0) > 0;
                    @endphp
                    <li wire:key="track-{{ $track->ulid }}" wire:sort:item="{{ $track->ulid }}">
                        <div class="hm-track">
                            <button type="button" id="grip-{{ $track->ulid }}" class="hm-track__grip" wire:sort:handle
                                aria-describedby="reorder-hint"
                                x-on:keydown.arrow-up.prevent="$wire.move('{{ $track->ulid }}', -1)"
                                x-on:keydown.arrow-down.prevent="$wire.move('{{ $track->ulid }}', 1)">
                                <x-lucide-grip-vertical class="hm-icon" width="16" height="16" aria-hidden="true" />
                                <span class="hm-sr">{{ __('release.tracks.grip_label', ['title' => $track->displayTitle()]) }}</span>
                            </button>
                            <span class="hm-track__pos">{{ \App\Support\Format::position($track->position) }}</span>
                            <div class="hm-track__main">
                                <button type="button" class="hm-track__title block w-full cursor-pointer border-0 bg-transparent p-0 text-left hover:underline"
                                    wire:click="{{ $isEditing ? 'close' : "edit('{$track->ulid}')" }}"
                                    aria-expanded="{{ $isEditing ? 'true' : 'false' }}" aria-controls="track-{{ $track->ulid }}-panel">
                                    @if ($hasIssues)
                                        <x-lucide-circle-alert class="hm-icon inline text-danger" width="16" height="16" aria-hidden="true" />
                                    @endif
                                    {{ $track->title ?: __('release.track.untitled') }}@if ($track->version)<span class="hm-track__version"> ({{ $track->version }})</span>@endif
                                    @if ($track->explicit)<span class="hm-explicit" aria-hidden="true">E</span><span class="hm-sr">{{ __('release.info.explicit') }}</span>@endif
                                </button>
                                <span class="hm-track__artists">{{ $primaryLine }}{{ $featuringLine !== '' ? ' feat. '.$featuringLine : '' }}</span>
                            </div>
                            <span @class(['hm-track__isrc', 'is-empty' => ! $track->isrc])>{{ $track->formattedIsrc() ?? __('release.isrc_pending') }}</span>
                            <span class="hm-track__dur">
                                @if ($audio === null)
                                    <span aria-hidden="true">—</span><span class="hm-sr">{{ __('release.validation.track_audio_required') }}</span>
                                @elseif ($audio->validation_status === \App\Enums\MediaStatus::Pending)
                                    <x-lucide-loader-circle class="hm-icon hm-spin inline" width="16" height="16" aria-hidden="true" /><span class="hm-sr">{{ __('release.tracks.audio.processing') }}</span>
                                @elseif ($audio->validation_status === \App\Enums\MediaStatus::Invalid)
                                    <x-lucide-circle-x class="hm-icon inline text-danger" width="16" height="16" aria-hidden="true" /><span class="hm-sr">{{ __('release.validation.track_audio_invalid') }}</span>
                                @else
                                    {{ \App\Support\Format::duration($track->duration_ms) }}
                                @endif
                            </span>
                        </div>

                        @if ($isEditing)
                            <div id="track-{{ $track->ulid }}-panel" class="grid gap-5 border-b border-line bg-ground p-4 sm:p-5" wire:sort:ignore>
                                @include('livewire.releases.wizard.partials.audio-field', ['track' => $track, 'audio' => $audio])

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-ui.field name="form.title" :label="__('release.tracks.fields.title')" maxlength="200" wire:model.live.blur="form.title" />
                                    <x-ui.field name="form.version" :label="__('release.tracks.fields.version')" :help="__('release.info.version_help')" :optional="true" maxlength="120" wire:model.live.blur="form.version" />
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-ui.field name="form.isrc" :label="__('release.tracks.fields.isrc')" :help="__('release.tracks.fields.isrc_help')" :optional="true" :mono="true"
                                        maxlength="20" autocomplete="off" wire:model.live.blur="form.isrc" />
                                    <x-ui.select name="form.language" id="s-form-language" :label="__('release.tracks.fields.language')" :selected="$form['language'] ?? ''"
                                        :options="['' => __('release.info.select_language')] + $languages" wire:model.live="form.language" />
                                </div>

                                <div class="grid items-start gap-4 sm:grid-cols-2">
                                    <x-ui.field name="form.preview_start_sec" type="number" :label="__('release.tracks.fields.preview_start')" :help="__('release.tracks.fields.preview_start_help')"
                                        suffix="sn" min="0" max="3600" step="1" inputmode="numeric" wire:model.live.blur="form.preview_start_sec" />
                                    <div class="sm:pt-7">
                                        <x-ui.checkbox name="form.explicit" :checked="(bool) ($form['explicit'] ?? false)" wire:model.live="form.explicit">
                                            {{ __('release.tracks.fields.explicit') }}
                                        </x-ui.checkbox>
                                    </div>
                                </div>

                                @include('livewire.releases.wizard.partials.featuring', [
                                    'model' => 'form.featuring',
                                    'entries' => $form['featuring'] ?? [],
                                    'exclude' => array_merge($primaryUlids, collect($form['featuring'] ?? [])->pluck('artist')->filter()->all()),
                                    'idPrefix' => 'track-'.$track->ulid,
                                ])

                                <div class="grid gap-4 md:grid-cols-3">
                                    @foreach (['composers', 'lyricists', 'producers'] as $field)
                                        <fieldset class="hm-field m-0 border-0 p-0" wire:key="credits-{{ $field }}">
                                            <legend class="hm-label">
                                                {{ __('release.tracks.fields.'.$field) }}@if ($field === 'producers')<span class="hm-label__optional"> · {{ __('ui.optional') }}</span>@endif
                                            </legend>
                                            @foreach ($form[$field] ?? [] as $i => $name)
                                                <div class="flex items-center gap-2" wire:key="credit-{{ $field }}-{{ $i }}">
                                                    <label for="credit-{{ $field }}-{{ $i }}" class="hm-sr">{{ __('release.tracks.fields.'.$field) }} {{ $i + 1 }}</label>
                                                    <input id="credit-{{ $field }}-{{ $i }}" type="text" class="hm-input min-w-0 flex-1 @error('form.'.$field) is-invalid @enderror"
                                                        placeholder="{{ __('release.tracks.fields.credit_name') }}" maxlength="150" autocomplete="off"
                                                        wire:model.live.blur="form.{{ $field }}.{{ $i }}"
                                                        @error('form.'.$field) aria-invalid="true" aria-describedby="credits-{{ $field }}-error" @enderror>
                                                    <button type="button" class="hm-btn hm-btn--ghost hm-btn--s" wire:click="removeCredit('{{ $field }}', {{ $i }})">
                                                        <x-lucide-x class="hm-icon" width="16" height="16" aria-hidden="true" />
                                                        <span class="hm-sr">{{ __('release.info.remove') }}</span>
                                                    </button>
                                                </div>
                                            @endforeach
                                            <div>
                                                <x-ui.button size="s" variant="ghost" icon="plus" wire:click="addCredit('{{ $field }}')">{{ __('release.tracks.fields.add_credit') }}</x-ui.button>
                                            </div>
                                            <x-ui.field-error :id="'credits-'.$field.'-error'" :message="$errors->first('form.'.$field)" />
                                        </fieldset>
                                    @endforeach
                                </div>

                                <x-ui.field name="form.lyrics" :label="__('release.tracks.fields.lyrics')" :optional="true" :multiline="true" :rows="6" maxlength="20000" wire:model.live.blur="form.lyrics" />

                                <div class="flex flex-wrap justify-between gap-3">
                                    <x-ui.button variant="ghost" icon="trash-2" class="text-danger"
                                        wire:click="deleteTrack('{{ $track->ulid }}')" wire:confirm="{{ __('release.tracks.delete_confirm') }}">
                                        {{ __('release.tracks.delete') }}
                                    </x-ui.button>
                                    <x-ui.button icon="chevron-up" wire:click="close">{{ __('release.tracks.close') }}</x-ui.button>
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>

            <div class="p-4 sm:p-5">
                <x-ui.button icon="plus" wire:click="addTrack">{{ __('release.tracks.add') }}</x-ui.button>
            </div>
        @endif
    </section>

    @include('livewire.releases.wizard.partials.footer', ['release' => $release, 'step' => 3])
</div>
