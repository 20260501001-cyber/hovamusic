<?php

namespace App\Filament\Resources\LedgerEntries;

use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Filament\Resources\LedgerEntries\Pages\ListLedgerEntries;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\Format;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Bakiye hareketleri. Defter yalnızca okunur; düzeltme "Manuel düzeltme" ile yeni
 * kayıt olarak eklenir.
 */
class LedgerEntryResource extends Resource
{
    protected static ?string $model = LedgerEntry::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-book-open-text';

    protected static string|UnitEnum|null $navigationGroup = 'Finans';

    protected static ?string $modelLabel = 'Bakiye hareketi';

    protected static ?string $pluralModelLabel = 'Bakiye hareketleri';

    protected static ?string $slug = 'bakiye-hareketleri';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user:id,email,deleted_at'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone')),
                TextColumn::make('user.email')->label('Kullanıcı'),
                TextColumn::make('type')->label('Tür')->badge()
                    ->formatStateUsing(fn (LedgerEntryType $state): string => $state->label())
                    ->color(fn (LedgerEntryType $state): string => match ($state) {
                        LedgerEntryType::Earning, LedgerEntryType::Unblock => 'success',
                        LedgerEntryType::EarningReversal => 'danger',
                        LedgerEntryType::Adjustment => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('bucket')->label('Kova')->formatStateUsing(fn (LedgerBucket $state): string => $state->label()),
                TextColumn::make('amount_usd')->label('Tutar (USD)')->alignEnd()->fontFamily('mono')
                    ->formatStateUsing(fn (LedgerEntry $record): string => Format::money((string) $record->amount_usd, 'USD', 2))
                    ->color(fn (LedgerEntry $record): ?string => str_starts_with((string) $record->amount_usd, '-') ? 'danger' : null)
                    ->summarize(Sum::make()->label('Toplam')->formatStateUsing(fn ($state): string => Format::money((string) ($state ?? '0')))),
                TextColumn::make('description')->label('Açıklama')->limit(60)->wrap()->placeholder('—'),
                TextColumn::make('created_by_type')->label('Yazan')->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (string $state): string => ['admin' => 'Admin', 'user' => 'Kullanıcı', 'system' => 'Sistem'][$state] ?? $state),
                TextColumn::make('group_id')->label('Grup')->fontFamily('mono')->limit(8)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user')->label('Kullanıcı')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => User::withTrashed()
                        ->where(fn (Builder $query) => $query->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                        ->limit(20)->pluck('email', 'id')->all())
                    ->getOptionLabelUsing(fn ($value): ?string => User::withTrashed()->find($value)?->email)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->where('user_id', $data['value']) : $query),
                SelectFilter::make('bucket')->label('Kova')
                    ->options(collect(LedgerBucket::cases())->mapWithKeys(fn (LedgerBucket $b): array => [$b->value => $b->label()])->all()),
                SelectFilter::make('type')->label('Tür')
                    ->options(collect(LedgerEntryType::cases())->mapWithKeys(fn (LedgerEntryType $t): array => [$t->value => $t->label()])->all()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLedgerEntries::route('/'),
        ];
    }
}
