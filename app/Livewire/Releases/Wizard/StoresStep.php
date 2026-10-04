<?php

namespace App\Livewire\Releases\Wizard;

use App\Domain\Releases\ReleaseValidator;
use App\Enums\TerritoryMode;
use App\Models\Platform;
use App\Models\Release;
use App\Support\Locale\Countries;
use Illuminate\Contracts\View\View;

class StoresStep extends WizardStep
{
    public const STEP = ReleaseValidator::STEP_STORES;

    /**
     * @var list<string>
     */
    public array $platforms = [];

    public string $territoryMode = 'worldwide';

    /**
     * @var list<string>
     */
    public array $territories = [];

    public function mount(Release $release): void
    {
        $this->useRelease($release);
        $release->load('platforms');

        // İlk ziyarette tüm etkin mağazalar seçili gelir.
        if ($release->platforms->isEmpty() && $release->wizard_step <= self::STEP) {
            $release->platforms()->sync(Platform::query()->active()->pluck('id'));
            $release->load('platforms');
        }

        $this->platforms = $release->platforms->pluck('id')->map(fn (int $id): string => (string) $id)->all();
        $this->territoryMode = $release->territory_mode->value;
        $this->territories = array_values($release->territories ?? []);
    }

    public function updatedPlatforms(): void
    {
        $this->touch('platforms');
        $this->savePlatforms(array_map('intval', array_filter($this->platforms, 'is_scalar')));
    }

    public function selectAllPlatforms(): void
    {
        $this->touch('platforms');
        $this->savePlatforms(Platform::query()->active()->pluck('id')->all());
    }

    public function clearPlatforms(): void
    {
        $this->touch('platforms');
        $this->savePlatforms([]);
    }

    public function updatedTerritoryMode(): void
    {
        $mode = TerritoryMode::tryFrom($this->territoryMode) ?? TerritoryMode::Worldwide;
        $this->territoryMode = $mode->value;
        $this->release()->forceFill(['territory_mode' => $mode])->save();
        $this->markSaved();
    }

    public function updatedTerritories(): void
    {
        $this->touch('territories');
        $this->saveTerritories($this->territories);
    }

    public function clearTerritories(): void
    {
        $this->touch('territories');
        $this->saveTerritories([]);
    }

    /**
     * @param  list<int>  $ids
     */
    private function savePlatforms(array $ids): void
    {
        $valid = Platform::query()->active()->whereIn('id', $ids)->pluck('id')->all();
        $this->release()->platforms()->sync($valid);
        $this->platforms = array_map('strval', $valid);
        $this->markSaved();
    }

    /**
     * @param  array<mixed>  $codes
     */
    private function saveTerritories(array $codes): void
    {
        $valid = array_values(array_intersect(Countries::codes(), array_filter($codes, 'is_string')));
        $this->territories = $valid;
        $this->release()->forceFill(['territories' => $valid === [] ? null : $valid])->save();
        $this->markSaved();
    }

    public function render(): View
    {
        $this->forgetRelease();
        $release = $this->release();
        $summary = $this->exposeErrors(app(ReleaseValidator::class)->step($release, self::STEP));

        return view('livewire.releases.wizard.stores-step', [
            'release' => $release,
            'summary' => $summary,
            'availablePlatforms' => Platform::query()->active()->ordered()->get(),
            'modes' => TerritoryMode::cases(),
            'countries' => Countries::options(),
        ]);
    }
}
