<?php

namespace App\Filament\Resources\ReviewTemplates;

use App\Enums\TemplateType;
use App\Filament\Resources\ReviewTemplates\Pages\ManageReviewTemplates;
use App\Models\ReviewTemplate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
 * Sık kullanılan düzeltme ve ret sebepleri. Durum değiştirirken seçilir; metin
 * gönderilmeden önce düzenlenebilir.
 */
class ReviewTemplateResource extends Resource
{
    protected static ?string $model = ReviewTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-message-square-text';

    protected static string|UnitEnum|null $navigationGroup = 'İnceleme';

    protected static ?string $modelLabel = 'İnceleme şablonu';

    protected static ?string $pluralModelLabel = 'İnceleme şablonları';

    protected static ?string $slug = 'inceleme-sablonlari';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('type')->label('Kullanım')->required()
                ->options(collect(TemplateType::cases())->mapWithKeys(fn (TemplateType $t): array => [$t->value => $t->label()])->all()),
            TextInput::make('title')->label('Başlık')->helperText('Yalnızca adminler görür.')->maxLength(150)->required(),
            Textarea::make('body')->label('Kullanıcıya gidecek metin')->rows(6)->maxLength(2000)->required()->columnSpanFull(),
            Toggle::make('is_active')->label('Kullanımda')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('type')->label('Kullanım')->badge()
                    ->formatStateUsing(fn (TemplateType $state): string => $state->label())
                    ->color(fn (TemplateType $state): string => $state === TemplateType::Rejection ? 'danger' : 'warning'),
                TextColumn::make('title')->label('Başlık')->searchable(),
                TextColumn::make('body')->label('Metin')->limit(90)->wrap(),
                IconColumn::make('is_active')->label('Kullanımda')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Kullanım')
                    ->options(collect(TemplateType::cases())->mapWithKeys(fn (TemplateType $t): array => [$t->value => $t->label()])->all()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReviewTemplates::route('/'),
        ];
    }
}
