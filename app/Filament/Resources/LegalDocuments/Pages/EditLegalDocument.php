<?php

namespace App\Filament\Resources\LegalDocuments\Pages;

use App\Domain\Legal\LegalDocuments;
use App\Filament\Resources\LegalDocuments\LegalDocumentResource;
use Filament\Resources\Pages\EditRecord;

class EditLegalDocument extends EditRecord
{
    protected static string $resource = LegalDocumentResource::class;

    protected function afterSave(): void
    {
        LegalDocuments::forget();
    }
}
