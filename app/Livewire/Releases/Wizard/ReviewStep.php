<?php

namespace App\Livewire\Releases\Wizard;

use App\Domain\Media\MediaUrl;
use App\Domain\Releases\ReleaseSubmission;
use App\Domain\Releases\ReleaseValidator;
use App\Domain\Releases\SubmissionFailed;
use App\Enums\ReleaseStatus;
use App\Models\Release;
use App\Support\Format;
use App\Support\Locale\Countries;
use App\Support\Locale\Languages;
use Illuminate\Contracts\View\View;
use Illuminate\Support\MessageBag;

class ReviewStep extends WizardStep
{
    public const STEP = 5;

    /**
     * @var array<string, bool>
     */
    public array $declarations = [];

    /**
     * Gönderim sırasında oluşan, adıma bağlı olmayan hatalar (plan, durum, beyan).
     *
     * @var list<string>
     */
    public array $submitErrors = [];

    public function mount(Release $release): void
    {
        $this->useRelease($release);
        $this->declarations = collect(config('hova.consents.release'))->keys()->mapWithKeys(fn (string $key): array => [$key => false])->all();
    }

    public function next(ReleaseValidator $validator): void
    {
        // Son adımda "Devam et" yok; gönderim submit() ile yapılır.
    }

    public function submit(ReleaseSubmission $submission): void
    {
        $this->forgetRelease();
        $release = $this->release();

        try {
            $submission->submit($release, auth()->user(), $this->declarations);
        } catch (SubmissionFailed $failed) {
            $this->showAll = true;
            $this->submitErrors = collect($failed->errors)
                ->filter(fn ($messages, $key): bool => ! is_int($key))
                ->flatten()
                ->values()
                ->all();
            $this->dispatch('wizard-step-invalid');

            return;
        }

        session()->flash('flash', __('release.review.submitted'));
        $this->redirectRoute('panel.releases.show', ['release' => $release->ulid]);
    }

    public function render(): View
    {
        $this->forgetRelease();
        $release = $this->release()->load([
            'artists', 'genre', 'subgenre', 'cover', 'platforms',
            'tracks.audio', 'tracks.credits', 'tracks.artists',
        ]);
        $issues = app(ReleaseValidator::class)->issues($release);
        $this->setErrorBag(new MessageBag(
            $this->showAll ? ['declarations' => $this->submitErrors] : [],
        ));

        return view('livewire.releases.wizard.review-step', [
            'release' => $release,
            'issues' => $issues,
            'ready' => $issues === [],
            'coverUrl' => $release->cover?->isValid() ? MediaUrl::temporary($release->cover) : null,
            'language' => Languages::name($release->language),
            'totalDuration' => Format::duration($release->totalDurationMs()),
            'releaseDate' => Format::longDate($release->release_date),
            'originalReleaseDate' => Format::longDate($release->original_release_date),
            'territoryText' => $release->territory_mode->label().(
                $release->territory_mode->value !== 'worldwide' && $release->territories
                    ? ': '.collect($release->territories)->map(fn (string $code): string => Countries::name($code))->implode(', ')
                    : ''
            ),
            'declarationTexts' => collect(config('hova.consents.release'))->keys()->mapWithKeys(fn (string $key): array => [$key => __('release.declarations.'.$key)])->all(),
            'resubmit' => $release->status === ReleaseStatus::NeedsChanges,
        ]);
    }
}
