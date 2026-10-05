<?php

namespace App\Filament\Resources\ReportImports\Pages;

use App\Filament\Resources\ReportImports\ReportImportActions;
use App\Filament\Resources\ReportImports\ReportImportResource;
use App\Models\ReportImport;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @property ReportImport $record
 */
class ViewReportImport extends ViewRecord
{
    protected static string $resource = ReportImportResource::class;

    public function getTitle(): string
    {
        return $this->record->original_name;
    }

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(['mapping', 'uploader', 'approver']);
    }

    public function hydrate(): void
    {
        $this->record->loadMissing(['mapping', 'uploader', 'approver']);
    }

    protected function getHeaderActions(): array
    {
        return [
            ReportImportActions::approve(),
            ReportImportActions::recalculate(),
            ReportImportActions::reprocess(),
            ReportImportActions::reverse(),
            ReportImportActions::discard(),
        ];
    }
}
