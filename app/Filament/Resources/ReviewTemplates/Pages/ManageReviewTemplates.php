<?php

namespace App\Filament\Resources\ReviewTemplates\Pages;

use App\Filament\Resources\ReviewTemplates\ReviewTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageReviewTemplates extends ManageRecords
{
    protected static string $resource = ReviewTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Şablon ekle'),
        ];
    }
}
