<?php

namespace App\Filament\Resources\Releases\Schemas;

use App\Enums\ArtistRole;
use App\Enums\ReleaseType;
use App\Enums\TerritoryMode;
use App\Models\Genre;
use App\Rules\Upc;
use App\Support\Locale\Countries;
use App\Support\Locale\Languages;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Admin metadata düzenleme. Admin her durumda düzenleyebilir; kullanıcı panelindeki
 * gönderim kuralları burada zorunlu tutulmaz. Her değişiklik audit log'a yazılır.
 */
class ReleaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Yayın')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title')->label('Başlık')->maxLength(200)->required(),
                    TextInput::make('version')->label('Sürüm')->maxLength(120),
                    Select::make('type')->label('Tür')
                        ->options(collect(ReleaseType::cases())->mapWithKeys(fn (ReleaseType $type): array => [$type->value => $type->label()])->all()),
                    TextInput::make('label_name')->label('Plak şirketi')->maxLength(150)->required(),
                    Select::make('genre_id')->label('Tür (müzik)')
                        ->options(fn (): array => Genre::query()->topLevel()->orderBy('sort')->orderBy('name')->pluck('name', 'id')->all())
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('subgenre_id', null)),
                    Select::make('subgenre_id')->label('Alt tür')
                        ->options(fn (Get $get): array => $get('genre_id')
                            ? Genre::query()->where('parent_id', $get('genre_id'))->orderBy('sort')->orderBy('name')->pluck('name', 'id')->all()
                            : []),
                    Select::make('language')->label('Dil')->options(Languages::options())->searchable(),
                    Toggle::make('explicit')->label('Açık içerik')->inline(false),
                    DatePicker::make('release_date')->label('Yayın tarihi')->native(false)->displayFormat('d.m.Y'),
                    DatePicker::make('original_release_date')->label('İlk yayın tarihi')->native(false)->displayFormat('d.m.Y'),
                    TextInput::make('p_line')->label('℗ satırı')->maxLength(200),
                    TextInput::make('c_line')->label('© satırı')->maxLength(200),
                    TextInput::make('upc')->label('UPC')
                        ->helperText('12 ya da 13 hane; kontrol hanesi doğrulanır.')
                        ->maxLength(13)
                        ->rule(new Upc)
                        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? preg_replace('/\D/', '', $state) : null),
                ]),

            Section::make('Sanatçılar')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('artists')
                        ->hiddenLabel()
                        ->relationship('artists')
                        ->orderColumn('position')
                        ->columns(4)
                        ->addActionLabel('Sanatçı ekle')
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                        ->schema([
                            TextInput::make('name')->label('Ad')->maxLength(150)->required(),
                            Select::make('role')->label('Rol')->required()
                                ->options(collect(ArtistRole::cases())->mapWithKeys(fn (ArtistRole $role): array => [$role->value => $role->label()])->all()),
                            TextInput::make('spotify_artist_id')->label('Spotify kimliği')->maxLength(32),
                            TextInput::make('apple_music_id')->label('Apple Music kimliği')->maxLength(32),
                            Hidden::make('artist_id'),
                        ]),
                ]),

            Section::make('Dağıtım')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    CheckboxList::make('platforms')->label('Mağazalar')
                        ->relationship('platforms', 'name', fn ($query) => $query->orderBy('sort'))
                        ->columns(3)
                        ->columnSpanFull(),
                    Select::make('territory_mode')->label('Bölge')
                        ->options(collect(TerritoryMode::cases())->mapWithKeys(fn (TerritoryMode $mode): array => [$mode->value => $mode->label()])->all())
                        ->required()
                        ->live(),
                    Select::make('territories')->label('Ülkeler')
                        ->multiple()
                        ->searchable()
                        ->options(Countries::options())
                        ->visible(fn (Get $get): bool => in_array($get('territory_mode'), [TerritoryMode::Include->value, TerritoryMode::Exclude->value, TerritoryMode::Include, TerritoryMode::Exclude], true)),
                ]),
        ]);
    }
}
