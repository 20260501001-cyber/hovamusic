<?php

use App\Domain\Releases\SpotifyReleaseTracker;
use App\Domain\Spotify\FakeSpotifyCatalog;
use App\Domain\Spotify\SpotifyAlbum;
use App\Domain\Spotify\SpotifyCatalog;
use App\Domain\Spotify\SpotifyTrack;
use App\Enums\AdminRole;
use App\Enums\ReleaseStatus;
use App\Enums\SpotifyMatchStatus;
use App\Models\Admin;
use App\Models\Platform;
use App\Models\Release;
use App\Models\SpotifyMatch;
use App\Notifications\ReleaseStatusChanged;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->catalog = app(SpotifyCatalog::class);
    Platform::query()->firstOrCreate(['slug' => 'spotify'], ['name' => 'Spotify', 'is_active' => true]);
});

function deliveredRelease(array $attributes = []): Release
{
    $release = Release::factory()->complete()->create(['upc' => '8721234567894', ...$attributes]);
    $release->tracks()->update(['isrc' => 'GXLM52600001']);
    $release->forceFill(['status' => ReleaseStatus::Delivered])->save();

    return $release->fresh();
}

it('looks up delivered releases by UPC and tracks by ISRC every day', function () {
    expect($this->catalog)->toBeInstanceOf(FakeSpotifyCatalog::class);
    $release = deliveredRelease();
    Release::factory()->status(ReleaseStatus::Approved)->create(['upc' => '8721234567894']);

    $this->catalog
        ->addAlbum('8721234567894', new SpotifyAlbum('alb1', 'Gece Yarısı', 'https://open.spotify.com/album/alb1'))
        ->addTrack(new SpotifyTrack('trk1', 'Gece Yarısı', 'https://open.spotify.com/track/trk1', 'GXLM52600001', 'alb1', 'Gece Yarısı', 'https://open.spotify.com/album/alb1'));

    $this->artisan('hova:spotify-track')->assertSuccessful();

    $matches = SpotifyMatch::query()->orderBy('id')->get();
    expect($matches)->toHaveCount(2)
        ->and($matches->pluck('matched_by')->all())->toBe(['upc', 'isrc'])
        ->and($matches->pluck('release_id')->unique()->all())->toBe([$release->id])
        ->and($matches->every(fn (SpotifyMatch $m) => $m->status === SpotifyMatchStatus::Pending))->toBeTrue()
        ->and($release->fresh()->spotify_checked_at)->not->toBeNull()
        ->and($this->catalog->lookups)->toBe(2);

    $this->artisan('hova:spotify-track')->assertSuccessful();
    expect(SpotifyMatch::query()->count())->toBe(2);
});

it('runs the tracking job daily', function () {
    $this->artisan('schedule:list')->assertSuccessful();

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'hova:spotify-track'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 6 * * *');
});

it('adds the Spotify link and puts the release live with one click', function () {
    Notification::fake();
    $admin = Admin::factory()->withRole(AdminRole::ReviewEditor)->create();
    $release = deliveredRelease();
    $this->catalog
        ->addAlbum('8721234567894', new SpotifyAlbum('alb1', 'Gece Yarısı', 'https://open.spotify.com/album/alb1'))
        ->addTrack(new SpotifyTrack('trk1', 'Gece Yarısı', 'https://open.spotify.com/track/trk1', 'GXLM52600001', 'alb1', 'Gece Yarısı', 'https://open.spotify.com/album/alb1'));
    app(SpotifyReleaseTracker::class)->check($release);

    app(SpotifyReleaseTracker::class)->accept(SpotifyMatch::query()->where('matched_by', 'upc')->sole(), $admin);

    $release->refresh();
    expect($release->status)->toBe(ReleaseStatus::Live)
        ->and($release->spotify_album_id)->toBe('alb1')
        ->and($release->storeLinks()->sole()->url)->toBe('https://open.spotify.com/album/alb1')
        ->and($release->tracks()->sole()->spotify_track_id)->toBe('trk1')
        ->and(SpotifyMatch::query()->pluck('status')->unique()->all())->toBe([SpotifyMatchStatus::Accepted]);

    Notification::assertSentTo($release->user, ReleaseStatusChanged::class, fn (ReleaseStatusChanged $n) => $n->log->to_status === ReleaseStatus::Live);
});

it('does not suggest a dismissed album again', function () {
    $admin = Admin::factory()->withRole(AdminRole::ReviewEditor)->create();
    $release = deliveredRelease();
    $this->catalog->addAlbum('8721234567894', new SpotifyAlbum('wrong', 'Başka Albüm', 'https://open.spotify.com/album/wrong'));
    $tracker = app(SpotifyReleaseTracker::class);

    $tracker->check($release);
    $tracker->dismiss(SpotifyMatch::query()->sole(), $admin);

    expect($tracker->check($release->fresh()))->toBe(0)
        ->and(SpotifyMatch::query()->sole()->status)->toBe(SpotifyMatchStatus::Dismissed)
        ->and($release->fresh()->status)->toBe(ReleaseStatus::Delivered);
});
