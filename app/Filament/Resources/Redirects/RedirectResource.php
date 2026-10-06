<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Models\Redirect;
use App\Models\SeoMeta;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * 301/302 yönlendirmeleri. Kaynak yol site içi bir yoldur; hedef site içi yol ya
 * da tam adres olabilir.
 */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-corner-up-right';

    protected static string|UnitEnum|null $navigationGroup = 'İçerik';

    protected static ?string $modelLabel = 'Yönlendirme';

    protected static ?string $pluralModelLabel = 'Yönlendirmeler';

    protected static ?string $slug = 'yonlendirmeler';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('from_path')->label('Eski yol')->required()->maxLength(255)
                ->regex('#^/[A-Za-z0-9\-/._~%]*$#')
                ->helperText('Site içi yol, ör. /eski-sayfa')
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (string $state): string => SeoMeta::normalizePath($state)),
            TextInput::make('to_path')->label('Yeni adres')->required()->maxLength(500)
                ->helperText('Site içi yol (/yeni-sayfa) ya da tam adres (https://...)')
                ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    $to = trim((string) $value);

                    if (! str_starts_with($to, '/') && ! preg_match('#^https?://#i', $to)) {
                        $fail('Hedef "/" ile başlayan bir yol ya da http(s) adresi olmalı.');
                    }

                    if (str_starts_with($to, '/') && SeoMeta::normalizePath($to) === SeoMeta::normalizePath((string) $get('from_path'))) {
                        $fail('Eski ve yeni yol aynı olamaz.');
                    }
                }),
            Select::make('code')->label('Tür')->required()->default(301)
                ->options([301 => '301 (kalıcı)', 302 => '302 (geçici)']),
            Toggle::make('is_active')->label('Etkin')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('from_path')
            ->columns([
                TextColumn::make('from_path')->label('Eski yol')->fontFamily('mono')->searchable(),
                TextColumn::make('to_path')->label('Yeni adres')->fontFamily('mono')->searchable()->limit(50),
                TextColumn::make('code')->label('Tür'),
                TextColumn::make('hits')->label('Kullanım')->numeric()->sortable(),
                TextColumn::make('last_hit_at')->label('Son kullanım')->since()->placeholder('—')->sortable(),
                ToggleColumn::make('is_active')->label('Etkin'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRedirects::route('/')];
    }
}
