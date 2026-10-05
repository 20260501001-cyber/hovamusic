<?php

namespace App\Filament\Resources\ExchangeRates;

use App\Filament\Resources\ExchangeRates\Pages\ManageExchangeRates;
use App\Models\ExchangeRate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Dönem kurları. Rapor tutarları satış ayının kuruyla USD'ye çevrilir; kullanıcı
 * ekranındaki TRY/EUR gösterimi en son girilen USD kuruyla yaklaşık yapılır.
 */
class ExchangeRateResource extends Resource
{
    protected static ?string $model = ExchangeRate::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-arrow-left-right';

    protected static string|UnitEnum|null $navigationGroup = 'Finans';

    protected static ?string $modelLabel = 'Kur';

    protected static ?string $pluralModelLabel = 'Kurlar';

    protected static ?string $slug = 'kurlar';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        $upper = fn (?string $state): ?string => $state ? strtoupper(trim($state)) : null;

        return $schema->columns(2)->components([
            TextInput::make('period')->label('Dönem')->placeholder('2026-09')->helperText('Satış ayı (YYYY-AA).')
                ->regex('/^\d{4}-(0[1-9]|1[0-2])$/')->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                    ->where('from_currency', strtoupper((string) $get('from_currency')))
                    ->where('to_currency', strtoupper((string) $get('to_currency')))),
            TextInput::make('rate')->label('Kur')->helperText('1 birim kaynak para = kaç birim hedef para.')
                ->numeric()->minValue(0.00000001)->step('0.00000001')->required()
                ->rule('regex:/^\d{1,10}(\.\d{1,8})?$/'),
            TextInput::make('from_currency')->label('Kaynak para birimi')->placeholder('EUR')->length(3)->regex('/^[A-Za-z]{3}$/')->required()
                ->dehydrateStateUsing($upper),
            TextInput::make('to_currency')->label('Hedef para birimi')->default('USD')->length(3)->regex('/^[A-Za-z]{3}$/')->required()
                ->dehydrateStateUsing($upper),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('period', 'desc')
            ->columns([
                TextColumn::make('period')->label('Dönem')->sortable()->searchable(),
                TextColumn::make('pair')->label('Parite')->state(fn (ExchangeRate $record): string => $record->from_currency.' → '.$record->to_currency),
                TextColumn::make('rate')->label('Kur')->fontFamily('mono'),
                TextColumn::make('updated_at')->label('Güncellendi')->since(),
            ])
            ->filters([
                SelectFilter::make('from_currency')->label('Kaynak')
                    ->options(fn (): array => ExchangeRate::query()->distinct()->orderBy('from_currency')->pluck('from_currency', 'from_currency')->all()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExchangeRates::route('/'),
        ];
    }
}
