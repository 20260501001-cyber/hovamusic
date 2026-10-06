<?php

namespace App\Filament\Resources\SeoMetas\Pages;

use App\Filament\Resources\SeoMetas\SeoMetaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSeoMetas extends ManageRecords
{
    protected static string $resource = SeoMetaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Sayfa ekle')];
    }
}
