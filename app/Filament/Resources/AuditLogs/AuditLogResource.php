<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-scroll-text';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $modelLabel = 'İşlem kaydı';

    protected static ?string $pluralModelLabel = 'Audit log';

    protected static ?int $navigationSort = 92;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->label('Zaman')->dateTime('d.m.Y H:i:s'),
            TextEntry::make('actor_type')->label('Yapan')->formatStateUsing(fn (AuditLog $record): string => $record->actor_type.($record->actor_id ? ' #'.$record->actor_id : '')),
            TextEntry::make('action')->label('İşlem')->fontFamily('mono'),
            TextEntry::make('subject_type')->label('Kayıt')->formatStateUsing(fn (AuditLog $record): string => class_basename((string) $record->subject_type).' #'.$record->subject_id),
            TextEntry::make('ip_address')->label('IP'),
            KeyValueEntry::make('changes')->label('Değişiklikler')->columnSpanFull()
                ->getStateUsing(fn (AuditLog $record): array => collect($record->changes ?? [])
                    ->map(fn ($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value)
                    ->all()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Zaman')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('actor_type')->label('Yapan')
                    ->formatStateUsing(fn (AuditLog $record): string => $record->actor_type.($record->actor_id ? ' #'.$record->actor_id : '')),
                TextColumn::make('action')->label('İşlem')->fontFamily('mono')->searchable(),
                TextColumn::make('subject_type')->label('Kayıt')
                    ->formatStateUsing(fn (AuditLog $record): string => $record->subject_type ? class_basename($record->subject_type).' #'.$record->subject_id : '—'),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('actor_type')->label('Yapan')->options([
                    'admin' => 'Admin',
                    'user' => 'Kullanıcı',
                    'system' => 'Sistem',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
