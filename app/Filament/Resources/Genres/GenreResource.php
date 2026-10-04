<?php

namespace App\Filament\Resources\Genres;

use App\Filament\Resources\Genres\Pages\ManageGenres;
use App\Models\Genre;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class GenreResource extends Resource
{
    protected static ?string $model = Genre::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-tags';

    protected static string|UnitEnum|null $navigationGroup = 'Katalog';

    protected static ?string $modelLabel = 'Tür';

    protected static ?string $pluralModelLabel = 'Türler';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('parent_id')
                ->label('Üst tür')
                ->helperText('Alt tür ekliyorsan bağlı olduğu türü seç; ana tür için boş bırak.')
                ->relationship('parent', 'name', fn (Builder $query) => $query->whereNull('parent_id')->orderBy('name'), ignoreRecord: true)
                ->searchable()
                ->preload(),
            TextInput::make('name')
                ->label('Ad')
                ->required()
                ->maxLength(100),
            TextInput::make('slug')
                ->label('Kısa ad')
                ->helperText('Boş bırakılırsa addan üretilir.')
                ->alphaDash()
                ->maxLength(120)
                ->unique(ignoreRecord: true),
            Toggle::make('is_active')
                ->label('Etkin')
                ->helperText('Pasif tür yeni yayınlarda seçilemez.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Kullanılan ya da alt türü olan tür silinemez; pasife alınabilir.')
            ->reorderable('sort')
            ->defaultSort('sort')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('parent'))
            ->columns([
                TextColumn::make('name')->label('Ad')->searchable(),
                TextColumn::make('parent.name')->label('Üst tür')->placeholder('Ana tür'),
                TextColumn::make('slug')->label('Kısa ad')->fontFamily('mono'),
                ToggleColumn::make('is_active')->label('Etkin'),
            ])
            ->filters([
                TernaryFilter::make('parent_id')
                    ->label('Düzey')
                    ->nullable()
                    ->trueLabel('Alt türler')
                    ->falseLabel('Ana türler'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGenres::route('/'),
        ];
    }
}
