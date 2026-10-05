<?php

namespace App\Filament\Resources\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Resources\Users\UserResource;
use App\Models\Subscription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-repeat';

    protected static string|UnitEnum|null $navigationGroup = 'Satış';

    protected static ?string $modelLabel = 'Abonelik';

    protected static ?string $pluralModelLabel = 'Abonelikler';

    protected static ?string $slug = 'abonelikler';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        $tz = config('hova.display_timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'plan']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.email')->label('Kullanıcı')->searchable()
                    ->url(fn (Subscription $record): ?string => $record->user && UserResource::canView($record->user) ? UserResource::getUrl('view', ['record' => $record->user]) : null),
                TextColumn::make('plan.name')->label('Plan')->placeholder('Bağlı plan yok'),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label())
                    ->color(fn (SubscriptionStatus $state): string => match ($state) {
                        SubscriptionStatus::Active, SubscriptionStatus::Trialing => 'success',
                        SubscriptionStatus::PastDue, SubscriptionStatus::Incomplete => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('cancel_at_period_end')->label('Dönem sonunda bitecek')->boolean(),
                TextColumn::make('current_period_end')->label('Dönem sonu')->dateTime('d.m.Y H:i')->timezone($tz)->sortable()->placeholder('—'),
                TextColumn::make('started_at')->label('Başlangıç')->dateTime('d.m.Y')->timezone($tz)->placeholder('—'),
                TextColumn::make('provider_id')->label('Polar abonelik kimliği')->fontFamily('mono')->copyable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')
                    ->options(collect(SubscriptionStatus::cases())->mapWithKeys(fn (SubscriptionStatus $s): array => [$s->value => $s->label()])->all()),
                TernaryFilter::make('in_use')->label('Kullanımda')
                    ->queries(
                        true: fn (Builder $query) => $query->active(),
                        false: fn (Builder $query) => $query->whereNotIn('id', Subscription::query()->active()->select('id')),
                    ),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
        ];
    }
}
