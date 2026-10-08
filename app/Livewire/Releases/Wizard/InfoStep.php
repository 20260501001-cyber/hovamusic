<?php

namespace App\Livewire\Releases\Wizard;

use App\Domain\Releases\ArtistCredits;
use App\Domain\Releases\ReleaseValidator;
use App\Enums\AccountType;
use App\Enums\ArtistRole;
use App\Enums\ReleaseType;
use App\Livewire\Concerns\LooksUpGuestArtists;
use App\Models\Genre;
use App\Models\Release;
use App\Support\Format;
use App\Support\Locale\Languages;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InfoStep extends WizardStep
{
    use LooksUpGuestArtists;

    public const STEP = ReleaseValidator::STEP_INFO;

    /**
     * Livewire özelliği → ReleaseValidator hata anahtarı.
     */
    private const FIELDS = [
        'type' => 'type',
        'title' => 'title',
        'version' => 'version',
        'primaryArtists' => 'primary_artists',
        'featuring' => 'featuring',
        'labelName' => 'label_name',
        'genreId' => 'genre_id',
        'subgenreId' => 'subgenre_id',
        'language' => 'language',
        'releaseDate' => 'release_date',
        'originalReleaseDate' => 'original_release_date',
        'pLine' => 'p_line',
        'cLine' => 'c_line',
        'upc' => 'upc',
        'explicit' => 'explicit',
    ];

    public string $type = '';

    public string $title = '';

    public string $version = '';

    /**
     * @var list<string>
     */
    public array $primaryArtists = [];

    public string $addPrimary = '';

    /**
     * @var list<array<string, mixed>>
     */
    public array $featuring = [];

    public string $labelName = '';

    public string $genreId = '';

    public string $subgenreId = '';

    public string $language = '';

    public string $releaseDate = '';

    public string $originalReleaseDate = '';

    public string $pLine = '';

    public string $cLine = '';

    public string $upc = '';

    public bool $explicit = false;

    public function mount(Release $release): void
    {
        $this->useRelease($release);
        $release->load(['artists.artist']);

        $this->type = (string) $release->type?->value;
        $this->title = (string) $release->title;
        $this->version = (string) $release->version;
        $this->primaryArtists = $release->artists
            ->where('role', ArtistRole::Primary->value)
            ->filter(fn ($credit): bool => $credit->artist !== null && ! $credit->artist->trashed())
            ->map(fn ($credit): string => $credit->artist->ulid)
            ->values()
            ->all();
        $this->featuring = ArtistCredits::featuringForm($release->artists);
        $this->labelName = (string) $release->label_name;
        $this->genreId = (string) $release->genre_id;
        $this->subgenreId = (string) $release->subgenre_id;
        $this->language = (string) $release->language;
        $this->releaseDate = (string) $release->release_date?->format('Y-m-d');
        $this->originalReleaseDate = (string) $release->original_release_date?->format('Y-m-d');
        $this->pLine = (string) $release->p_line;
        $this->cLine = (string) $release->c_line;
        $this->upc = (string) $release->upc;
        $this->explicit = $release->explicit;
    }

    public function updated(string $property): void
    {
        $root = Str::before($property, '.');

        if (($guest = $this->guestNameIndex($property)) !== null) {
            $this->lookupGuest($guest);
        }

        if (! isset(self::FIELDS[$root])) {
            return;
        }

        $this->touch(self::FIELDS[$root]);
        $release = $this->release();

        match ($root) {
            'primaryArtists', 'featuring' => $this->saveArtists(),
            'type' => $release->type = ReleaseType::tryFrom($this->type),
            'title' => $release->title = $this->clean($this->title, 200),
            'version' => $release->version = $this->clean($this->version, 120),
            'labelName' => $this->saveLabel(),
            'genreId', 'subgenreId' => $this->saveGenre(),
            'language' => $release->language = in_array($this->language, Languages::codes(), true) ? $this->language : null,
            'releaseDate' => $release->release_date = $this->date($this->releaseDate),
            'originalReleaseDate' => $release->original_release_date = $this->date($this->originalReleaseDate),
            'pLine' => $release->p_line = $this->clean($this->pLine, 200),
            'cLine' => $release->c_line = $this->clean($this->cLine, 200),
            'upc' => $this->saveUpc(),
            'explicit' => $release->explicit = $this->explicit,
            default => null,
        };

        if ($release->isDirty()) {
            $release->save();
        }

        $this->markSaved();
    }

    public function addPrimaryArtist(): void
    {
        if ($this->addPrimary === '' || in_array($this->addPrimary, $this->primaryArtists, true)) {
            return;
        }

        $this->primaryArtists[] = $this->addPrimary;
        $this->addPrimary = '';
        $this->updated('primaryArtists');
    }

    public function removePrimaryArtist(string $ulid): void
    {
        $this->primaryArtists = array_values(array_diff($this->primaryArtists, [$ulid]));
        $this->updated('primaryArtists');
    }

    public function addFeaturing(string $kind): void
    {
        if (! in_array($kind, [ArtistCredits::KIND_PROFILE, ArtistCredits::KIND_GUEST], true)) {
            return;
        }

        $this->featuring[] = ['kind' => $kind, 'artist' => null, 'name' => '', 'spotify' => '', 'apple' => ''];
    }

    protected function guestEntries(): array
    {
        return $this->featuring;
    }

    protected function storeGuestEntries(array $entries): void
    {
        $this->featuring = $entries;
        $this->updated('featuring');
    }

    public function removeFeaturing(int $index): void
    {
        unset($this->featuring[$index]);
        $this->featuring = array_values($this->featuring);
        $this->guestLookup = [];
        $this->clearInputError('featuring');
        $this->updated('featuring');
    }

    private function saveArtists(): void
    {
        $this->primaryArtists = array_values(array_filter($this->primaryArtists, 'is_string'));
        $this->featuring = array_values(array_filter($this->featuring, 'is_array'));
        $credits = ArtistCredits::resolve(auth()->user(), $this->primaryArtists, $this->featuring);

        $this->clearInputError('featuring');
        $this->clearInputError('primary_artists');
        $this->inputErrors = array_merge($this->inputErrors, $credits->errors);

        $release = $this->release();

        DB::transaction(function () use ($release, $credits): void {
            $release->artists()->delete();
            $release->artists()->createMany($credits->rows);
        });
    }

    private function saveLabel(): void
    {
        $release = $this->release();

        if (auth()->user()->account_type !== AccountType::Label) {
            $this->labelName = (string) $release->label_name;

            return;
        }

        $release->label_name = (string) $this->clean($this->labelName, 150);
    }

    private function saveGenre(): void
    {
        $release = $this->release();
        $genre = Genre::query()->active()->topLevel()->find((int) $this->genreId);
        $subgenre = $genre ? $genre->children()->active()->find((int) $this->subgenreId) : null;

        $release->genre_id = $genre?->id;
        $release->subgenre_id = $subgenre?->id;
        $this->genreId = (string) $genre?->id;
        $this->subgenreId = (string) $subgenre?->id;
    }

    private function saveUpc(): void
    {
        $digits = preg_replace('/\D/', '', $this->upc);
        $this->clearInputError('upc');

        if (strlen($digits) > 13) {
            $this->inputErrors['upc'] = __('release.validation.upc_format', ['length' => strlen($digits)]);

            return;
        }

        $this->upc = $digits;
        $this->release()->upc = $digits !== '' ? $digits : null;
    }

    private function clean(string $value, int $max): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function date(string $value): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value && $date->year >= 1900 && $date->year <= 2100 ? $date : null;
    }

    public function render(): View
    {
        $validator = app(ReleaseValidator::class);
        $this->forgetRelease();
        $release = $this->release();
        $summary = $this->exposeErrors($validator->step($release, self::STEP));

        $profiles = auth()->user()->artists()->get();
        $genres = Genre::query()->active()->topLevel()->orderBy('sort')->orderBy('name')->get();
        $selectedGenre = $genres->firstWhere('id', (int) $this->genreId);
        $earliest = $validator->earliestReleaseDate();
        $year = now()->year;

        return view('livewire.releases.wizard.info-step', [
            'release' => $release,
            'summary' => $summary,
            'types' => ReleaseType::cases(),
            'profiles' => $profiles,
            'profilesByUlid' => $profiles->keyBy('ulid'),
            'genres' => $genres,
            'subgenres' => $selectedGenre ? $selectedGenre->children()->active()->get() : collect(),
            'languages' => Languages::options(),
            'labelLocked' => auth()->user()->account_type !== AccountType::Label,
            'defaultLabel' => config('hova.default_label'),
            'earliest' => $earliest,
            'earliestText' => Format::longDate($earliest),
            'lineExample' => $year.' '.($this->labelName ?: config('hova.default_label')),
        ]);
    }
}
