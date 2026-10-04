<?php

namespace App\Filament\Resources\Releases\Tables;

use App\Enums\ReleaseStatus;
use App\Enums\ReleaseType;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Models\Release;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReleasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'artists'])->withCount('tracks'))
            // İnceleme bekleyenler en üstte; en uzun süredir bekleyen önce.
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw("case when status = 'in_review' then 0 when status = 'takedown_requested' then 1 else 2 end")
                ->orderByRaw("case when status = 'in_review' then submitted_at end asc")
                ->orderByDesc('updated_at'))
            ->searchPlaceholder('Başlık, sanatçı, ISRC ya da UPC')
            ->columns([
                TextColumn::make('title')
                    ->label('Yayın')
                    ->formatStateUsing(fn (Release $record): string => $record->displayTitle())
                    ->description(fn (Release $record): string => $record->artistLine() ?: '—')
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::search($query, $search))
                    ->wrap(),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (ReleaseStatus $state): string => $state->label())
                    ->color(fn (ReleaseStatus $state): string => ReleaseResource::statusColor($state)),
                TextColumn::make('type')->label('Tür')
                    ->formatStateUsing(fn (?ReleaseType $state): string => $state?->label() ?? '—'),
                TextColumn::make('tracks_count')->label('Parça')->numeric(),
                TextColumn::make('user.email')->label('Kullanıcı')->description(fn (Release $record): ?string => $record->user?->name),
                TextColumn::make('upc')->label('UPC')->placeholder('—')->fontFamily('mono')->copyable(),
                TextColumn::make('release_date')->label('Yayın tarihi')->date('d.m.Y')->sortable(),
                TextColumn::make('submitted_at')->label('Gönderim')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone'))->placeholder('—')->sortable(),
                TextColumn::make('updated_at')->label('Son değişiklik')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')->multiple()->options(ReleaseResource::statusOptions()),
                SelectFilter::make('type')->label('Tür')
                    ->options(collect(ReleaseType::cases())->mapWithKeys(fn (ReleaseType $type): array => [$type->value => $type->label()])->all()),
                SelectFilter::make('user_id')->label('Kullanıcı')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => User::query()
                        ->where(fn (Builder $query) => $query->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                        ->limit(20)->pluck('email', 'id')->all())
                    ->getOptionLabelUsing(fn ($value): ?string => User::query()->find($value)?->email),
                Filter::make('submitted')
                    ->label('Gönderim tarihi')
                    ->schema([
                        DatePicker::make('from')->label('Gönderim (başlangıç)')->native(false)->displayFormat('d.m.Y'),
                        DatePicker::make('until')->label('Gönderim (bitiş)')->native(false)->displayFormat('d.m.Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->where('submitted_at', '>=', self::dayStart($date)))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->where('submitted_at', '<', self::dayStart($date)->addDay())))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'Gönderim ≥ '.Carbon::parse($data['from'])->format('d.m.Y');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Gönderim ≤ '.Carbon::parse($data['until'])->format('d.m.Y');
                        }

                        return $indicators;
                    }),
            ])
            ->recordUrl(fn (Release $record): string => ReleaseResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    /**
     * Başlık, sürüm, yayın ve parça sanatçıları, parça ISRC'si ve UPC'de arar.
     * ISRC tireli ya da tiresiz yazılabilir.
     *
     * @param  Builder<Release>  $query
     * @return Builder<Release>
     */
    public static function search(Builder $query, string $search): Builder
    {
        $term = trim($search);
        $compact = strtoupper(str_replace(['-', ' '], '', $term));

        return $query->where(function (Builder $query) use ($term, $compact): void {
            $query->where('title', 'like', "%{$term}%")
                ->orWhere('version', 'like', "%{$term}%")
                ->orWhere('upc', 'like', "%{$compact}%")
                ->orWhereHas('artists', fn (Builder $q) => $q->where('name', 'like', "%{$term}%"))
                ->orWhereHas('tracks', fn (Builder $q) => $q
                    ->where('isrc', 'like', "%{$compact}%")
                    ->orWhere('title', 'like', "%{$term}%")
                    ->orWhereHas('artists', fn (Builder $a) => $a->where('name', 'like', "%{$term}%")));
        });
    }

    /**
     * Admin tarihi İstanbul saatine göre seçer; veritabanı UTC tutar.
     */
    private static function dayStart(string $date): Carbon
    {
        return Carbon::parse($date, config('hova.display_timezone'))->startOfDay()->utc();
    }
}
