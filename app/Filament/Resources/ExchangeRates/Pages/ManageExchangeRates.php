<?php

namespace App\Filament\Resources\ExchangeRates\Pages;

use App\Filament\Resources\ExchangeRates\ExchangeRateResource;
use App\Filament\Resources\Releases\Actions\ReleaseActions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExchangeRates extends ManageRecords
{
    protected static string $resource = ExchangeRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Kur ekle')
                ->mutateDataUsing(fn (array $data): array => [...$data, 'created_by' => ReleaseActions::admin()->id]),
        ];
    }
}
