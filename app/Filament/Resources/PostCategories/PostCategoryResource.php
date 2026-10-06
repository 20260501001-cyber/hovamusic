<?php

namespace App\Filament\Resources\PostCategories;

use App\Filament\Resources\PostCategories\Pages\ManagePostCategories;
use App\Models\PostCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PostCategoryResource extends Resource
{
    protected static ?string $model = PostCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-folder';

    protected static string|UnitEnum|null $navigationGroup = 'İçerik';

    protected static ?string $modelLabel = 'Kategori';

    protected static ?string $pluralModelLabel = 'Blog kategorileri';

    protected static ?string $slug = 'blog-kategorileri';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Ad')->required()->maxLength(120),
            TextInput::make('slug')->label('Adres')->prefix('/blog/kategori/')->alphaDash()->maxLength(140)->unique(ignoreRecord: true)
                ->helperText('Boş bırakılırsa addan üretilir.'),
            TextInput::make('description')->label('Açıklama')->maxLength(300)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Ad')->searchable(),
                TextColumn::make('slug')->label('Adres')->fontFamily('mono'),
                TextColumn::make('posts_count')->label('Yazı')->counts('posts'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePostCategories::route('/')];
    }
}
