<?php

namespace App\Filament\Resources\Releases\Pages;

use App\Domain\Media\MediaUrl;
use App\Filament\Resources\Releases\Actions\ReleaseActions;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Models\MediaFile;
use App\Models\Release;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * @property Release $record
 */
class ViewRelease extends ViewRecord
{
    protected static string $resource = ReleaseResource::class;

    public const RELATIONS = [
        'user', 'cover', 'genre', 'subgenre', 'platforms', 'artists',
        'tracks.audio', 'tracks.artists', 'tracks.credits', 'tracks.release.artists',
        'statusLogs.admin', 'statusLogs.template', 'requests.handler',
        'spotifyMatches.track', 'storeLinks.platform',
    ];

    public function getTitle(): string
    {
        return $this->record->displayTitle();
    }

    public function getSubheading(): ?string
    {
        return $this->record->artistLine() ?: null;
    }

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(self::RELATIONS);
    }

    /**
     * Livewire sonraki isteklerde modeli ilişkileri olmadan yeniden yükler; detay
     * sayfası her istekte aynı ilişkilerle çizilir.
     */
    public function hydrate(): void
    {
        $this->record->loadMissing(self::RELATIONS);
    }

    protected function getHeaderActions(): array
    {
        return [
            ...ReleaseActions::transitions(),
            ...ReleaseActions::takedownDecision(),
            ReleaseActions::acceptSpotify(),
            ActionGroup::make([
                EditAction::make()->label('Metadata\'yı düzenle'),
                ReleaseActions::downloadAll(),
                ReleaseActions::assignIsrc(),
            ])->label('Diğer')->icon('lucide-ellipsis')->button()->color('gray'),
        ];
    }

    /**
     * Yayına ait kapak ya da ses dosyası için tıklama anında imzalı, süreli indirme
     * adresi üretir. İndirme MediaController'da audit log'a yazılır.
     */
    public function downloadMedia(string $ulid): void
    {
        Gate::forUser(auth('admin')->user())->authorize('download', $this->record);

        $ids = $this->record->tracks->pluck('audio_file_id')->push($this->record->cover_media_id)->filter()->all();
        $media = MediaFile::query()->where('ulid', $ulid)->whereIn('id', $ids)->first();

        abort_if($media === null, 404);

        $this->redirect(MediaUrl::temporary($media, download: true));
    }

    /**
     * İşlem sonrası ilişkiler yeniden yüklenir; durum, geçmiş ve öneriler güncel görünür.
     */
    public function refreshRecord(): void
    {
        $this->record = $this->record->fresh()->load(self::RELATIONS);
    }

    protected function afterActionCalled(Action $action): void
    {
        $this->refreshRecord();
    }
}
