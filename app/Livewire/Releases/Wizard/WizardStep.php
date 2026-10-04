<?php

namespace App\Livewire\Releases\Wizard;

use App\Domain\Releases\ReleaseValidator;
use App\Models\Release;
use Illuminate\Support\MessageBag;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Sihirbaz adımlarının ortak davranışı: her istekte sahiplik ve düzenlenebilirlik
 * kontrolü, alan değiştikçe otomatik kayıt, "Devam et" ile adım kontrolü ve geçiş.
 *
 * Hatalar alan bırakıldığında (touched) ya da "Devam et"e basıldığında görünür;
 * mesajlar ReleaseValidator'dan gelir, böylece özet adımıyla aynıdır.
 */
abstract class WizardStep extends Component
{
    public const STEP = 0;

    #[Locked]
    public string $releaseUlid;

    /**
     * @var array<string, bool>
     */
    public array $touched = [];

    public bool $showAll = false;

    public bool $saved = false;

    /**
     * Girilen değer kaydedilemediğinde (ör. okunamayan link) gösterilen hatalar.
     *
     * @var array<string, string>
     */
    public array $inputErrors = [];

    private ?Release $loaded = null;

    public function hydrate(): void
    {
        $this->authorize('update', $this->release());
    }

    protected function release(): Release
    {
        if ($this->loaded === null) {
            $this->loaded = Release::query()->where('ulid', $this->releaseUlid)->firstOrFail();
            $this->authorize('update', $this->loaded);
        }

        return $this->loaded;
    }

    protected function forgetRelease(): void
    {
        $this->loaded = null;
    }

    protected function useRelease(Release $release): void
    {
        $this->authorize('update', $release);
        $this->releaseUlid = $release->ulid;
        $this->loaded = $release;
    }

    protected function markSaved(): void
    {
        $this->release()->touch();
        $this->saved = true;
    }

    public function next(ReleaseValidator $validator): void
    {
        $this->forgetRelease();
        $issues = $validator->step($this->release(), static::STEP);

        if ($issues !== [] || $this->inputErrors !== []) {
            $this->showAll = true;
            $this->dispatch('wizard-step-invalid');

            return;
        }

        $release = $this->release();
        $release->forceFill(['wizard_step' => max($release->wizard_step, static::STEP + 1)])->save();

        $this->redirectRoute('panel.releases.edit', ['release' => $release->ulid, 'step' => static::STEP + 1]);
    }

    /**
     * Görünür hataları Livewire hata çantasına koyar; bileşenler $errors üzerinden okur.
     * Adım hataları ($issues) "Devam et"ten sonra özet olarak da listelenir; $fields
     * yalnızca ilgili alanın altında görünür.
     *
     * @param  array<string, string>  $issues
     * @param  array<string, string>  $fields
     * @return list<string> Özet listesi
     */
    protected function exposeErrors(array $issues, array $fields = []): array
    {
        $visible = fn (array $errors): array => $this->showAll
            ? $errors
            : array_filter($errors, fn (string $key): bool => $this->isTouched($key), ARRAY_FILTER_USE_KEY);

        $bag = array_merge($visible($issues), $visible($fields), $this->inputErrors);
        $this->setErrorBag(new MessageBag(array_map(fn (string $message): array => [$message], $bag)));

        return $this->showAll
            ? array_values(array_unique([...array_values($issues), ...array_values($this->inputErrors)]))
            : [];
    }

    protected function isTouched(string $key): bool
    {
        foreach (array_keys($this->touched) as $touched) {
            if ($key === $touched || str_starts_with($key, $touched.'.')) {
                return true;
            }
        }

        return false;
    }

    protected function touch(string $key): void
    {
        $this->touched[$key] = true;
    }

    protected function clearInputError(string $prefix): void
    {
        $this->inputErrors = array_filter(
            $this->inputErrors,
            fn (string $key): bool => $key !== $prefix && ! str_starts_with($key, $prefix.'.'),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
