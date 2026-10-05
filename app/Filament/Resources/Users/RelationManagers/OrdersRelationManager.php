<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Format;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Ödemeler';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ordered_at')->label('Tarih')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone')),
                TextColumn::make('product_name')->label('Ürün')->placeholder('—'),
                TextColumn::make('total')->label('Toplam')->formatStateUsing(fn (Order $record): string => Format::money((string) $record->total, $record->currency)),
                TextColumn::make('status')->label('Durum')->badge()->formatStateUsing(fn (OrderStatus $state): string => $state->label()),
                TextColumn::make('invoice_number')->label('Fatura no')->placeholder('—'),
            ]);
    }
}
