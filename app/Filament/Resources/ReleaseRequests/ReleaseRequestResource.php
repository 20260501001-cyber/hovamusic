<?php

namespace App\Filament\Resources\ReleaseRequests;

use App\Domain\Releases\InvalidTransition;
use App\Domain\Releases\NoteRequired;
use App\Domain\Releases\ReleaseRequests;
use App\Domain\Releases\RequestNotAllowed;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\TemplateType;
use App\Filament\Resources\ReleaseRequests\Pages\ManageReleaseRequests;
use App\Filament\Resources\Releases\Actions\ReleaseActions;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Models\ReleaseRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Kullanıcıların onaylanmış yayınlar için açtığı düzeltme ve kaldırma talepleri.
 * Düzeltme talebi yanıtlanır; kaldırma talebi onaylanır ya da sebep yazılarak reddedilir.
 */
class ReleaseRequestResource extends Resource
{
    protected static ?string $model = ReleaseRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-inbox';

    protected static string|UnitEnum|null $navigationGroup = 'İnceleme';

    protected static ?string $modelLabel = 'Talep';

    protected static ?string $pluralModelLabel = 'Talepler';

    protected static ?string $slug = 'talepler';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $open = ReleaseRequest::query()->where('status', RequestStatus::Open)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['release.artists', 'user', 'handler']))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw("case when status = 'open' then 0 else 1 end")
                ->orderBy('created_at'))
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone'))->sortable(),
                TextColumn::make('type')->label('Tür')->badge()
                    ->formatStateUsing(fn (RequestType $state): string => $state->label())
                    ->color(fn (RequestType $state): string => $state === RequestType::Takedown ? 'danger' : 'info'),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (RequestStatus $state): string => $state->label())
                    ->color(fn (RequestStatus $state): string => match ($state) {
                        RequestStatus::Open => 'warning',
                        RequestStatus::Resolved => 'success',
                        RequestStatus::Rejected => 'danger',
                    }),
                TextColumn::make('release.title')->label('Yayın')
                    ->formatStateUsing(fn (ReleaseRequest $record): string => $record->release->displayTitle())
                    ->description(fn (ReleaseRequest $record): string => $record->release->artistLine())
                    ->url(fn (ReleaseRequest $record): string => ReleaseResource::getUrl('view', ['record' => $record->release]))
                    ->searchable(),
                TextColumn::make('user.email')->label('Kullanıcı')->searchable(),
                TextColumn::make('message')->label('Mesaj')->limit(80)->tooltip(fn (ReleaseRequest $record): string => $record->message)->wrap(),
                TextColumn::make('admin_note')->label('Yanıt')->limit(60)->placeholder('—')->toggleable(),
                TextColumn::make('handler.name')->label('Yanıtlayan')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')
                    ->options(collect(RequestStatus::cases())->mapWithKeys(fn (RequestStatus $s): array => [$s->value => $s->label()])->all())
                    ->default(RequestStatus::Open->value),
                SelectFilter::make('type')->label('Tür')
                    ->options(collect(RequestType::cases())->mapWithKeys(fn (RequestType $t): array => [$t->value => $t->label()])->all()),
            ])
            ->recordActions([
                Action::make('answer')
                    ->label('Yanıtla')
                    ->icon('lucide-reply')
                    ->authorize('update')
                    ->visible(fn (ReleaseRequest $record): bool => $record->isOpen() && $record->type === RequestType::Correction)
                    ->modalDescription(fn (ReleaseRequest $record): string => $record->message)
                    ->schema([
                        Textarea::make('note')->label('Yanıt (zorunlu)')->helperText('Kullanıcıya e-posta ve panel bildirimiyle gider.')
                            ->rows(5)->maxLength(2000)->required(),
                    ])
                    ->action(fn (array $data, ReleaseRequest $record, Action $action) => self::run($action,
                        fn () => app(ReleaseRequests::class)->answer($record, ReleaseActions::admin(), $data['note']), 'Talep yanıtlandı.')),
                Action::make('approveTakedown')
                    ->label('Kaldırmayı onayla')
                    ->icon('lucide-archive-x')
                    ->color('danger')
                    ->authorize('update')
                    ->visible(fn (ReleaseRequest $record): bool => $record->isOpen() && $record->type === RequestType::Takedown)
                    ->modalDescription('Yayın "Kaldırıldı" olur; kullanıcıya e-posta ve panel bildirimi gider.')
                    ->schema(ReleaseActions::noteSchema(false, null))
                    ->action(fn (array $data, ReleaseRequest $record, Action $action) => self::run($action,
                        fn () => app(ReleaseRequests::class)->approveTakedown($record, ReleaseActions::admin(), $data['note'] ?? null), 'Yayın kaldırıldı.')),
                Action::make('rejectTakedown')
                    ->label('Reddet')
                    ->icon('lucide-undo-2')
                    ->color('gray')
                    ->authorize('update')
                    ->visible(fn (ReleaseRequest $record): bool => $record->isOpen() && $record->type === RequestType::Takedown)
                    ->modalDescription(fn (ReleaseRequest $record): string => 'Yayın önceki durumuna ('.($record->previous_status?->label() ?? '—').') döner. Sebep kullanıcıya gönderilir.')
                    ->schema(ReleaseActions::noteSchema(true, TemplateType::Rejection))
                    ->action(fn (array $data, ReleaseRequest $record, Action $action) => self::run($action,
                        fn () => app(ReleaseRequests::class)->rejectTakedown($record, ReleaseActions::admin(), (string) $data['note']), 'Kaldırma talebi reddedildi.')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReleaseRequests::route('/'),
        ];
    }

    private static function run(Action $action, callable $callback, string $success): void
    {
        try {
            $callback();
        } catch (InvalidTransition|NoteRequired|RequestNotAllowed $e) {
            Notification::make()->danger()->title('İşlem yapılamadı')->body($e->getMessage())->send();
            $action->halt();
        }

        Notification::make()->success()->title($success)->send();
    }
}
