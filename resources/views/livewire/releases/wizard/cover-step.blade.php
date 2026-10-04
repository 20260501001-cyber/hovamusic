<div class="grid gap-6" x-data x-on:wizard-step-invalid.window="$nextTick(() => $refs.summary?.focus())">
    @include('livewire.releases.wizard.partials.step-errors')

    <section class="hm-card" aria-labelledby="kapak-baslik">
        <div class="hm-card__head">
            <h2 id="kapak-baslik" class="hm-h3">{{ __('release.cover.title') }}</h2>
            <p class="hm-muted m-0 text-sm">{{ __('release.cover.rules') }}</p>
        </div>
        <div class="hm-card__body"
            x-data="hmCoverPicker({ maxBytes: {{ $maxBytes }}, size: 3000, messages: @js($messages) })"
            x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false" x-on:drop.prevent="dragging = false; pick($event.dataTransfer.files[0])">

            {{-- Tarayıcıdaki ön kontrol hatası (dosya sunucuya gönderilmedi). --}}
            <div class="hm-file hm-file--invalid" role="alert" x-show="state === 'error'" x-cloak>
                <x-lucide-image-off class="hm-file__icon hm-icon" width="20" height="20" aria-hidden="true" />
                <div class="hm-file__body">
                    <span class="hm-file__name" x-text="fileName"></span>
                    <template x-for="message in errors" :key="message">
                        <p class="hm-file__meta" x-text="message"></p>
                    </template>
                </div>
            </div>

            {{-- Livewire yüklemesi sürerken. --}}
            <div class="hm-file hm-file--processing" x-show="state === 'uploading'" x-cloak>
                <x-lucide-upload class="hm-file__icon hm-icon" width="20" height="20" aria-hidden="true" />
                <div class="hm-file__body">
                    <div class="hm-file__row">
                        <span class="hm-file__name" x-text="fileName"></span>
                        <span class="hm-file__pct" x-text="progress + '%'"></span>
                    </div>
                    <div class="hm-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="progress" aria-label="{{ __('release.cover.title') }}">
                        <span class="hm-progress__bar" :style="'width: ' + progress + '%'"></span>
                    </div>
                </div>
            </div>

            {{-- Sunucu kontrolü. --}}
            <div class="hm-file hm-file--processing" wire:loading.flex wire:target="upload, _finishUpload" x-show="state !== 'uploading'">
                <x-lucide-loader-circle class="hm-file__icon hm-icon hm-spin" width="20" height="20" aria-hidden="true" />
                <div class="hm-file__body">
                    <span class="hm-file__name">{{ __('release.cover.processing') }}</span>
                </div>
            </div>

            <div wire:loading.remove wire:target="upload, _finishUpload" x-show="state !== 'uploading'" class="grid gap-4">
                @if ($coverErrors !== [])
                    <div class="hm-file hm-file--invalid" role="alert" x-show="state !== 'error'">
                        <x-lucide-image-off class="hm-file__icon hm-icon" width="20" height="20" aria-hidden="true" />
                        <div class="hm-file__body">
                            <span class="hm-file__name">{{ $rejectedName }}</span>
                            @foreach ($coverErrors as $message)
                                <p class="hm-file__meta">{{ $message }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($cover)
                    <div class="flex flex-wrap items-start gap-4">
                        <img src="{{ $coverUrl }}" alt="{{ __('release.cover.preview_alt') }}" width="160" height="160"
                            class="size-40 rounded-sm border border-line object-cover">
                        <div class="hm-file hm-file--valid min-w-0 flex-1">
                            <x-lucide-circle-check class="hm-file__icon hm-icon" width="20" height="20" aria-hidden="true" />
                            <div class="hm-file__body">
                                <span class="hm-file__name">{{ $cover->original_name }}</span>
                                <p class="hm-file__meta">{{ $coverMeta }}</p>
                                <div class="flex flex-wrap gap-2 pt-1">
                                    <input id="cover-replace" type="file" class="peer hm-sr" accept="image/jpeg,image/png"
                                        x-on:change="pick($event.target.files[0]); $event.target.value = ''" aria-describedby="cover-hint">
                                    <label for="cover-replace" class="hm-btn hm-btn--secondary hm-btn--s cursor-pointer peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-(--focus-ring)">
                                        <x-lucide-replace class="hm-icon" width="16" height="16" aria-hidden="true" />
                                        <span>{{ __('release.cover.replace') }}</span>
                                    </label>
                                    <x-ui.button size="s" variant="ghost" icon="trash-2" wire:click="removeCover">{{ __('release.cover.remove') }}</x-ui.button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p id="cover-hint" class="hm-field__help">{{ __('release.cover.hint', ['max' => $maxText]) }}</p>
                @else
                    <div class="hm-drop">
                        <input id="cover-input" type="file" class="hm-drop__input" accept="image/jpeg,image/png"
                            x-on:change="pick($event.target.files[0]); $event.target.value = ''" aria-describedby="cover-hint">
                        <label for="cover-input" class="hm-drop__zone" :class="dragging && 'border-accent-ink text-ink'">
                            <x-lucide-image-up class="hm-icon" width="24" height="24" aria-hidden="true" />
                            <span class="hm-drop__title">{{ __('release.cover.drop_title') }}</span>
                            <span id="cover-hint" class="hm-drop__hint">{{ __('release.cover.hint', ['max' => $maxText]) }}</span>
                        </label>
                    </div>
                    <x-ui.field-error id="cover-error" :message="$errors->first('cover')" />
                @endif
            </div>
        </div>
    </section>

    @include('livewire.releases.wizard.partials.footer', ['release' => $release, 'step' => 2])
</div>
