<?php

namespace App\Livewire\Releases\Wizard;

use App\Domain\Isrc\IsrcAllocator;
use App\Domain\Media\ChunkedUploads;
use App\Domain\Releases\ArtistCredits;
use App\Domain\Releases\ReleaseValidator;
use App\Enums\ArtistRole;
use App\Enums\CreditRole;
use App\Enums\IsrcSource;
use App\Enums\MediaStatus;
use App\Enums\UploadStatus;
use App\Livewire\Concerns\LooksUpGuestArtists;
use App\Models\Release;
use App\Models\Track;
use App\Models\UploadSession;
use App\Rules\Isrc;
use App\Support\Format;
use App\Support\Locale\Languages;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

class TracksStep extends WizardStep
{
    use LooksUpGuestArtists;

    public const STEP = ReleaseValidator::STEP_TRACKS;

    private const MAX_CREDITS = 20;

    private const CREDIT_FIELDS = [
        'composers' => CreditRole::Composer,
        'lyricists' => CreditRole::Lyricist,
        'producers' => CreditRole::Producer,
    ];

    #[Locked]
    public ?string $editing = null;

    /**
     * Açık parçanın formu.
     *
     * @var array<string, mixed>
     */
    public array $form = [];

    public string $announcement = '';

    public function mount(Release $release): void
    {
        $this->useRelease($release);
    }

    public function addTrack(): void
    {
        $release = $this->release();
        $track = $release->tracks()->create([
            'position' => (int) $release->tracks()->max('position') + 1,
            'language' => $release->language,
        ]);

        $this->markSaved();
        $this->edit($track->ulid);
    }

    public function edit(string $ulid): void
    {
        $track = $this->track($ulid)->load(['artists.artist', 'credits']);

        $this->editing = $track->ulid;
        $this->inputErrors = [];
        $this->guestLookup = [];
        $this->touched = array_filter($this->touched, fn (string $key): bool => ! str_starts_with($key, 'form.'), ARRAY_FILTER_USE_KEY);
        $this->form = [
            'title' => (string) $track->title,
            'version' => (string) $track->version,
            'isrc' => (string) $track->formattedIsrc(),
            'has_own_isrc' => $track->has_own_isrc || (filled($track->isrc) && $track->isrc_source !== IsrcSource::Hova) ? '1' : '0',
            'explicit' => $track->explicit,
            'language' => (string) $track->language,
            'preview_start_sec' => (string) $track->preview_start_sec,
            'lyrics' => (string) $track->lyrics,
            'featuring' => ArtistCredits::featuringForm($track->artists),
            'composers' => $track->creditNames(CreditRole::Composer) ?: [''],
            'lyricists' => $track->creditNames(CreditRole::Lyricist) ?: [''],
            'producers' => $track->creditNames(CreditRole::Producer),
        ];
    }

    public function close(): void
    {
        $this->editing = null;
        $this->form = [];
        $this->inputErrors = [];
        $this->guestLookup = [];
    }

    public function updatedForm(mixed $value, string $key): void
    {
        if ($this->editing === null) {
            return;
        }

        $root = Str::before($key, '.');
        $this->touch("form.{$root}");

        if (($guest = $this->guestNameIndex($key)) !== null) {
            $this->lookupGuest($guest);
        }

        $track = $this->track($this->editing);

        match ($root) {
            'title' => $track->title = $this->clean($this->form['title'] ?? '', 200),
            'version' => $track->version = $this->clean($this->form['version'] ?? '', 120),
            'isrc' => $this->saveIsrc($track),
            'has_own_isrc' => $this->saveIsrcChoice($track),
            'explicit' => $track->explicit = (bool) ($this->form['explicit'] ?? false),
            'language' => $track->language = in_array($this->form['language'] ?? null, Languages::codes(), true) ? $this->form['language'] : null,
            'preview_start_sec' => $track->preview_start_sec = min(65535, max(0, (int) ($this->form['preview_start_sec'] ?? 0))),
            'lyrics' => $track->lyrics = $this->cleanText($this->form['lyrics'] ?? '', 20000),
            'featuring' => $this->saveFeaturing($track),
            'composers', 'lyricists', 'producers' => $this->saveCredits($track, $root),
            default => null,
        };

        if ($track->isDirty()) {
            $track->save();
        }

        $this->markSaved();
    }

    public function addCredit(string $field): void
    {
        if ($this->editing === null || ! isset(self::CREDIT_FIELDS[$field])) {
            return;
        }

        $entries = $this->form[$field] ?? [];

        if (count($entries) < self::MAX_CREDITS) {
            $entries[] = '';
        }

        $this->form[$field] = array_values($entries);
    }

    public function removeCredit(string $field, int $index): void
    {
        if ($this->editing === null || ! isset(self::CREDIT_FIELDS[$field])) {
            return;
        }

        $entries = $this->form[$field] ?? [];
        unset($entries[$index]);
        $this->form[$field] = array_values($entries);
        $this->updatedForm(null, $field);
    }

    public function addFeaturing(string $kind): void
    {
        if ($this->editing === null || ! in_array($kind, [ArtistCredits::KIND_PROFILE, ArtistCredits::KIND_GUEST], true)) {
            return;
        }

        $this->form['featuring'][] = ['kind' => $kind, 'artist' => null, 'name' => '', 'spotify' => '', 'apple' => ''];
    }

    protected function guestEntries(): array
    {
        return $this->editing === null ? [] : array_values($this->form['featuring'] ?? []);
    }

    protected function storeGuestEntries(array $entries): void
    {
        if ($this->editing === null) {
            return;
        }

        $this->form['featuring'] = $entries;
        $this->updatedForm(null, 'featuring');
    }

    public function removeFeaturing(int $index): void
    {
        if ($this->editing === null) {
            return;
        }

        $entries = $this->form['featuring'] ?? [];
        unset($entries[$index]);
        $this->form['featuring'] = array_values($entries);
        $this->guestLookup = [];
        $this->updatedForm(null, 'featuring');
    }

    /**
     * Sürükle-bırak: $position yeni sıradır (0'dan başlar).
     */
    public function reorder(string $ulid, int $position): void
    {
        $tracks = $this->release()->tracks()->get();
        $moving = $tracks->firstWhere('ulid', $ulid);

        if ($moving === null) {
            return;
        }

        $ordered = $tracks->reject(fn (Track $track): bool => $track->is($moving))->values();
        $ordered->splice(max(0, min($position, $ordered->count())), 0, [$moving]);

        $this->renumber($ordered);
        $this->announce($moving->fresh());
    }

    /**
     * Klavye ile taşıma: tutamaç odaktayken yukarı/aşağı ok.
     */
    public function move(string $ulid, int $direction): void
    {
        $tracks = $this->release()->tracks()->get()->values();
        $index = $tracks->search(fn (Track $track): bool => $track->ulid === $ulid);

        if ($index === false) {
            return;
        }

        $target = $index + ($direction < 0 ? -1 : 1);

        if ($target < 0 || $target >= $tracks->count()) {
            return;
        }

        $this->reorder($ulid, $target);
        $this->dispatch('track-moved', ulid: $ulid);
    }

    public function deleteTrack(string $ulid): void
    {
        $track = $this->track($ulid)->load('audio');
        $audio = $track->audio;

        DB::transaction(function () use ($track): void {
            UploadSession::query()
                ->where('track_id', $track->id)
                ->where('status', UploadStatus::Uploading)
                ->get()
                ->each(fn (UploadSession $session) => app(ChunkedUploads::class)->cancel($session));
            $track->delete();
        });

        if ($audio && ! Track::query()->where('audio_file_id', $audio->id)->exists()) {
            $audio->deleteWithFile();
        }

        if ($this->editing === $ulid) {
            $this->close();
        }

        $this->renumber($this->release()->tracks()->get());
        $this->markSaved();
    }

    /**
     * Yükleyici dosyanın son parçasını gönderdiğinde çağırır.
     */
    public function audioUploaded(string $ulid): void
    {
        $this->track($ulid);
        $this->touch('form.audio');
        $this->markSaved();
    }

    private function track(string $ulid): Track
    {
        return $this->release()->tracks()->where('ulid', $ulid)->firstOrFail();
    }

    /**
     * @param  iterable<Track>  $tracks
     */
    private function renumber(iterable $tracks): void
    {
        DB::transaction(function () use ($tracks): void {
            $position = 1;

            foreach ($tracks as $track) {
                if ($track->position !== $position) {
                    $track->forceFill(['position' => $position])->save();
                }

                $position++;
            }
        });

        $this->markSaved();
    }

    private function announce(Track $track): void
    {
        $this->announcement = __('release.tracks.moved', [
            'title' => $track->displayTitle(),
            'position' => $track->position,
        ]);
    }

    /**
     * "ISRC kodum var / yok" seçimi. Hova Music'in atadığı kod değiştirilemez.
     */
    private function saveIsrcChoice(Track $track): void
    {
        if ($track->isrc_source === IsrcSource::Hova) {
            return;
        }

        $own = (bool) (int) ($this->form['has_own_isrc'] ?? 0);
        $track->has_own_isrc = $own;
        $this->clearInputError('form.isrc');

        if (! $own) {
            $track->isrc = null;
            $track->isrc_source = null;
            $this->form['isrc'] = '';
        }
    }

    private function saveIsrc(Track $track): void
    {
        if ($track->isrc_source === IsrcSource::Hova) {
            return;
        }

        $raw = trim((string) ($this->form['isrc'] ?? ''));
        $this->clearInputError('form.isrc');

        if ($raw === '') {
            $track->isrc = null;
            $track->isrc_source = null;

            return;
        }

        $isrc = Isrc::normalize($raw);
        $allocator = app(IsrcAllocator::class);

        if ($isrc === null) {
            $this->inputErrors['form.isrc'] = __('release.validation.isrc_format');

            return;
        }

        if ($allocator->isReserved($isrc)) {
            $this->inputErrors['form.isrc'] = __('isrc.reserved', ['prefix' => $allocator->registrant()]);

            return;
        }

        $track->forceFill(['isrc' => $isrc, 'isrc_source' => IsrcSource::User, 'has_own_isrc' => true]);
        $this->form['isrc'] = $track->formattedIsrc();
        $this->form['has_own_isrc'] = '1';
    }

    private function saveFeaturing(Track $track): void
    {
        $entries = array_values(array_filter($this->form['featuring'] ?? [], 'is_array'));
        $this->form['featuring'] = $entries;
        $credits = ArtistCredits::resolve(auth()->user(), [], $entries);

        $this->clearInputError('featuring');
        $this->inputErrors = array_merge($this->inputErrors, $credits->errors);

        DB::transaction(function () use ($track, $credits): void {
            $track->artists()->delete();
            $track->artists()->createMany($credits->featuringRows());
        });
    }

    private function saveCredits(Track $track, string $field): void
    {
        $role = self::CREDIT_FIELDS[$field];
        $entries = array_slice(array_values(array_filter($this->form[$field] ?? [], 'is_string')), 0, self::MAX_CREDITS);
        $this->form[$field] = $entries;

        $names = collect($entries)
            ->map(fn (string $name): ?string => $this->clean($name, 150))
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($track, $role, $names): void {
            $track->credits()->where('role', $role->value)->delete();
            $track->credits()->createMany(
                $names->map(fn (string $name, int $i): array => ['role' => $role->value, 'name' => $name, 'position' => $i])->all(),
            );
        });
    }

    private function clean(string $value, int $max): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function cleanText(string $value, int $max): ?string
    {
        $value = trim(str_replace("\r\n", "\n", $value));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    public function render(): View
    {
        $validator = app(ReleaseValidator::class);
        $this->forgetRelease();
        $release = $this->release()->load(['artists.artist', 'tracks.audio', 'tracks.credits', 'tracks.artists']);
        $issues = $validator->step($release, self::STEP);
        $editingTrack = $this->editing ? $release->tracks->firstWhere('ulid', $this->editing) : null;

        if ($this->editing !== null && $editingTrack === null) {
            $this->close();
        }

        $fieldIssues = $editingTrack
            ? collect($validator->trackIssues($editingTrack, final: false))->mapWithKeys(fn (string $message, string $field): array => ["form.{$field}" => Str::ucfirst($message)])->all()
            : [];

        $summary = $this->exposeErrors($issues, $fieldIssues);
        $settings = app(Settings::class);
        $trackIssueCount = collect($issues)->keys()
            ->filter(fn (string $key): bool => str_starts_with($key, 'track.'))
            ->map(fn (string $key): string => explode('.', $key)[1])
            ->countBy();

        return view('livewire.releases.wizard.tracks-step', [
            'release' => $release,
            'summary' => $summary,
            'tracks' => $release->tracks,
            'editingTrack' => $editingTrack,
            'profiles' => auth()->user()->artists()->get(),
            'languages' => Languages::options(),
            'primaryLine' => $release->artists->where('role', ArtistRole::Primary->value)->pluck('name')->implode(', '),
            'primaryUlids' => $release->artists
                ->where('role', ArtistRole::Primary->value)
                ->map(fn ($credit): ?string => $credit->artist?->ulid)
                ->filter()
                ->values()
                ->all(),
            'trackIssueCount' => $trackIssueCount,
            'hasPending' => $release->tracks->contains(fn (Track $track): bool => $track->audio?->validation_status === MediaStatus::Pending),
            'audioMax' => $settings->audioMaxBytes(),
            'audioMaxText' => Format::bytes($settings->audioMaxBytes()),
            'uploadMessages' => [
                'extension' => __('media.audio.extension'),
                'no_extension' => __('media.no_extension'),
                'empty' => __('media.audio.empty'),
                'too_large' => __('media.audio.too_large'),
                'not_audio' => __('media.audio.not_audio'),
                'failed' => __('media.upload.failed'),
                'uploading_meta' => __('release.tracks.audio.uploading_meta'),
            ],
        ]);
    }
}
