<?php

namespace App\Filament\Resources\Plans;

use App\Enums\AccountType;
use App\Enums\PlanInterval;
use App\Filament\Resources\Plans\Pages\ManagePlans;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Abonelik planları. Fiyat Polar ürününde de aynı tutulmalı; satış Polar ürün
 * kimliğiyle yapılır, kimliği olmayan plan satılmaz. Gelir payı değişikliği yalnızca
 * yeni plan geçmişi kayıtlarına yansır.
 */
class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-layers';

    protected static string|UnitEnum|null $navigationGroup = 'Satış';

    protected static ?string $modelLabel = 'Plan';

    protected static ?string $pluralModelLabel = 'Planlar';

    protected static ?string $slug = 'planlar';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Plan adı')->maxLength(120)->required(),
            Select::make('audience')->label('Hedef hesap türü')->required()
                ->options(collect(AccountType::cases())->mapWithKeys(fn (AccountType $type): array => [$type->value => $type->label()])->all()),
            TextInput::make('price_usd')->label('Fiyat')->prefix('USD')->numeric()->minValue(0)->maxValue(99999)->step('0.01')->required()
                ->helperText('Polar ürünündeki fiyatla aynı olmalı; kullanıcı Polar\'daki tutarı öder.'),
            Select::make('interval')->label('Süre')->required()
                ->options(collect(PlanInterval::cases())->mapWithKeys(fn (PlanInterval $interval): array => [$interval->value => $interval->label()])->all()),
            TextInput::make('release_limit')->label('Yayın limiti (dönem başına)')->integer()->minValue(0)->helperText('Boş bırakılırsa sınırsız.')
                ->dehydrateStateUsing(fn ($state): ?int => filled($state) ? (int) $state : null),
            TextInput::make('artist_limit')->label('Sanatçı limiti')->integer()->minValue(0)->helperText('Boş bırakılırsa sınırsız.')
                ->dehydrateStateUsing(fn ($state): ?int => filled($state) ? (int) $state : null),
            TextInput::make('revenue_share_pct')->label('Gelir payı')->suffix('%')->numeric()->minValue(0)->maxValue(100)->step('0.01')->required()
                ->helperText('Kullanıcıya kalan pay. Değişiklik mevcut abonelerin plan geçmişine yeni kayıt açılınca yansır.'),
            TextInput::make('polar_product_id')->label('Polar ürün kimliği')->maxLength(64)
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn ($state): ?string => filled($state) ? trim((string) $state) : null)
                ->helperText('Polar > Products > ürün > ID. Boşsa plan satışa çıkmaz.'),
            Textarea::make('description')->label('Kısa açıklama')->rows(2)->maxLength(500)->columnSpanFull(),
            TagsInput::make('features')->label('Öne çıkan özellikler')->helperText('Fiyat sayfasında ve panelde liste olarak gösterilir.')->columnSpanFull(),
            TextInput::make('sort')->label('Sıra')->integer()->default(0),
            Toggle::make('is_active')->label('Satışta')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')->label('Plan')->searchable()
                    ->description(fn (Plan $record): string => $record->audience->label().' · '.$record->interval->label()),
                TextColumn::make('price_usd')->label('Fiyat')->money('USD'),
                TextColumn::make('release_limit')->label('Yayın')->placeholder('Sınırsız'),
                TextColumn::make('artist_limit')->label('Sanatçı')->placeholder('Sınırsız'),
                TextColumn::make('revenue_share_pct')->label('Gelir payı')->suffix('%'),
                TextColumn::make('polar_product_id')->label('Polar ürünü')->placeholder('Bağlı değil')->fontFamily('mono')->copyable()->limit(14),
                TextColumn::make('subscriptions_count')->label('Abone')->counts(['subscriptions' => fn ($query) => $query->active()]),
                IconColumn::make('is_active')->label('Satışta')->boolean(),
            ])
            ->filters([
                SelectFilter::make('audience')->label('Hesap türü')
                    ->options(collect(AccountType::cases())->mapWithKeys(fn (AccountType $type): array => [$type->value => $type->label()])->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePlans::route('/'),
        ];
    }
}
