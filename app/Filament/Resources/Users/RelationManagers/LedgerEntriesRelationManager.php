<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Support\Format;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LedgerEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'ledgerEntries';

    protected static ?string $title = 'Bakiye hareketleri';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone')),
                TextColumn::make('type')->label('Tür')->formatStateUsing(fn (LedgerEntryType $state): string => $state->label()),
                TextColumn::make('bucket')->label('Kova')->formatStateUsing(fn (LedgerBucket $state): string => $state->label()),
                TextColumn::make('amount_usd')->label('Tutar')->alignEnd()->fontFamily('mono')
                    ->formatStateUsing(fn (LedgerEntry $record): string => Format::money((string) $record->amount_usd)),
                TextColumn::make('description')->label('Açıklama')->limit(60)->placeholder('—'),
            ]);
    }
}
