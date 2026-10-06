<?php

use App\Enums\CreditRole;
use App\Enums\ReleaseStatus;
use App\Enums\ReleaseType;
use App\Livewire\Releases\Wizard\CoverStep;
use App\Livewire\Releases\Wizard\InfoStep;
use App\Livewire\Releases\Wizard\ReviewStep;
use App\Livewire\Releases\Wizard\StoresStep;
use App\Livewire\Releases\Wizard\TracksStep;
use App\Models\Artist;
use App\Models\Consent;
use App\Models\Genre;
use App\Models\MediaFile;
use App\Models\Platform;
use App\Models\Release;
use App\Models\Track;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('private');
    $this->user = User::factory()->create();
    activePlan($this->user);
    $this->actingAs($this->user);
});

describe('info step', function () {
    it('saves each field as soon as it changes', function () {
        $artist = Artist::factory()->for($this->user)->create(['name' => 'Deniz Yılmaz']);
        $genre = Genre::query()->create(['name' => 'Pop', 'is_active' => true]);
        $release = Release::factory()->for($this->user)->create(['type' => null, 'title' => null]);

        Livewire::test(InfoStep::class, ['release' => $release])
            ->set('type', 'ep')
            ->set('title', '  Gece   Yarısı ')
            ->set('addPrimary', $artist->ulid)
            ->call('addPrimaryArtist')
            ->set('genreId', (string) $genre->id)
            ->set('language', 'tr')
            ->set('upc', '0360 0029 1452')
            ->assertSet('saved', true);

        $release->refresh()->load('artists');

        expect($release->type)->toBe(ReleaseType::Ep)
            ->and($release->title)->toBe('Gece Yarısı')
            ->and($release->artistLine())->toBe('Deniz Yılmaz')
            ->and($release->genre_id)->toBe($genre->id)
            ->and($release->upc)->toBe('036000291452');
    });

    it('keeps the label locked for artist accounts', function () {
        $release = Release::factory()->for($this->user)->create();

        Livewire::test(InfoStep::class, ['release' => $release])->set('labelName', 'Başka Label');

        expect($release->fresh()->label_name)->toBe('Hova Music');
    });

    it('lets label accounts set their own label name', function () {
        $label = User::factory()->label()->create();
        $this->actingAs($label);
        $release = Release::factory()->for($label)->create();

        Livewire::test(InfoStep::class, ['release' => $release])->set('labelName', 'Gece Plak');

        expect($release->fresh()->label_name)->toBe('Gece Plak');
    });

    it('saves featuring artists from profiles and as guests', function () {
        $main = Artist::factory()->for($this->user)->create(['name' => 'Ana']);
        $friend = Artist::factory()->for($this->user)->create(['name' => 'Arkadaş']);
        $release = Release::factory()->for($this->user)->create();

        Livewire::test(InfoStep::class, ['release' => $release])
            ->set('addPrimary', $main->ulid)
            ->call('addPrimaryArtist')
            ->call('addFeaturing', 'profile')
            ->set('featuring.0.artist', $friend->ulid)
            ->call('addFeaturing', 'guest')
            ->set('featuring.1.name', 'Konuk Sanatçı')
            ->set('featuring.1.spotify', 'https://open.spotify.com/artist/0OdUWJ0sBjDrqHygGUXeCF')
            ->assertHasNoErrors();

        $release->refresh()->load('artists');

        expect($release->artistLine())->toBe('Ana feat. Arkadaş, Konuk Sanatçı')
            ->and($release->artists->firstWhere('name', 'Konuk Sanatçı')->spotify_artist_id)->toBe('0OdUWJ0sBjDrqHygGUXeCF')
            ->and($release->artists->firstWhere('name', 'Konuk Sanatçı')->artist_id)->toBeNull()
            ->and($this->user->artists()->count())->toBe(2);
    });

    it('explains an unreadable guest link', function () {
        $release = Release::factory()->for($this->user)->create();

        Livewire::test(InfoStep::class, ['release' => $release])
            ->call('addFeaturing', 'guest')
            ->set('featuring.0.name', 'Konuk')
            ->set('featuring.0.spotify', 'https://example.com/konuk')
            ->assertHasErrors(['featuring.0.spotify']);
    });

    it('ignores profiles that belong to another account', function () {
        $foreign = Artist::factory()->create();
        $release = Release::factory()->for($this->user)->create();

        Livewire::test(InfoStep::class, ['release' => $release])
            ->set('primaryArtists', [$foreign->ulid])
            ->assertHasErrors(['primary_artists']);

        expect($release->artists()->count())->toBe(0);
    });

    it('shows what is missing and stays on the step', function () {
        $release = Release::factory()->for($this->user)->create(['title' => null]);

        Livewire::test(InfoStep::class, ['release' => $release])
            ->call('next')
            ->assertNoRedirect()
            ->assertSee('Devam etmeden önce şunları tamamla:')
            ->assertSee('Yayın adını yaz.');
    });

    it('moves on once the step is complete', function () {
        $release = Release::factory()->complete()->create();
        $this->actingAs($release->user);

        Livewire::test(InfoStep::class, ['release' => $release])
            ->call('next')
            ->assertRedirect(route('panel.releases.edit', ['release' => $release->ulid, 'step' => 2]));

        expect($release->fresh()->wizard_step)->toBe(2);
    });

    it('refuses a release that is under review', function () {
        $release = Release::factory()->for($this->user)->status(ReleaseStatus::InReview)->create();

        Livewire::test(InfoStep::class, ['release' => $release])->assertForbidden();
    });
});

describe('cover step', function () {
    it('stores a valid cover without its metadata', function () {
        $release = Release::factory()->for($this->user)->create();
        $file = UploadedFile::fake()->createWithContent('kapak.jpg', file_get_contents(makeJpeg(exif: 'HOVA-GIZLI-KONUM')));

        Livewire::test(CoverStep::class, ['release' => $release])
            ->set('upload', $file)
            ->assertSet('coverErrors', []);

        $cover = $release->fresh()->cover;

        expect($cover)->not->toBeNull()
            ->and($cover->width)->toBe(3000)
            ->and($cover->color_space)->toBe('RGB')
            ->and($cover->original_name)->toBe('kapak.jpg');
        Storage::disk('private')->assertExists($cover->path);
        expect(Storage::disk('private')->get($cover->path))->not->toContain('HOVA-GIZLI-KONUM')
            ->and(str_starts_with($cover->path, 'covers/'.$this->user->ulid.'/'))->toBeTrue();
    });

    it('reports the measured values of a rejected cover', function (Closure $make, string $name, string $message) {
        $release = Release::factory()->for($this->user)->create();

        Livewire::test(CoverStep::class, ['release' => $release])
            ->set('upload', UploadedFile::fake()->createWithContent($name, file_get_contents($make())))
            ->assertSet('coverErrors', [$message])
            ->assertSee($message);

        expect($release->fresh()->cover_media_id)->toBeNull();
    })->with([
        'small' => [fn () => makeJpeg(2000, 2000), 'kucuk.jpg', 'Kapak 2000×2000 px; 3000×3000 px olmalı.'],
        'not square' => [fn () => makeJpeg(3000, 2400), 'dikdortgen.jpg', 'Kapak 3000×2400 px ve kare değil; 3000×3000 px olmalı.'],
        'grayscale' => [fn () => makeGrayscalePng(), 'gri.png', 'Kapak gri tonlamalı renk uzayında; RGB olmalı.'],
        'not an image' => [function () {
            $path = tempnam(sys_get_temp_dir(), 'hm-');
            file_put_contents($path, 'bu bir resim değil');

            return $path;
        }, 'sahte.jpg', 'Dosya JPG ya da PNG değil; kapak JPG veya PNG olmalı.'],
    ]);

    it('does not accept a cover without an active plan', function () {
        $user = User::factory()->create();
        $release = Release::factory()->for($user)->create();
        $this->actingAs($user);

        Livewire::test(CoverStep::class, ['release' => $release])
            ->set('upload', UploadedFile::fake()->createWithContent('kapak.jpg', file_get_contents(makeJpeg())))
            ->assertSet('coverErrors', [__('plans.gate.no_plan_upload')]);

        expect($release->fresh()->cover_media_id)->toBeNull();
    });

    it('measures the size limit from the admin setting', function () {
        app(Settings::class)->put(['cover_max_mb' => 1]);
        $release = Release::factory()->for($this->user)->create();
        $path = makeJpeg();
        file_put_contents($path, str_repeat("\0", 1024 * 1024), FILE_APPEND);

        Livewire::test(CoverStep::class, ['release' => $release])
            ->set('upload', UploadedFile::fake()->createWithContent('buyuk.jpg', file_get_contents($path)))
            ->assertSet('coverErrors', fn (array $errors) => str_contains($errors[0], 'en fazla 1 MB olmalı'));
    });

    it('replaces the previous cover and deletes its file', function () {
        $release = Release::factory()->for($this->user)->create();
        $component = Livewire::test(CoverStep::class, ['release' => $release])
            ->set('upload', UploadedFile::fake()->createWithContent('ilk.jpg', file_get_contents(makeJpeg())));
        $first = $release->fresh()->cover;

        $component->set('upload', UploadedFile::fake()->createWithContent('ikinci.jpg', file_get_contents(makeJpeg())));

        expect($release->fresh()->cover->original_name)->toBe('ikinci.jpg')
            ->and(MediaFile::query()->find($first->id))->toBeNull();
        Storage::disk('private')->assertMissing($first->path);
    });
});

describe('tracks step', function () {
    it('adds a track and saves its details as they change', function () {
        $release = Release::factory()->for($this->user)->create(['language' => 'en']);

        $component = Livewire::test(TracksStep::class, ['release' => $release])->call('addTrack');
        $track = $release->tracks()->sole();

        $component
            ->assertSet('editing', $track->ulid)
            ->set('form.title', 'İlk Parça')
            ->set('form.isrc', 'tr-a1b-26-00001')
            ->assertSet('form.isrc', 'TR-A1B-26-00001')
            ->set('form.composers.0', 'Ali Veli')
            ->set('form.lyricists.0', 'Ayşe Fatma')
            ->call('addCredit', 'producers')
            ->set('form.producers.0', 'Prodüktör Kişi')
            ->set('form.explicit', true);

        $track->refresh()->load('credits');

        expect($track->title)->toBe('İlk Parça')
            ->and($track->language)->toBe('en')
            ->and($track->isrc)->toBe('TRA1B2600001')
            ->and($track->explicit)->toBeTrue()
            ->and($track->creditNames(CreditRole::Composer))->toBe(['Ali Veli'])
            ->and($track->creditNames(CreditRole::Lyricist))->toBe(['Ayşe Fatma'])
            ->and($track->creditNames(CreditRole::Producer))->toBe(['Prodüktör Kişi']);
    });

    it('rejects a malformed ISRC without overwriting the saved one', function () {
        $release = Release::factory()->for($this->user)->create();
        $track = Track::factory()->for($release)->create(['isrc' => 'TRA1B2600001']);

        Livewire::test(TracksStep::class, ['release' => $release])
            ->call('edit', $track->ulid)
            ->set('form.isrc', 'TR-123')
            ->assertHasErrors(['form.isrc']);

        expect($track->fresh()->isrc)->toBe('TRA1B2600001');
    });

    it('reorders tracks by dragging and with the keyboard', function () {
        $release = Release::factory()->for($this->user)->create();
        [$a, $b, $c] = collect(['A', 'B', 'C'])->map(fn ($title, $i) => Track::factory()->for($release)->create(['title' => $title, 'position' => $i + 1]))->all();

        $component = Livewire::test(TracksStep::class, ['release' => $release])->call('reorder', $c->ulid, 0);

        expect($release->tracks()->pluck('title')->all())->toBe(['C', 'A', 'B']);

        $component->call('move', $c->ulid, 1)
            ->assertSet('announcement', 'C şimdi 2. sırada.')
            ->assertDispatched('track-moved');

        expect($release->tracks()->pluck('title')->all())->toBe(['A', 'C', 'B']);
    });

    it('deletes a track and renumbers the rest', function () {
        $release = Release::factory()->for($this->user)->create();
        [$a, $b, $c] = collect(['A', 'B', 'C'])->map(fn ($title, $i) => Track::factory()->for($release)->create(['title' => $title, 'position' => $i + 1]))->all();

        Livewire::test(TracksStep::class, ['release' => $release])->call('deleteTrack', $b->ulid);

        expect($release->tracks()->get()->map(fn ($t) => $t->title.$t->position)->all())->toBe(['A1', 'C2']);
    });

    it('does not open tracks of another release', function () {
        $release = Release::factory()->for($this->user)->create();
        $foreign = Track::factory()->create();

        Livewire::test(TracksStep::class, ['release' => $release])
            ->call('edit', $foreign->ulid)
            ->assertNotFound();
    });
});

describe('stores step', function () {
    it('selects every active store on the first visit', function () {
        $active = Platform::query()->create(['name' => 'Spotify', 'is_active' => true]);
        Platform::query()->create(['name' => 'Kapalı Mağaza', 'is_active' => false]);
        $release = Release::factory()->for($this->user)->create(['wizard_step' => 4]);

        Livewire::test(StoresStep::class, ['release' => $release])
            ->assertSet('platforms', [(string) $active->id]);

        expect($release->platforms()->pluck('platforms.id')->all())->toBe([$active->id]);
    });

    it('saves country selections and ignores unknown codes', function () {
        $release = Release::factory()->for($this->user)->create(['wizard_step' => 4]);

        Livewire::test(StoresStep::class, ['release' => $release])
            ->set('territoryMode', 'exclude')
            ->set('territories', ['TR', 'XX', 'DE']);

        $release->refresh();
        expect($release->territory_mode->value)->toBe('exclude')
            ->and($release->territories)->toBe(['DE', 'TR']);
    });
});

describe('review step', function () {
    it('lists what is missing with links to the steps', function () {
        $release = Release::factory()->for($this->user)->create(['wizard_step' => 5]);

        Livewire::test(ReviewStep::class, ['release' => $release])
            ->assertSee('Göndermeden önce eksikleri tamamla.')
            ->assertSee(route('panel.releases.edit', ['release' => $release->ulid, 'step' => 2]));
    });

    it('requires the three declarations before submitting', function () {
        $release = planned(Release::factory()->complete()->create(['wizard_step' => 5]));
        $this->actingAs($release->user);

        Livewire::test(ReviewStep::class, ['release' => $release])
            ->set('declarations.hak-beyani-haklar', true)
            ->call('submit')
            ->assertNoRedirect()
            ->assertSee('Göndermek için üç hak beyanını da onayla.');

        expect($release->fresh()->status)->toBe(ReleaseStatus::Draft);
    });

    it('submits the release for review', function () {
        $release = planned(Release::factory()->complete()->create(['wizard_step' => 5]));
        $this->actingAs($release->user);

        Livewire::test(ReviewStep::class, ['release' => $release])
            ->assertSee('Yayın gönderilmeye hazır.')
            ->set('declarations.hak-beyani-haklar', true)
            ->set('declarations.hak-beyani-icerik', true)
            ->set('declarations.hak-beyani-bilgiler', true)
            ->call('submit')
            ->assertRedirect(route('panel.releases.show', ['release' => $release->ulid]));

        expect($release->fresh()->status)->toBe(ReleaseStatus::InReview)
            ->and(Consent::query()->where('context_id', $release->id)->count())->toBe(3);
    });
});
