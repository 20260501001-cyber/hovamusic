<?php

namespace App\Filament\Resources\LegalDocuments;

use App\Filament\Resources\LegalDocuments\Pages\EditLegalDocument;
use App\Filament\Resources\LegalDocuments\Pages\ListLegalDocuments;
use App\Filament\Resources\LegalDocuments\RelationManagers\VersionsRelationManager;
use App\Models\LegalDocument;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Yasal metinler. Metin sürüm olarak girilir; yayımlanan sürüm değiştirilmez,
 * düzeltme için yeni sürüm açılır. Onay kayıtları onaylanan sürüm numarasını saklar.
 */
class LegalDocumentResource extends Resource
{
    protected static ?string $model = LegalDocument::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-scale';

    protected static string|UnitEnum|null $navigationGroup = 'İçerik';

    protected static ?string $modelLabel = 'Yasal metin';

    protected static ?string $pluralModelLabel = 'Yasal metinler';

    protected static ?string $slug = 'yasal-metinler';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label('Başlık')->maxLength(200)->required(),
            TextInput::make('slug')->label('Adres')->prefix('/yasal/')->disabled()->dehydrated(false),
            TextInput::make('consent_type')->label('Onay türü')->disabled()->dehydrated(false)->placeholder('Onay alınmaz'),
            Toggle::make('is_public')->label('Sitede yayında')->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        $tz = config('hova.display_timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('currentVersion'))
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('title')->label('Metin')->description(fn (LegalDocument $record): string => '/yasal/'.$record->slug),
                TextColumn::make('currentVersion.version')->label('Yürürlükteki sürüm')->placeholder('Yayımlanmadı'),
                TextColumn::make('currentVersion.published_at')->label('Yürürlük')->dateTime('d.m.Y H:i')->timezone($tz)->placeholder('—'),
                TextColumn::make('consent_type')->label('Onay türü')->placeholder('—'),
                IconColumn::make('is_public')->label('Sitede')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->label('Sürümler'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalDocuments::route('/'),
            'edit' => EditLegalDocument::route('/{record}/duzenle'),
        ];
    }
}
