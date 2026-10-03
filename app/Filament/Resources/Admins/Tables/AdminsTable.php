<?php

namespace App\Filament\Resources\Admins\Tables;

use App\Enums\AdminRole;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Ad soyad')->searchable()->sortable(),
                TextColumn::make('email')->label('E-posta')->searchable()->copyable(),
                TextColumn::make('roles.name')
                    ->label('Roller')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AdminRole::tryFrom($state)?->label() ?? $state),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('last_login_at')->label('Son giriş')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
