{{-- Parçanın ses dosyası: yükleme durumu (Alpine) ve sunucudaki kontrol sonucu. --}}
@php
    $inputId = 'audio-'.$track->ulid;
    $hint = __('release.tracks.audio.hint', ['max' => $audioMaxText]);
    $status = $audio?->validation_status;
@endphp

<div class="hm-field" wire:key="audio-field-{{ $track->ulid }}"
    x-data="hmAudioUploader({ startUrl: @js(route('panel.uploads.store')), track: @js($track->ulid), maxBytes: {{ $audioMax }}, messages: @js($uploadMessages) })"
    x-on:dragover.prevent="dragging = true"
    x-on:dragleave.prevent="dragging = false"
    x-on:drop.prevent="dragging = false; pick($event.dataTransfer.files[0])">
    <span class="hm-label">{{ __('release.tracks.audio.title') }}</span>

    <div class="hm-file hm-file--processing" x-show="state === 'uploading' || state === 'retrying'" x-cloak>
        <x-lucide-upload class="hm-file__icon hm-icon" width="20" height="20" aria-hidden="true" />
        <div class="hm-file__body">
            <div class="hm-file__row">
                <span class="hm-file__name" x-text="fileName"></span>
                <span class="hm-file__pct" x-text="progress + '%'"></span>
            </div>
            <div class="hm-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="progress" aria-label="{{ __('release.tracks.audio.title') }}">
                <span class="hm-progress__bar" :style="'width: ' + progress + '%'"></span>
            </div>
            <p class="hm-file__meta" x-show="state === 'uploading'" x-text="sentText"></p>
            <p class="hm-file__meta" x-show="state === 'retrying'" role="status">{{ __('release.tracks.audio.resuming') }}</p>
            <div>
                <button type="button" class="hm-btn hm-btn--ghost hm-btn--s" x-on:click="cancel()">
                    <x-lucide-circle-stop class="hm-icon" width="16" height="16" aria-hidden="true" />
                    <span>{{ __('release.tracks.audio.cancel') }}</span>
                </button>
            </div>
        </div>
    </div>

    <div class="hm-file hm-file--processing" x-show="state === 'processing'" x-cloak role="status">
        <x-lucide-loader-circle class="hm-file__icon hm-icon hm-spin" width="20" height="20" aria-hidden="true" />
        <div class="hm-file__body">
            <span class="hm-file__name" x-text="fileName"></span>
            <p class="hm-file__meta">{{ __('release.tracks.audio.processing') }}</p>
        </div>
    </div>

    <div class="hm-file hm-file--invalid" role="alert" x-show="state === 'error'" x-cloak>
        <x-lucide-file-x class="hm-file__icon hm-icon" width="20" height="20" aria-hidden="true" />
        <div class="hm-file__body">
            <span class="hm-file__name" x-text="fileName"></span>
            <p class="hm-file__meta" x-text="error"></p>
        </div>
    </div>

    <div class="grid gap-3" x-show="state === 'idle' || state === 'error'">
        @if ($audio && $status === \App\Enums\MediaStatus::Valid)
            <div class="hm-file hm-file--valid">
                <x-lucide-file-audio class="hm-file__icon hm-icon" width="20" height="20" aria-hidden="true" />
                <div class="hm-file__body">
                    <span class="hm-file__name">{{ $audio->original_name }}</span>
                    <p class="hm-file__meta">{{ $audio->audioSummary() }}</p>
                </div>
            </div>
        @elseif ($audio && $status === \App\Enums\MediaStatus::Pending)
            <div class="hm-file hm-file--processing" role="status">
                <x-lucide-loader-circle class="hm-file__icon hm-icon hm-spin" width="20" height="20" aria-hidden="true" />
                <div class="hm-file__body">
                    <span class="hm-file__name">{{ $audio->original_name }}</span>
                    <p class="hm-file__meta">{{ __('release.tracks.audio.processing') }}</p>
                </div>
            </div>
        @elseif ($audio && $status === \App\Enums\MediaStatus::Invalid)
            <div class="hm-file hm-file--invalid" role="alert">
                <x-lucide-file-x class="hm-file__icon hm-icon" width="20" height="20" aria-hidden="true" />
                <div class="hm-file__body">
                    <span class="hm-file__name">{{ $audio->original_name }}</span>
                    @foreach ($audio->validation_errors ?? [__('release.validation.track_audio_invalid')] as $message)
                        <p class="hm-file__meta">{{ $message }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($audio === null)
            <div class="hm-drop">
                <input id="{{ $inputId }}" type="file" class="hm-drop__input" accept=".wav,.flac,audio/wav,audio/x-wav,audio/flac"
                    x-on:change="pick($event.target.files[0]); $event.target.value = ''" aria-describedby="{{ $inputId }}-hint">
                <label for="{{ $inputId }}" class="hm-drop__zone" :class="dragging && 'border-accent-ink text-ink'">
                    <x-lucide-file-audio class="hm-icon" width="24" height="24" aria-hidden="true" />
                    <span class="hm-drop__title">{{ __('release.tracks.audio.drop_title') }}</span>
                    <span id="{{ $inputId }}-hint" class="hm-drop__hint">{{ $hint }}</span>
                </label>
            </div>
        @elseif ($status !== \App\Enums\MediaStatus::Pending)
            <div class="flex flex-wrap items-center gap-3">
                <input id="{{ $inputId }}" type="file" class="peer hm-sr" accept=".wav,.flac,audio/wav,audio/x-wav,audio/flac"
                    x-on:change="pick($event.target.files[0]); $event.target.value = ''" aria-describedby="{{ $inputId }}-hint">
                <label for="{{ $inputId }}" class="hm-btn hm-btn--secondary hm-btn--s cursor-pointer peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-(--focus-ring)">
                    <x-lucide-replace class="hm-icon" width="16" height="16" aria-hidden="true" />
                    <span>{{ __('release.tracks.audio.replace') }}</span>
                </label>
                <span id="{{ $inputId }}-hint" class="hm-field__help">{{ $hint }}</span>
            </div>
        @endif
    </div>

    <x-ui.field-error :id="$inputId.'-error'" :message="$errors->first('form.audio')" />
</div>
