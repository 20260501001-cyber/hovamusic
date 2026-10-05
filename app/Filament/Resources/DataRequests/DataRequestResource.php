<?php

namespace App\Filament\Resources\DataRequests;

use App\Domain\Privacy\PrivacyRequests;
use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Filament\Resources\DataRequests\Pages\ListDataRequests;
use App\Filament\Resources\Releases\Actions\ReleaseActions;
use App\Filament\Resources\Users\UserResource;
use App\Models\DataRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use UnitEnum;

/**
 * KVKK veri talepleri: kopya, düzeltme ve hesap silme.
 */
class DataRequestResource extends Resource
{
    protected static ?string $model = DataRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-shield-question';

    protected static string|UnitEnum|null $navigationGroup = 'Kullanıcılar';

    protected static ?string $modelLabel = 'Veri talebi';

    protected static ?string $pluralModelLabel = 'Veri talepleri (KVKK)';

    protected static ?string $slug = 'veri-talepleri';

    protected static ?int $navigationSort = 50;

    /**
     * @var array<string, callable(DataRequest): list<string>>
     */
    private static array $warnings = [];

    /**
     * Silme öncesi uyarı kaynakları (aktif abonelik, bakiye) sonradan eklenir.
     *
     * @param  callable(DataRequest): list<string>  $warning
     */
    public static function warnWith(string $key, callable $warning): void
    {
        self::$warnings[$key] = $warning;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = DataRequest::query()->where('status', DataRequestStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        $tz = config('hova.display_timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'handler']))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByRaw("case when status = 'pending' then 0 else 1 end")->orderBy('created_at'))
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i')->timezone($tz),
                TextColumn::make('type')->label('Tür')->badge()
                    ->formatStateUsing(fn (DataRequestType $state): string => $state->label())
                    ->color(fn (DataRequestType $state): string => $state === DataRequestType::Deletion ? 'danger' : 'info'),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (DataRequestStatus $state): string => $state->label())
                    ->color(fn (DataRequestStatus $state): string => match ($state) {
                        DataRequestStatus::Pending => 'warning',
                        DataRequestStatus::Completed => 'success',
                        DataRequestStatus::Rejected => 'danger',
                    }),
                TextColumn::make('user_email')->label('Kullanıcı')->searchable()
                    ->url(fn (DataRequest $record): ?string => $record->user && ! $record->user->trashed() && UserResource::canView($record->user) ? UserResource::getUrl('view', ['record' => $record->user]) : null),
                TextColumn::make('message')->label('Mesaj')->limit(80)->placeholder('—')->wrap(),
                TextColumn::make('admin_note')->label('Yanıt')->limit(60)->placeholder('—')->toggleable(),
                TextColumn::make('handler.name')->label('İşleyen')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')->default(DataRequestStatus::Pending->value)
                    ->options(collect(DataRequestStatus::cases())->mapWithKeys(fn (DataRequestStatus $s): array => [$s->value => $s->label()])->all()),
                SelectFilter::make('type')->label('Tür')
                    ->options(collect(DataRequestType::cases())->mapWithKeys(fn (DataRequestType $t): array => [$t->value => $t->label()])->all()),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label(fn (DataRequest $record): string => match ($record->type) {
                        DataRequestType::Export => 'Kopyayı hazırla ve gönder',
                        DataRequestType::Deletion => 'Hesabı sil',
                        DataRequestType::Correction => 'Tamamlandı',
                    })
                    ->icon(fn (DataRequest $record): string => $record->type === DataRequestType::Deletion ? 'lucide-user-x' : 'lucide-check')
                    ->color(fn (DataRequest $record): string => $record->type === DataRequestType::Deletion ? 'danger' : 'success')
                    ->visible(fn (DataRequest $record): bool => $record->isPending())
                    ->authorize('update')
                    ->modalDescription(fn (DataRequest $record): string => self::description($record))
                    ->schema([
                        Textarea::make('note')->label('Kullanıcıya not')->rows(3)->maxLength(2000),
                    ])
                    ->action(fn (array $data, DataRequest $record, Action $action) => self::run($action, fn () => app(PrivacyRequests::class)->complete($record, ReleaseActions::admin(), $data['note'] ?? null))),
                Action::make('reject')
                    ->label('Reddet')
                    ->icon('lucide-x')
                    ->color('gray')
                    ->visible(fn (DataRequest $record): bool => $record->isPending())
                    ->authorize('update')
                    ->schema([
                        Textarea::make('reason')->label('Gerekçe (zorunlu)')->rows(3)->maxLength(2000)->required(),
                    ])
                    ->action(fn (array $data, DataRequest $record, Action $action) => self::run($action, fn () => app(PrivacyRequests::class)->reject($record, ReleaseActions::admin(), $data['reason']))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDataRequests::route('/'),
        ];
    }

    private static function description(DataRequest $record): string
    {
        $lines = match ($record->type) {
            DataRequestType::Export => ['Kullanıcının verileri JSON dosyası olarak hazırlanır; indirme bağlantısı e-postayla gider (7 gün geçerli).'],
            DataRequestType::Deletion => ['Kişisel bilgiler anonimleştirilir ve hesap kapatılır. Bu işlem geri alınamaz. Polar\'daki abonelik buradan iptal edilmez.'],
            DataRequestType::Correction => ['Düzeltmeyi yaptıktan sonra tamamla; not kullanıcıya gider.'],
        };

        if ($record->type === DataRequestType::Deletion && $record->user !== null) {
            foreach (self::$warnings as $warning) {
                $lines = [...$lines, ...$warning($record)];
            }
        }

        return implode(' ', $lines);
    }

    private static function run(Action $action, callable $callback): void
    {
        try {
            $callback();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title('İşlem yapılamadı')->body($e->getMessage())->send();
            $action->halt();
        }

        Notification::make()->success()->title('Talep sonuçlandı.')->send();
    }
}
