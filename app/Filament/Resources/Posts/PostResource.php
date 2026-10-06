<?php

namespace App\Filament\Resources\Posts;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Blog ve rehber yazıları. Gövde Markdown; kapaktan WebP/AVIF sürümleri üretilir.
 */
class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-newspaper';

    protected static string|UnitEnum|null $navigationGroup = 'İçerik';

    protected static ?string $modelLabel = 'Yazı';

    protected static ?string $pluralModelLabel = 'Blog yazıları';

    protected static ?string $slug = 'blog-yazilari';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('İçerik')
                ->columnSpan(2)
                ->schema([
                    TextInput::make('title')->label('Başlık')->maxLength(200)->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set, ?string $state) => blank($get('slug')) ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->label('Adres')->prefix('/blog/')->maxLength(220)->alphaDash()->required()
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('locale', 'tr')),
                    Textarea::make('excerpt')->label('Özet')->rows(2)->maxLength(400)
                        ->helperText('Listelerde ve arama sonucu açıklamasında kullanılır.'),
                    MarkdownEditor::make('body')->label('Metin')->required()
                        ->toolbarButtons([['bold', 'italic', 'link'], ['heading'], ['blockquote', 'codeBlock', 'bulletList', 'orderedList', 'table'], ['undo', 'redo']]),
                ]),
            Section::make('Yayın')
                ->columnSpan(1)
                ->schema([
                    Select::make('status')->label('Durum')->required()->default(PostStatus::Draft->value)
                        ->options(collect(PostStatus::cases())->mapWithKeys(fn (PostStatus $s): array => [$s->value => $s->label()])->all()),
                    DateTimePicker::make('published_at')->label('Yayın tarihi')->seconds(false)->timezone(config('hova.display_timezone'))
                        ->helperText('Boşsa yayına alındığı an. İleri bir tarih seçilirse o zamana kadar görünmez.'),
                    Select::make('post_category_id')->label('Kategori')->relationship('category', 'name')->searchable()->preload()
                        ->createOptionForm([
                            TextInput::make('name')->label('Ad')->required()->maxLength(120),
                            TextInput::make('description')->label('Açıklama')->maxLength(300),
                        ]),
                    TextInput::make('author_name')->label('Yazar')->maxLength(120)->placeholder('Hova Music'),
                    FileUpload::make('cover_path')->label('Kapak görseli')
                        ->image()->disk('public')->directory('blog')->visibility('public')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(5120)
                        ->helperText('En az 1280 px genişlik önerilir. WebP ve AVIF sürümleri otomatik üretilir.'),
                    TextInput::make('cover_alt')->label('Kapak açıklaması (alt metin)')->maxLength(200)
                        ->requiredWith('cover_path'),
                ]),
            Section::make('SEO')
                ->columnSpan(2)
                ->collapsed()
                ->schema([
                    TextInput::make('seo_title')->label('SEO başlığı')->maxLength(200)->helperText('Boşsa yazı başlığı. 60 karakter civarı önerilir.'),
                    Textarea::make('seo_description')->label('SEO açıklaması')->rows(2)->maxLength(300)->helperText('Boşsa özet. 150-160 karakter önerilir.'),
                    Toggle::make('noindex')->label('Arama motorlarına kapalı (noindex)'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category:id,name'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Başlık')->searchable()->limit(60)
                    ->description(fn (Post $record): string => '/blog/'.$record->slug),
                TextColumn::make('category.name')->label('Kategori')->placeholder('—'),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (PostStatus $state): string => $state->label())
                    ->color(fn (PostStatus $state): string => $state === PostStatus::Published ? 'success' : 'gray'),
                TextColumn::make('published_at')->label('Yayın')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone'))->placeholder('—')->sortable(),
                TextColumn::make('updated_at')->label('Güncellendi')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')
                    ->options(collect(PostStatus::cases())->mapWithKeys(fn (PostStatus $s): array => [$s->value => $s->label()])->all()),
                SelectFilter::make('post_category_id')->label('Kategori')->relationship('category', 'name'),
            ])
            ->recordActions([
                Action::make('open')->label('Sitede aç')->icon('lucide-external-link')
                    ->url(fn (Post $record): string => $record->url(), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/yeni'),
            'edit' => EditPost::route('/{record}/duzenle'),
        ];
    }
}
