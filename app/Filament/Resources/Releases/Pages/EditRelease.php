<?php

namespace App\Filament\Resources\Releases\Pages;

use App\Filament\Resources\Releases\ReleaseResource;
use App\Models\Release;
use App\Support\Audit\AuditLogger;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/**
 * @property Release $record
 */
class EditRelease extends EditRecord
{
    protected static string $resource = ReleaseResource::class;

    /**
     * @var list<int>
     */
    private array $platformsBefore = [];

    public function getTitle(): string
    {
        return 'Düzenle: '.$this->record->displayTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Detaya dön'),
        ];
    }

    protected function beforeSave(): void
    {
        $this->platformsBefore = $this->record->platforms()->pluck('platforms.id')->sort()->values()->all();
    }

    /**
     * Mağaza seçimi ara tabloda tutulduğu için model olayı oluşmaz; değişiklik ayrıca loglanır.
     */
    protected function afterSave(): void
    {
        $after = $this->record->platforms()->pluck('platforms.id')->sort()->values()->all();

        if ($after !== $this->platformsBefore) {
            app(AuditLogger::class)->record('releases.platforms_updated', $this->record, [
                'platforms' => ['old' => $this->platformsBefore, 'new' => $after],
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return ReleaseResource::getUrl('view', ['record' => $this->record]);
    }
}
