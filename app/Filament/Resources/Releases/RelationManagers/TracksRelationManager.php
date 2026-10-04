<?php

namespace App\Filament\Resources\Releases\RelationManagers;

use App\Domain\Isrc\IsrcAllocator;
use App\Enums\ArtistRole;
use App\Enums\CreditRole;
use App\Enums\IsrcSource;
use App\Filament\Resources\Releases\Pages\EditRelease;
use App\Models\Admin;
use App\Models\Track;
use App\Rules\AdminTrackIsrc;
use App\Rules\Isrc;
use App\Support\Format;
use App\Support\Locale\Languages;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Parça metadata'sı. Admin parça ekleyip silmez (ses dosyası kullanıcıdan gelir);
 * bilgileri ve ISRC'yi her durumda düzenleyebilir. Hova Music önekli bir kod elle
 * girilirse ISRC kayıtlarına geçer ve başka parçaya verilemez.
 */
class TracksRelationManager extends RelationManager
{
    protected static string $relationship = 'tracks';

    protected static ?string $title = 'Parçalar';

    protected static ?string $modelLabel = 'Parça';

    protected static ?string $pluralModelLabel = 'Parçalar';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label('Parça adı')->maxLength(200)->required(),
            TextInput::make('version')->label('Sürüm')->maxLength(120),
            TextInput::make('isrc')->label('ISRC')
                ->helperText('Tireli ya da tiresiz yazılabilir. Boş bırakılırsa "Eksik ISRC\'leri ata" ile Hova Music kodu verilebilir.')
                ->maxLength(15)
                ->rule(fn (?Track $record): AdminTrackIsrc => new AdminTrackIsrc($record)),
            Select::make('language')->label('Dil')->options(Languages::options())->searchable(),
            TextInput::make('preview_start_sec')->label('Önizleme başlangıcı')->suffix('sn')->integer()->minValue(0)->maxValue(3600)->required(),
            Toggle::make('explicit')->label('Açık içerik')->inline(false),
            Repeater::make('artists')->label('Parça sanatçıları')
                ->relationship('artists')
                ->orderColumn('position')
                ->columns(2)
                ->columnSpanFull()
                ->addActionLabel('Sanatçı ekle')
                ->defaultItems(0)
                ->schema([
                    TextInput::make('name')->label('Ad')->maxLength(150)->required(),
                    Select::make('role')->label('Rol')->required()->default(ArtistRole::Featuring->value)
                        ->options(collect(ArtistRole::cases())->mapWithKeys(fn (ArtistRole $role): array => [$role->value => $role->label()])->all()),
                    TextInput::make('spotify_artist_id')->label('Spotify kimliği')->maxLength(32),
                    TextInput::make('apple_music_id')->label('Apple Music kimliği')->maxLength(32),
                    Hidden::make('artist_id'),
                ]),
            Repeater::make('credits')->label('Besteci, söz yazarı ve yapımcılar')
                ->relationship('credits')
                ->orderColumn('position')
                ->columns(2)
                ->columnSpanFull()
                ->addActionLabel('Kişi ekle')
                ->defaultItems(0)
                ->schema([
                    Select::make('role')->label('Rol')->required()
                        ->options(collect(CreditRole::cases())->mapWithKeys(fn (CreditRole $role): array => [$role->value => $role->label()])->all()),
                    TextInput::make('name')->label('Ad soyad')->maxLength(150)->required(),
                ]),
            Textarea::make('lyrics')->label('Şarkı sözü')->rows(8)->maxLength(20000)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('audio'))
            ->defaultSort('position')
            ->paginated(false)
            ->columns([
                TextColumn::make('position')->label('#'),
                TextColumn::make('title')->label('Parça')->formatStateUsing(fn (Track $record): string => $record->displayTitle())->wrap(),
                TextColumn::make('isrc')->label('ISRC')->fontFamily('mono')->copyable()
                    ->formatStateUsing(fn (Track $record): ?string => $record->formattedIsrc())
                    ->description(fn (Track $record): ?string => $record->isrc_source?->label())
                    ->placeholder('—'),
                TextColumn::make('duration_ms')->label('Süre')->formatStateUsing(fn (?int $state): string => Format::duration($state)),
                TextColumn::make('language')->label('Dil')->formatStateUsing(fn (?string $state): ?string => Languages::name($state))->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth('4xl')
                    ->using(fn (Track $record, array $data): Track => $this->saveTrack($record, $data)),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveTrack(Track $track, array $data): Track
    {
        $isrc = filled($data['isrc'] ?? null) ? Isrc::normalize((string) $data['isrc']) : null;
        unset($data['isrc']);

        DB::transaction(function () use ($track, $data, $isrc): void {
            $track->fill($data);

            if ($isrc !== $track->isrc) {
                /** @var Admin $admin */
                $admin = auth('admin')->user();

                if ($isrc !== null) {
                    app(IsrcAllocator::class)->claimManual($track, $isrc, $admin);
                }

                $track->forceFill([
                    'isrc' => $isrc,
                    'isrc_source' => $isrc !== null ? IsrcSource::Admin : null,
                    'has_own_isrc' => $isrc !== null,
                ]);
            }

            $track->save();
        });

        return $track;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $admin = auth('admin')->user();

        return $pageClass === EditRelease::class && $admin instanceof Admin && $admin->isReviewer();
    }
}
