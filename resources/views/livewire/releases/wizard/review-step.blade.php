<div class="grid gap-6" x-data x-on:wizard-step-invalid.window="$nextTick(() => $refs.summary?.focus())">
    @if ($ready)
        <x-ui.alert tone="success" :title="__('release.review.ready')" />
    @else
        <div x-ref="summary" tabindex="-1" class="outline-none">
            <x-ui.alert tone="warning" :title="__('release.review.not_ready')">
                <div class="grid gap-3">
                    @foreach ($issues as $step => $messages)
                        <div class="grid gap-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <strong class="font-semibold">{{ __('release.wizard.steps.'.$step) }}</strong>
                                <a class="hm-link text-sm" href="{{ route('panel.releases.edit', ['release' => $release->ulid, 'step' => $step]) }}">{{ __('release.review.fix') }}</a>
                            </div>
                            <ul class="m-0 grid gap-1 pl-5">
                                @foreach ($messages as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </x-ui.alert>
        </div>
    @endif

    <section class="hm-card" aria-labelledby="ozet-baslik">
        <div class="hm-card__head">
            <h2 id="ozet-baslik" class="hm-h3">{{ __('release.review.title') }}</h2>
        </div>
        <div class="hm-card__body">
            <div class="flex items-start gap-4">
                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="{{ __('release.cover.preview_alt') }}" width="112" height="112" class="size-28 flex-none rounded-sm border border-line object-cover">
                @else
                    <span class="grid size-28 flex-none place-items-center rounded-sm border border-line bg-surface-hover text-ink-subtle" aria-hidden="true"><x-lucide-disc class="hm-icon" width="28" height="28" /></span>
                @endif
                <div class="grid min-w-0 gap-1">
                    <p class="m-0 text-lg font-semibold">{{ $release->displayTitle() }}</p>
                    <p class="m-0 text-ink-muted">{{ $release->artistLine() ?: '—' }}</p>
                    <p class="m-0 text-sm text-ink-muted">
                        {{ __('release.review.tracks_count', ['count' => $release->tracks->count(), 'duration' => $totalDuration ?: '0:00']) }}
                    </p>
                </div>
            </div>

            @include('panel.releases.partials.details', ['release' => $release])
        </div>

        @if ($release->tracks->isNotEmpty())
            <div class="pb-2">
                <div class="px-4 pb-3 sm:px-5">
                    <h3 class="hm-h3">{{ __('release.tracks.title') }}</h3>
                </div>
                @include('panel.releases.partials.tracks', ['release' => $release])
            </div>
        @endif
    </section>

    <section class="hm-card" aria-labelledby="beyan-baslik">
        <div class="hm-card__head">
            <h2 id="beyan-baslik" class="hm-h3">{{ __('release.review.declarations') }}</h2>
            <p class="hm-muted m-0 text-sm">{{ __('release.review.declarations_help') }}</p>
        </div>
        <div class="hm-card__body">
            @foreach ($declarationTexts as $key => $text)
                <x-ui.checkbox :name="'declarations.'.$key" :id="'c-'.$key" :checked="$declarations[$key] ?? false" bag="_" wire:model="declarations.{{ $key }}">
                    {{ $text }}
                </x-ui.checkbox>
            @endforeach

            @if ($submitErrors !== [])
                <div x-ref="summary" tabindex="-1" class="outline-none">
                    <x-ui.alert tone="danger">
                        <ul class="m-0 grid gap-1 pl-5">
                            @foreach ($submitErrors as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                </div>
            @endif
        </div>
    </section>

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5">
        <x-ui.button :href="route('panel.releases.edit', ['release' => $release->ulid, 'step' => 4])" variant="ghost" icon="arrow-left">
            {{ __('release.wizard.back') }}
        </x-ui.button>
        <x-ui.button type="button" variant="primary" icon="send" wire:click="submit" wire:loading.attr="disabled" wire:target="submit">
            {{ $resubmit ? __('release.review.resubmit') : __('release.review.submit') }}
        </x-ui.button>
    </div>
</div>
