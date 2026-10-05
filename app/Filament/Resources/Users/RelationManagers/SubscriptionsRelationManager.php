<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\SubscriptionStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static ?string $title = 'Abonelikler';

    public function table(Table $table): Table
    {
        $tz = config('hova.display_timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('plan'))
            ->columns([
                TextColumn::make('plan.name')->label('Plan')->placeholder('—'),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label()),
                IconColumn::make('cancel_at_period_end')->label('Dönem sonunda bitecek')->boolean(),
                TextColumn::make('current_period_end')->label('Dönem sonu')->dateTime('d.m.Y H:i')->timezone($tz)->placeholder('—'),
                TextColumn::make('started_at')->label('Başlangıç')->dateTime('d.m.Y')->timezone($tz)->placeholder('—'),
            ]);
    }
}
