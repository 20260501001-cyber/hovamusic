<?php

namespace App\Filament\Resources\Faqs;

use App\Filament\Resources\Faqs\Pages\ManageFaqs;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Sıkça sorulan sorular: SSS sayfasında ve (işaretliyse) ana sayfada; FAQPage
 * yapılandırılmış verisi buradan üretilir.
 */
class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-circle-help';

    protected static string|UnitEnum|null $navigationGroup = 'İçerik';

    protected static ?string $modelLabel = 'Soru';

    protected static ?string $pluralModelLabel = 'SSS';

    protected static ?string $slug = 'sss';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('question')->label('Soru')->required()->maxLength(300)->columnSpanFull(),
            MarkdownEditor::make('answer')->label('Cevap')->required()->columnSpanFull()
                ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList']]),
            TextInput::make('group')->label('Bölüm')->maxLength(80)->placeholder('Genel')
                ->datalist(fn (): array => Faq::query()->whereNotNull('group')->distinct()->pluck('group')->all()),
            Toggle::make('is_published')->label('Yayında')->default(true)->inline(false),
            Toggle::make('show_on_home')->label('Ana sayfada göster')->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('question')->label('Soru')->searchable()->limit(80)->wrap(),
                TextColumn::make('group')->label('Bölüm')->placeholder('Genel'),
                ToggleColumn::make('is_published')->label('Yayında'),
                ToggleColumn::make('show_on_home')->label('Ana sayfa'),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label('Yayında'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFaqs::route('/')];
    }
}
