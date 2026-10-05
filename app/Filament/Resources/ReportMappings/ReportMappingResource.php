<?php

namespace App\Filament\Resources\ReportMappings;

use App\Filament\Resources\ReportMappings\Pages\ManageReportMappings;
use App\Models\ReportMapping;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Rapor sütun eşleştirme profilleri: alan => rapordaki sütun başlığı. Başlıklar büyük
 * küçük harf ve boşluk farkı gözetmeden aranır.
 */
class ReportMappingResource extends Resource
{
    protected static ?string $model = ReportMapping::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-columns-3';

    protected static string|UnitEnum|null $navigationGroup = 'Finans';

    protected static ?string $modelLabel = 'Eşleştirme profili';

    protected static ?string $pluralModelLabel = 'Sütun eşleştirme';

    protected static ?string $slug = 'sutun-eslestirme';

    protected static ?int $navigationSort = 60;

    /**
     * @var array<string, string>
     */
    public const FIELD_LABELS = [
        'sales_month' => 'Satış ayı',
        'platform' => 'Platform',
        'country' => 'Ülke',
        'isrc' => 'ISRC',
        'upc' => 'UPC',
        'artist_name' => 'Sanatçı',
        'release_title' => 'Yayın adı',
        'track_title' => 'Parça adı',
        'sale_type' => 'Satış türü',
        'quantity' => 'Adet',
        'net_amount' => 'Net gelir (zorunlu)',
        'currency' => 'Para birimi',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Profil adı')->maxLength(120)->required(),
            Toggle::make('is_default')->label('Varsayılan profil')->inline(false)
                ->helperText('Yeni rapor yüklerken önceden seçili gelir.'),
            TextInput::make('header_row')->label('Başlık satırı')->integer()->minValue(1)->maxValue(50)->default(1)->required(),
            Select::make('delimiter')->label('CSV ayırıcı')->required()->default('auto')
                ->options(['auto' => 'Otomatik', ',' => 'Virgül (,)', ';' => 'Noktalı virgül (;)', "\t" => 'Sekme', '|' => 'Dikey çizgi (|)']),
            Select::make('decimal_separator')->label('Ondalık ayırıcı')->required()->default('auto')
                ->options(['auto' => 'Otomatik', '.' => 'Nokta (1234.56)', ',' => 'Virgül (1234,56)']),
            TextInput::make('default_currency')->label('Varsayılan para birimi')->length(3)->regex('/^[A-Za-z]{3}$/')
                ->helperText('Raporda para birimi sütunu yoksa kullanılır.')
                ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper($state) : null),
            Section::make('Sütun başlıkları')
                ->description('Rapordaki başlığı aynen yaz. Net gelir ile ISRC ya da UPC zorunludur; boş bırakılan alan okunmaz.')
                ->columns(3)
                ->columnSpanFull()
                ->schema(collect(self::FIELD_LABELS)
                    ->map(fn (string $label, string $field) => TextInput::make('columns.'.$field)->label($label)->maxLength(120)
                        ->required($field === 'net_amount'))
                    ->values()
                    ->all()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Profil')->searchable(),
                IconColumn::make('is_default')->label('Varsayılan')->boolean(),
                TextColumn::make('columns.net_amount')->label('Net gelir sütunu')->placeholder('—'),
                TextColumn::make('columns.isrc')->label('ISRC sütunu')->placeholder('—'),
                TextColumn::make('updated_at')->label('Güncellendi')->since(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReportMappings::route('/'),
        ];
    }
}
