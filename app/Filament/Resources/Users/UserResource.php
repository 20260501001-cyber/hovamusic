<?php

namespace App\Filament\Resources\Users;

use App\Domain\Plans\PlanGate;
use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\LedgerEntriesRelationManager;
use App\Filament\Resources\Users\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Users\RelationManagers\ReleasesRelationManager;
use App\Filament\Resources\Users\RelationManagers\SubscriptionsRelationManager;
use App\Filament\Resources\Users\RelationManagers\WithdrawalsRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Kullanıcılar: liste, detay ve yayınları; askıya alma, banlama ve kullanıcı olarak
 * görüntüleme; plan, bakiye ve ödemeler. Yalnızca Süper Admin.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-users';

    protected static string|UnitEnum|null $navigationGroup = 'Kullanıcılar';

    protected static ?string $modelLabel = 'Kullanıcı';

    protected static ?string $pluralModelLabel = 'Kullanıcılar';

    protected static ?string $slug = 'kullanicilar';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'email';

    public static function infolist(Schema $schema): Schema
    {
        $tz = config('hova.display_timezone');

        return $schema->columns(2)->components([
            Section::make('Hesap')
                ->columns(2)
                ->schema([
                    TextEntry::make('name')->label('Ad')->copyable(),
                    TextEntry::make('email')->label('E-posta')->copyable(),
                    TextEntry::make('account_type')->label('Hesap türü')->formatStateUsing(fn (AccountType $state): string => __('account.types.'.$state->value)),
                    TextEntry::make('ulid')->label('Kullanıcı kimliği')->fontFamily('mono')->copyable(),
                    TextEntry::make('email_verified_at')->label('E-posta doğrulama')->dateTime('d.m.Y H:i')->timezone($tz)->placeholder('Doğrulanmadı'),
                    TextEntry::make('two_factor')->label('İki adımlı doğrulama')->state(fn (User $record): string => $record->hasTwoFactorEnabled() ? 'Açık' : 'Kapalı'),
                    TextEntry::make('created_at')->label('Kayıt')->dateTime('d.m.Y H:i')->timezone($tz),
                    TextEntry::make('last_login_at')->label('Son giriş')->dateTime('d.m.Y H:i')->timezone($tz)->placeholder('—')
                        ->helperText(fn (User $record): ?string => $record->last_login_ip),
                ]),
            Section::make('Plan')
                ->columns(2)
                ->schema([
                    TextEntry::make('active_plan')->label('Aktif plan')
                        ->state(fn (User $record): string => $record->activeSubscription()?->plan?->name ?? 'Yok'),
                    TextEntry::make('plan_status')->label('Abonelik durumu')
                        ->state(fn (User $record): ?string => $record->activeSubscription()?->status->label())
                        ->placeholder('—'),
                    TextEntry::make('period_end')->label('Dönem sonu')
                        ->state(fn (User $record) => $record->activeSubscription()?->current_period_end)
                        ->dateTime('d.m.Y H:i')->timezone($tz)->placeholder('—'),
                    TextEntry::make('usage')->label('Bu dönem kullanım')
                        ->state(function (User $record): string {
                            $usage = app(PlanGate::class)->usage($record);

                            return sprintf('%d yayın / %s · %d sanatçı / %s',
                                $usage['releases_used'], $usage['release_limit'] ?? 'sınırsız',
                                $usage['artists_used'], $usage['artist_limit'] ?? 'sınırsız');
                        }),
                ]),
            ...UserExtraSections::sections(),
            Section::make('Durum')
                ->schema([
                    TextEntry::make('status')->label('Durum')->badge()
                        ->formatStateUsing(fn (UserStatus $state): string => $state->label())
                        ->color(fn (UserStatus $state): string => self::statusColor($state)),
                    TextEntry::make('status_reason')->label('Sebep')->placeholder('—'),
                    TextEntry::make('status_changed_at')->label('Değişiklik')->dateTime('d.m.Y H:i')->timezone($tz)->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('releases'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Ad')->searchable()->description(fn (User $record): string => $record->email),
                TextColumn::make('email')->label('E-posta')->searchable()->hidden(),
                TextColumn::make('account_type')->label('Hesap türü')->formatStateUsing(fn (AccountType $state): string => __('account.types.'.$state->value)),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => $state->label())
                    ->color(fn (UserStatus $state): string => self::statusColor($state)),
                TextColumn::make('releases_count')->label('Yayın')->numeric()->sortable(),
                TextColumn::make('created_at')->label('Kayıt')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone'))->sortable(),
                TextColumn::make('last_login_at')->label('Son giriş')->since()->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')
                    ->options(collect(UserStatus::cases())->mapWithKeys(fn (UserStatus $s): array => [$s->value => $s->label()])->all()),
                SelectFilter::make('account_type')->label('Hesap türü')
                    ->options(collect(AccountType::cases())->mapWithKeys(fn (AccountType $t): array => [$t->value => __('account.types.'.$t->value)])->all()),
                Filter::make('new')->label('Son 7 günde kaydolanlar')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(7))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ReleasesRelationManager::class,
            SubscriptionsRelationManager::class,
            OrdersRelationManager::class,
            LedgerEntriesRelationManager::class,
            WithdrawalsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }

    public static function statusColor(UserStatus $status): string
    {
        return match ($status) {
            UserStatus::Active => 'success',
            UserStatus::Suspended => 'warning',
            UserStatus::Banned => 'danger',
        };
    }
}
