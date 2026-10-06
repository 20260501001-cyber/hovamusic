<?php

namespace App\Filament\Resources\SeoMetas;

use App\Filament\Resources\SeoMetas\Pages\ManageSeoMetas;
use App\Models\SeoMeta;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
 * Sayfa bazında SEO. Boş alanlar sayfanın varsayılanını kullanır. Blog yazılarının
 * SEO alanları yazının kendisinde düzenlenir.
 */
class SeoMetaResource extends Resource
{
    protected static ?string $model = SeoMeta::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-search';

    protected static string|UnitEnum|null $navigationGroup = 'İçerik';

    protected static ?string $modelLabel = 'Sayfa SEO ayarı';

    protected static ?string $pluralModelLabel = 'SEO';

    protected static ?string $slug = 'seo';

    protected static ?int $navigationSort = 40;

    /**
     * @var list<string>
     */
    public const KNOWN_PATHS = ['/', '/fiyatlar', '/nasil-calisir', '/platformlar', '/sss', '/blog', '/hakkimizda', '/iletisim'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('path')->label('Sayfa yolu')->required()->maxLength(255)
                ->datalist(self::KNOWN_PATHS)
                ->regex('#^/[A-Za-z0-9\-/._]*$#')
                ->helperText('Örn. / (ana sayfa), /fiyatlar, /blog/kategori/rehber, /yasal/gizlilik-politikasi')
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (string $state): string => SeoMeta::normalizePath($state)),
            Toggle::make('noindex')->label('Arama motorlarına kapalı (noindex)')->inline(false),
            Section::make('Arama sonucu')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('title')->label('Başlık (title)')->maxLength(200)->helperText('Tam başlık; site adı eklenmez. 60 karakter civarı önerilir.'),
                    TextInput::make('canonical')->label('Canonical adres')->url()->maxLength(500)->helperText('Boşsa sayfanın kendi adresi.'),
                    Textarea::make('description')->label('Açıklama (meta description)')->rows(2)->maxLength(300)->columnSpanFull()
                        ->helperText('150-160 karakter önerilir.'),
                ]),
            Section::make('Paylaşım (Open Graph ve Twitter)')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('og_title')->label('Paylaşım başlığı')->maxLength(200),
                    Select::make('twitter_card')->label('Twitter kartı')->required()->default('summary_large_image')
                        ->options(['summary_large_image' => 'Büyük görselli', 'summary' => 'Küçük görselli']),
                    Textarea::make('og_description')->label('Paylaşım açıklaması')->rows(2)->maxLength(300)->columnSpanFull(),
                    FileUpload::make('og_image_path')->label('Paylaşım görseli')->image()->disk('public')->directory('seo')->visibility('public')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(2048)
                        ->helperText('1200×630 px önerilir.')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('path')
            ->columns([
                TextColumn::make('path')->label('Yol')->fontFamily('mono')->searchable(),
                TextColumn::make('title')->label('Başlık')->limit(50)->placeholder('Varsayılan'),
                TextColumn::make('description')->label('Açıklama')->limit(60)->placeholder('Varsayılan'),
                IconColumn::make('noindex')->label('noindex')->boolean(),
                TextColumn::make('updated_at')->label('Güncellendi')->since(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSeoMetas::route('/')];
    }
}
