<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\WithdrawalStatus;
use App\Filament\Resources\Withdrawals\WithdrawalResource;
use App\Models\Withdrawal;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WithdrawalsRelationManager extends RelationManager
{
    protected static string $relationship = 'withdrawals';

    protected static ?string $title = 'Para çekme talepleri';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Talep')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone')),
                TextColumn::make('amount_usd')->label('Tutar')->formatStateUsing(fn (Withdrawal $record): string => Format::money((string) $record->amount_usd)),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (WithdrawalStatus $state): string => $state->label())
                    ->color(fn (WithdrawalStatus $state): string => WithdrawalResource::statusColor($state)),
                TextColumn::make('paid_at')->label('Ödendi')->dateTime('d.m.Y')->timezone(config('hova.display_timezone'))->placeholder('—'),
            ])
            ->recordActions([
                Action::make('open')->label('Aç')->icon('lucide-external-link')
                    ->url(fn (Withdrawal $record): string => WithdrawalResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
