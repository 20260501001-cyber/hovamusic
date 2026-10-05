<?php

namespace App\Filament\Resources\ReportMappings\Pages;

use App\Filament\Resources\ReportMappings\ReportMappingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageReportMappings extends ManageRecords
{
    protected static string $resource = ReportMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Profil ekle'),
        ];
    }
}
