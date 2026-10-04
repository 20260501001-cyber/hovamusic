@php($nextLabel = $nextLabel ?? null)

<div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5">
    <div class="flex items-center gap-3">
        @if ($step > 1)
            <x-ui.button :href="route('panel.releases.edit', ['release' => $release->ulid, 'step' => $step - 1])" variant="ghost" icon="arrow-left">
                {{ __('release.wizard.back') }}
            </x-ui.button>
        @endif
        <p class="m-0 text-sm text-ink-muted" aria-live="polite">
            <span wire:loading.delay.long>{{ __('release.wizard.saving') }}</span>
            @if ($saved)
                <span wire:loading.remove.delay.long class="inline-flex items-center gap-1">
                    <x-lucide-check class="hm-icon" width="14" height="14" aria-hidden="true" />{{ __('release.wizard.saved') }}
                </span>
            @endif
        </p>
    </div>
    @if ($nextLabel !== false)
        <x-ui.button type="button" variant="primary" icon-right="arrow-right" wire:click="next" wire:loading.attr="disabled" wire:target="next">
            {{ $nextLabel ?? __('release.wizard.next') }}
        </x-ui.button>
    @endif
</div>
