<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Media\MediaUrl;
use App\Domain\Releases\ReleaseRequests;
use App\Domain\Users\Impersonation;
use App\Enums\ReleaseStatus;
use App\Enums\RequestType;
use App\Http\Controllers\Controller;
use App\Livewire\Releases\Wizard\CoverStep;
use App\Livewire\Releases\Wizard\InfoStep;
use App\Livewire\Releases\Wizard\ReviewStep;
use App\Livewire\Releases\Wizard\StoresStep;
use App\Livewire\Releases\Wizard\TracksStep;
use App\Models\MediaFile;
use App\Models\Release;
use App\Models\Track;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReleaseController extends Controller
{
    public const STEPS = [
        1 => InfoStep::class,
        2 => CoverStep::class,
        3 => TracksStep::class,
        4 => StoresStep::class,
        5 => ReviewStep::class,
    ];

    public function index(Request $request): View
    {
        $releases = $request->user()->releases()
            ->with(['cover', 'artists'])
            ->latest('updated_at')
            ->paginate(20);

        return view('panel.releases.index', ['releases' => $releases]);
    }

    public function store(Request $request): RedirectResponse
    {
        $release = new Release(['label_name' => config('hova.default_label')]);
        $release->user()->associate($request->user());
        $release->save();

        return redirect()
            ->route('panel.releases.edit', ['release' => $release->ulid, 'step' => 1])
            ->with('flash', __('release.list.created'));
    }

    public function show(Release $release, ReleaseRequests $requests): View
    {
        Gate::authorize('view', $release);

        $release->load(['cover', 'artists', 'genre', 'subgenre', 'platforms', 'tracks.audio', 'tracks.artists', 'statusLogs', 'storeLinks.platform', 'requests']);

        return view('panel.releases.show', [
            'release' => $release,
            'coverUrl' => $release->cover?->isValid() ? MediaUrl::temporary($release->cover) : null,
            'storeLinks' => $release->status === ReleaseStatus::Live
                ? $release->storeLinks->sortBy(fn ($link) => [$link->platform->sort, $link->platform->name])->values()
                : collect(),
            'requestsVisible' => $release->requests->isNotEmpty() || in_array($release->status, ReleaseRequests::OPEN_STATUSES, true),
            'canCorrection' => $requests->canOpen($release, RequestType::Correction),
            'canTakedown' => $requests->canOpen($release, RequestType::Takedown),
        ]);
    }

    public function edit(Release $release, Impersonation $impersonation, int $step = 1): View|RedirectResponse
    {
        Gate::authorize('view', $release);

        if (! $release->isEditable()) {
            return redirect()->route('panel.releases.show', $release)->with('flash', __('release.show.locked'));
        }

        if ($impersonation->active()) {
            return redirect()->route('panel.releases.show', $release)->with('flash', __('panel.impersonation.blocked'));
        }

        $step = max(1, min($step, count(self::STEPS)));

        if ($step > $release->wizard_step) {
            return redirect()->route('panel.releases.edit', ['release' => $release->ulid, 'step' => $release->wizard_step]);
        }

        $note = $release->status === ReleaseStatus::NeedsChanges
            ? $release->statusLogs()->where('to_status', ReleaseStatus::NeedsChanges->value)->value('note')
            : null;

        return view('panel.releases.wizard', [
            'release' => $release,
            'step' => $step,
            'stepComponent' => self::STEPS[$step],
            'note' => $note,
        ]);
    }

    /**
     * Yalnızca hiç gönderilmemiş taslak silinir; dosyaları da silinir.
     */
    public function destroy(Release $release): RedirectResponse
    {
        Gate::authorize('delete', $release);

        $release->load(['cover', 'tracks.audio']);
        $media = collect([$release->cover])->merge($release->tracks->pluck('audio'))->filter();

        DB::transaction(fn () => $release->forceDelete());

        $media->each(function (MediaFile $file): void {
            $inUse = Release::withTrashed()->where('cover_media_id', $file->id)->exists()
                || Track::query()->where('audio_file_id', $file->id)->exists();

            if (! $inUse) {
                $file->deleteWithFile();
            }
        });

        return redirect()->route('panel.releases.index')->with('flash', __('release.list.deleted'));
    }
}
