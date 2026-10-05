<?php

namespace App\Filament\Resources\ReportImports\Pages;

use App\Filament\Resources\ReportImports\ReportImportActions;
use App\Filament\Resources\ReportImports\ReportImportResource;
use Filament\Resources\Pages\ListRecords;

class ListReportImports extends ListRecords
{
    protected static string $resource = ReportImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ReportImportActions::upload(),
        ];
    }
}
