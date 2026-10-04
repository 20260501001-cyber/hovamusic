<?php

namespace App\Filament\Resources\IsrcCodes;

use App\Filament\Resources\IsrcCodes\Pages\ListIsrcCodes;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Models\Admin;
use App\Models\IsrcCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Hova Music önekiyle atanmış ISRC'ler. Kayıtlar yalnızca görüntülenir; bir kod
 * bir kez atanır, parça silinse bile tekrar verilmez.
 */
class IsrcCodeResource extends Resource
{
    protected static ?string $model = IsrcCode::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-barcode';

    protected static string|UnitEnum|null $navigationGroup = 'İnceleme';

    protected static ?string $modelLabel = 'ISRC kaydı';

    protected static ?string $pluralModelLabel = 'ISRC kayıtları';

    protected static ?string $slug = 'isrc-kayitlari';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['track.release.user']))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('year')->orderByDesc('sequence'))
            ->searchPlaceholder('ISRC, parça ya da yayın')
            ->columns([
                TextColumn::make('isrc')->label('ISRC')->fontFamily('mono')->copyable()
                    ->formatStateUsing(fn (IsrcCode $record): string => $record->formatted())
                    ->copyableState(fn (IsrcCode $record): string => $record->isrc)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query) => $query
                        ->where('isrc', 'like', '%'.strtoupper(str_replace(['-', ' '], '', $search)).'%')
                        ->orWhereHas('track', fn (Builder $q) => $q->where('title', 'like', "%{$search}%")
                            ->orWhereHas('release', fn (Builder $r) => $r->where('title', 'like', "%{$search}%"))))),
                TextColumn::make('year')->label('Yıl')->formatStateUsing(fn (int $state): string => '20'.str_pad((string) $state, 2, '0', STR_PAD_LEFT)),
                TextColumn::make('sequence')->label('Sıra')->numeric(),
                TextColumn::make('track.title')->label('Parça')->placeholder('Parça silinmiş')
                    ->formatStateUsing(fn (IsrcCode $record): ?string => $record->track?->displayTitle()),
                TextColumn::make('track.release.title')->label('Yayın')->placeholder('—')
                    ->formatStateUsing(fn (IsrcCode $record): ?string => $record->track?->release?->displayTitle())
                    ->url(fn (IsrcCode $record): ?string => $record->track?->release ? ReleaseResource::getUrl('view', ['record' => $record->track->release]) : null),
                TextColumn::make('track.release.user.email')->label('Kullanıcı')->placeholder('—'),
                TextColumn::make('assigned_by_type')->label('Atayan')
                    ->formatStateUsing(fn (IsrcCode $record): string => $record->assigned_by_type === 'admin'
                        ? 'Admin: '.(Admin::query()->find($record->assigned_by_id)?->name ?? '—')
                        : 'Sistem (gönderimde)'),
                TextColumn::make('created_at')->label('Atanma')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone')),
            ])
            ->filters([
                SelectFilter::make('year')->label('Yıl')
                    ->options(fn (): array => IsrcCode::query()->distinct()->orderByDesc('year')->pluck('year')
                        ->mapWithKeys(fn (int $year): array => [$year => '20'.str_pad((string) $year, 2, '0', STR_PAD_LEFT)])->all()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIsrcCodes::route('/'),
        ];
    }
}
