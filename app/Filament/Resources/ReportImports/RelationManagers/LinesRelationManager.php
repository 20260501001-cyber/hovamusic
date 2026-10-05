<?php

namespace App\Filament\Resources\ReportImports\RelationManagers;

use App\Domain\Finance\ManualMatches;
use App\Domain\Finance\ReportUnreadable;
use App\Enums\ReportImportStatus;
use App\Filament\Resources\Releases\Actions\ReleaseActions;
use App\Models\Release;
use App\Models\ReportImport;
use App\Models\ReportLine;
use App\Models\Track;
use App\Support\Audit\AuditLogger;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Rapor satırları; eşleşmeyenler varsayılan olarak listelenir ve elle eşlenebilir.
 */
class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Satırlar';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof ReportImport && $ownerRecord->row_count > 0;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user:id,email'))
            ->defaultSort('row_number')
            ->columns([
                TextColumn::make('row_number')->label('Satır')->sortable(),
                TextColumn::make('sales_month')->label('Ay')->date('Y-m')->sortable(),
                TextColumn::make('platform')->label('Platform')->searchable()->placeholder('—'),
                TextColumn::make('country')->label('Ülke')->placeholder('—')->toggleable(),
                TextColumn::make('artist_name')->label('Sanatçı')->searchable()->placeholder('—')->limit(30),
                TextColumn::make('track_title')->label('Parça')->searchable()->placeholder('—')->limit(40)
                    ->description(fn (ReportLine $record): ?string => $record->release_title),
                TextColumn::make('isrc')->label('ISRC')->fontFamily('mono')->searchable()->placeholder('—'),
                TextColumn::make('upc')->label('UPC')->fontFamily('mono')->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('quantity')->label('Adet')->numeric(),
                TextColumn::make('net_amount')->label('Net')
                    ->formatStateUsing(fn (ReportLine $record): string => Format::money((string) $record->net_amount, $record->currency ?? 'USD', 4)),
                TextColumn::make('user_amount_usd')->label('Kullanıcıya')
                    ->formatStateUsing(fn (ReportLine $record): string => $record->user_amount_usd !== null ? Format::money((string) $record->user_amount_usd, 'USD', 4) : '—')
                    ->description(fn (ReportLine $record): ?string => $record->share_pct !== null ? '%'.$record->share_pct : null),
                TextColumn::make('match_status')->label('Eşleşme')->badge()
                    ->formatStateUsing(fn (string $state, ReportLine $record): string => $state === 'matched' ? 'Eşleşti ('.strtoupper((string) $record->match_source).')' : 'Eşleşmedi')
                    ->color(fn (string $state): string => $state === 'matched' ? 'success' : 'warning')
                    ->description(fn (ReportLine $record): ?string => $record->user?->email),
            ])
            ->filters([
                SelectFilter::make('match_status')->label('Eşleşme')
                    ->options(['unmatched' => 'Eşleşmeyen', 'matched' => 'Eşleşen'])
                    ->default('unmatched'),
            ])
            ->recordActions([
                Action::make('match')
                    ->label('Eşle')
                    ->icon('lucide-link')
                    ->visible(fn (ReportLine $record): bool => $record->match_status === 'unmatched'
                        && $this->getOwnerRecord()->status === ReportImportStatus::Preview
                        && auth('admin')->user()?->can('update', $this->getOwnerRecord()))
                    ->modalDescription('Seçtiğin anahtar bu ve sonraki raporlarda aynı yayına/parçaya eşlenir; rapor yeniden hesaplanır.')
                    ->schema(fn (ReportLine $record): array => [
                        Select::make('key_type')
                            ->label('Eşleştirme anahtarı')
                            ->options(collect(app(ManualMatches::class)->keysFor($record))
                                ->mapWithKeys(fn (string $key, string $type): array => [$type => match ($type) {
                                    'isrc' => 'ISRC: '.$key,
                                    'upc' => 'UPC: '.$key,
                                    'title' => 'Sanatçı | parça: '.$key,
                                }])->all())
                            ->default(fn (): ?string => array_key_first(app(ManualMatches::class)->keysFor($record)))
                            ->required(),
                        Select::make('release_id')
                            ->label('Yayın')
                            ->searchable()
                            ->live()
                            ->getSearchResultsUsing(fn (string $search): array => Release::query()
                                ->with('user:id,email')
                                ->where(fn (Builder $q) => $q->where('title', 'like', "%{$search}%")->orWhere('upc', 'like', "%{$search}%"))
                                ->whereNotNull('submitted_at')
                                ->limit(20)
                                ->get(['id', 'title', 'upc', 'user_id'])
                                ->mapWithKeys(fn (Release $release): array => [$release->id => $release->title.($release->upc ? ' · '.$release->upc : '').' · '.$release->user?->email])
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => Release::withTrashed()->find($value, ['id', 'title'])?->title)
                            ->required(),
                        Select::make('track_id')
                            ->label('Parça (isteğe bağlı)')
                            ->helperText('Boş bırakılırsa gelir yayın geneline yazılır.')
                            ->options(fn (Get $get): array => $get('release_id')
                                ? Track::query()->where('release_id', $get('release_id'))->orderBy('position')->get(['id', 'title', 'isrc'])
                                    ->mapWithKeys(fn (Track $track): array => [$track->id => $track->title.($track->isrc ? ' · '.$track->isrc : '')])->all()
                                : []),
                    ])
                    ->action(function (array $data, ReportLine $record, Action $action): void {
                        try {
                            $rule = app(ManualMatches::class)->match(
                                $record,
                                $data['key_type'],
                                Release::withTrashed()->findOrFail($data['release_id']),
                                filled($data['track_id'] ?? null) ? Track::query()->find($data['track_id']) : null,
                                ReleaseActions::admin(),
                            );
                        } catch (ReportUnreadable $e) {
                            Notification::make()->danger()->title('Eşlenemedi')->body($e->getMessage())->send();
                            $action->halt();

                            return;
                        }

                        app(AuditLogger::class)->record('report.match_rule', $rule, ['key_type' => $rule->key_type, 'key' => $rule->key]);
                        Notification::make()->success()->title('Eşleştirme kaydedildi.')->body('Rapor yeniden hesaplanıyor.')->send();
                    }),
            ]);
    }
}
