<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\ReleaseStatus;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Models\Release;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReleasesRelationManager extends RelationManager
{
    protected static string $relationship = 'releases';

    protected static ?string $title = 'Yayınları';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('artists'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Yayın')
                    ->formatStateUsing(fn (Release $record): string => $record->displayTitle())
                    ->description(fn (Release $record): string => $record->artistLine()),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (ReleaseStatus $state): string => $state->label())
                    ->color(fn (ReleaseStatus $state): string => ReleaseResource::statusColor($state)),
                TextColumn::make('upc')->label('UPC')->placeholder('—')->copyable(),
                TextColumn::make('release_date')->label('Yayın tarihi')->date('d.m.Y')->placeholder('—'),
                TextColumn::make('updated_at')->label('Son değişiklik')->since(),
            ])
            ->recordUrl(fn (Release $record): ?string => ReleaseResource::canView($record) ? ReleaseResource::getUrl('view', ['record' => $record]) : null);
    }
}
