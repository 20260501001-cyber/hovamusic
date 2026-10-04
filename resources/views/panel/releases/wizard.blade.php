<x-layouts.panel :title="$release->displayTitle()">
    @php
        $steps = __('release.wizard.steps');
        $total = count($steps);
    @endphp

    <div class="hm-page-head">
        <p class="hm-eyebrow" @if ($release->type === \App\Enums\ReleaseType::Single) lang="en" @endif>{{ $release->type?->label() ?? __('release.list.title') }}</p>
        <h1 class="hm-h1">{{ $release->displayTitle() }}</h1>
    </div>

    <nav aria-label="{{ __('release.wizard.label') }}">
        <p class="hm-stepper__compact">
            {!! __('release.wizard.step_of', ['current' => '<strong>'.$step.'</strong>', 'total' => $total]) !!} · {{ $steps[$step] }}
        </p>
        <ol class="hm-stepper__list">
            @foreach ($steps as $number => $name)
                @php
                    $isCurrent = $number === $step;
                    $isDone = ! $isCurrent && $number < $release->wizard_step;
                    $reachable = $number <= $release->wizard_step;
                @endphp
                <li @class(['hm-step', 'hm-step--current' => $isCurrent, 'hm-step--done' => $isDone]) @if ($isCurrent) aria-current="step" @endif>
                    @if ($reachable && ! $isCurrent)
                        <a href="{{ route('panel.releases.edit', ['release' => $release->ulid, 'step' => $number]) }}" class="flex min-w-0 items-center gap-2.5 text-inherit no-underline hover:text-ink">
                    @else
                        <span class="flex min-w-0 items-center gap-2.5">
                    @endif
                        <span class="hm-step__num">
                            @if ($isDone)
                                <x-lucide-check class="hm-icon" width="14" height="14" aria-hidden="true" />
                            @else
                                {{ sprintf('%02d', $number) }}
                            @endif
                        </span>
                        <span class="hm-step__name">{{ $name }}</span>
                        @if ($isDone)<span class="hm-sr">({{ __('release.wizard.done') }})</span>@endif
                    @if ($reachable && ! $isCurrent)
                        </a>
                    @else
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>

    @if ($release->status === \App\Enums\ReleaseStatus::NeedsChanges)
        <x-ui.alert tone="warning" :title="__('release.wizard.needs_changes_title')">
            {{ __('release.wizard.needs_changes_body') }}
            @if ($note)
                <span class="mt-2 block"><strong>{{ __('release.show.needs_changes_note') }}:</strong> {{ $note }}</span>
            @endif
        </x-ui.alert>
    @endif

    @livewire($stepComponent, ['release' => $release], key($release->ulid.'-'.$step))
</x-layouts.panel>
