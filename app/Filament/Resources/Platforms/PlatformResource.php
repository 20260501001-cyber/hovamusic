<?php

namespace App\Filament\Resources\Platforms;

use App\Filament\Resources\Platforms\Pages\ManagePlatforms;
use App\Models\Platform;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class PlatformResource extends Resource
{
    protected static ?string $model = Platform::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-store';

    protected static string|UnitEnum|null $navigationGroup = 'Katalog';

    protected static ?string $modelLabel = 'Mağaza';

    protected static ?string $pluralModelLabel = 'Mağazalar';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Ad')
                ->required()
                ->maxLength(100),
            TextInput::make('slug')
                ->label('Kısa ad')
                ->helperText('Boş bırakılırsa addan üretilir. Teslimat entegrasyonlarında kullanılır.')
                ->alphaDash()
                ->maxLength(100)
                ->unique(ignoreRecord: true),
            FileUpload::make('logo_path')
                ->label('Logo')
                ->helperText('PNG, JPG ya da WEBP; en fazla 512 KB. Kullanıcıların mağaza listesinde görünür.')
                ->image()
                ->disk('public')
                ->directory('platforms')
                ->visibility('public')
                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                ->maxSize(512),
            Toggle::make('is_active')
                ->label('Etkin')
                ->helperText('Pasif mağaza sihirbazda gösterilmez; mevcut yayınlardaki seçim korunur.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Yayın sihirbazının 4. adımında listelenen mağazalar. Sürükleyerek sırala.')
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                ImageColumn::make('logo_path')->label('Logo')->disk('public')->imageSize(28),
                TextColumn::make('name')->label('Ad')->searchable(),
                TextColumn::make('slug')->label('Kısa ad')->fontFamily('mono'),
                TextColumn::make('releases_count')->label('Yayın')->counts('releases'),
                ToggleColumn::make('is_active')->label('Etkin'),
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
            'index' => ManagePlatforms::route('/'),
        ];
    }
}
