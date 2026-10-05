<?php

namespace App\Filament\Resources\ReportImports;

use App\Enums\ReportImportStatus;
use App\Filament\Resources\ReportImports\Pages\ListReportImports;
use App\Filament\Resources\ReportImports\Pages\ViewReportImport;
use App\Filament\Resources\ReportImports\RelationManagers\LinesRelationManager;
use App\Models\ReportImport;
use App\Support\Format;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Believe satış raporları: yükleme, önizleme, eşleştirme, onay ve geri alma.
 * Onaylanmadan bakiyeye hiçbir şey yazılmaz.
 */
class ReportImportResource extends Resource
{
    protected static ?string $model = ReportImport::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-file-spreadsheet';

    protected static string|UnitEnum|null $navigationGroup = 'Finans';

    protected static ?string $modelLabel = 'Rapor';

    protected static ?string $pluralModelLabel = 'Rapor içe aktarma';

    protected static ?string $slug = 'raporlar';

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $waiting = ReportImport::query()->where('status', ReportImportStatus::Preview)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        $tz = config('hova.display_timezone');

        return $schema->columns(3)->components([
            Section::make('Özet')
                ->columns(3)
                ->columnSpan(2)
                ->schema([
                    TextEntry::make('status')->label('Durum')->badge()
                        ->formatStateUsing(fn (ReportImportStatus $state): string => $state->label())
                        ->color(fn (ReportImportStatus $state): string => self::statusColor($state)),
                    TextEntry::make('periods')->label('Satış dönemleri')
                        ->state(fn (ReportImport $record): string => implode(', ', $record->periods ?? []) ?: '—'),
                    TextEntry::make('mapping.name')->label('Eşleştirme profili')->placeholder('Varsayılan'),
                    TextEntry::make('row_count')->label('Satır')->numeric(),
                    TextEntry::make('matched_count')->label('Eşleşen')->numeric(),
                    TextEntry::make('unmatched_count')->label('Eşleşmeyen')->numeric()
                        ->color(fn (ReportImport $record): ?string => $record->unmatched_count > 0 ? 'warning' : null),
                    TextEntry::make('net_by_currency')->label('Net gelir (rapor para birimi)')
                        ->state(fn (ReportImport $record): string => collect($record->totals['net_by_currency'] ?? [])
                            ->map(fn (string $total, string $currency): string => Format::money($total, $currency ?: 'USD'))
                            ->implode(' · ') ?: '—'),
                    TextEntry::make('amount_usd')->label('Toplam (USD)')
                        ->state(fn (ReportImport $record): string => Format::money($record->totals['amount_usd'] ?? '0')),
                    TextEntry::make('users_usd')->label('Kullanıcı payları (USD)')
                        ->state(fn (ReportImport $record): string => Format::money($record->totals['users_usd'] ?? '0'))
                        ->helperText(fn (ReportImport $record): string => ($record->totals['users'] ?? 0).' kullanıcı'),
                    TextEntry::make('unmatched_usd')->label('Eşleşmeyen tutar (USD)')
                        ->state(fn (ReportImport $record): string => Format::money($record->totals['unmatched_usd'] ?? '0')),
                    TextEntry::make('quantity')->label('Toplam adet')
                        ->state(fn (ReportImport $record): string => number_format((int) ($record->totals['quantity'] ?? 0), 0, ',', '.')),
                    ViewEntry::make('processing')->hiddenLabel()->view('filament.report-imports.processing')->columnSpanFull()
                        ->visible(fn (ReportImport $record): bool => in_array($record->status, [ReportImportStatus::Uploaded, ReportImportStatus::Processing], true)),
                    TextEntry::make('error')->label('Hata')->color('danger')->columnSpanFull()
                        ->visible(fn (ReportImport $record): bool => filled($record->error)),
                ]),
            Section::make('Dosya')
                ->columnSpan(1)
                ->schema([
                    TextEntry::make('original_name')->label('Dosya'),
                    TextEntry::make('currency')->label('Para birimi (dosyada yoksa)')->placeholder('Dosyadan'),
                    TextEntry::make('file_sha256')->label('SHA-256')->fontFamily('mono')->limit(16)->copyable(),
                    TextEntry::make('created_at')->label('Yüklendi')->dateTime('d.m.Y H:i')->timezone($tz)
                        ->helperText(fn (ReportImport $record): ?string => $record->uploader?->name),
                    TextEntry::make('approved_at')->label('Onaylandı')->dateTime('d.m.Y H:i')->timezone($tz)->placeholder('—')
                        ->helperText(fn (ReportImport $record): ?string => $record->approver?->name),
                    TextEntry::make('reversed_at')->label('Geri alındı')->dateTime('d.m.Y H:i')->timezone($tz)->placeholder('—')
                        ->helperText(fn (ReportImport $record): ?string => $record->reverse_reason)
                        ->visible(fn (ReportImport $record): bool => $record->reversed_at !== null),
                ]),
            Section::make('Uyarılar')
                ->columnSpanFull()
                ->visible(fn (ReportImport $record): bool => ! empty($record->warnings['blocking'] ?? []) || ! empty($record->warnings['notes'] ?? []))
                ->schema([
                    ViewEntry::make('warnings')->hiddenLabel()->view('filament.report-imports.warnings'),
                ]),
            Section::make('Kullanıcı bazlı dağılım')
                ->description('Onayda her kullanıcıya rapor başına tek kayıt yazılır. Aktif planı olmayanın kazancı bloke kovaya gider.')
                ->columnSpanFull()
                ->visible(fn (ReportImport $record): bool => in_array($record->status, [ReportImportStatus::Preview, ReportImportStatus::Approved, ReportImportStatus::Reversed], true))
                ->schema([
                    ViewEntry::make('distribution')->hiddenLabel()->view('filament.report-imports.distribution'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $tz = config('hova.display_timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('uploader'))
            ->defaultSort('created_at', 'desc')
            ->poll(fn (): ?string => ReportImport::query()->whereIn('status', [ReportImportStatus::Uploaded, ReportImportStatus::Processing])->exists() ? '5s' : null)
            ->columns([
                TextColumn::make('created_at')->label('Yüklendi')->dateTime('d.m.Y H:i')->timezone($tz)->sortable(),
                TextColumn::make('original_name')->label('Dosya')->searchable()->limit(40)
                    ->description(fn (ReportImport $record): ?string => $record->uploader?->name),
                TextColumn::make('periods')->label('Dönem')
                    ->state(fn (ReportImport $record): string => implode(', ', $record->periods ?? []) ?: '—'),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (ReportImportStatus $state): string => $state->label())
                    ->color(fn (ReportImportStatus $state): string => self::statusColor($state)),
                TextColumn::make('row_count')->label('Satır')->numeric()
                    ->description(fn (ReportImport $record): ?string => $record->unmatched_count > 0 ? $record->unmatched_count.' eşleşmedi' : null),
                TextColumn::make('totals.users_usd')->label('Kullanıcı payları')
                    ->state(fn (ReportImport $record): string => Format::money($record->totals['users_usd'] ?? '0')),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')
                    ->options(collect(ReportImportStatus::cases())->mapWithKeys(fn (ReportImportStatus $s): array => [$s->value => $s->label()])->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReportImports::route('/'),
            'view' => ViewReportImport::route('/{record}'),
        ];
    }

    public static function statusColor(ReportImportStatus $status): string
    {
        return match ($status) {
            ReportImportStatus::Uploaded, ReportImportStatus::Processing => 'info',
            ReportImportStatus::Preview => 'warning',
            ReportImportStatus::Failed => 'danger',
            ReportImportStatus::Approved => 'success',
            ReportImportStatus::Reversed => 'gray',
        };
    }
}
