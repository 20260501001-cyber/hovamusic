<?php

namespace App\Filament\Resources\SpotifyMatches;

use App\Domain\Releases\InvalidTransition;
use App\Domain\Releases\SpotifyReleaseTracker;
use App\Enums\SpotifyMatchStatus;
use App\Filament\Resources\Releases\Actions\ReleaseActions;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Filament\Resources\SpotifyMatches\Pages\ManageSpotifyMatches;
use App\Models\SpotifyMatch;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Günlük Spotify takibinin bulduğu albümler. "Ekle" Spotify bağlantısını ekler ve
 * yayını "Yayında" yapar; "Yok say" öneriyi kapatır ve bir daha göstermez.
 */
class SpotifyMatchResource extends Resource
{
    protected static ?string $model = SpotifyMatch::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-radar';

    protected static string|UnitEnum|null $navigationGroup = 'İnceleme';

    protected static ?string $modelLabel = 'Spotify önerisi';

    protected static ?string $pluralModelLabel = 'Spotify önerileri';

    protected static ?string $slug = 'spotify-onerileri';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $pending = SpotifyMatch::query()->where('status', SpotifyMatchStatus::Pending)->distinct()->count('release_id');

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Mağazalara gönderilen yayınlar her gün UPC ve ISRC ile Spotify\'da aranır.')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['release.artists', 'track']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Bulunma')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone'))->sortable(),
                TextColumn::make('release.title')->label('Yayın')
                    ->formatStateUsing(fn (SpotifyMatch $record): string => $record->release->displayTitle())
                    ->description(fn (SpotifyMatch $record): string => $record->release->artistLine())
                    ->url(fn (SpotifyMatch $record): string => ReleaseResource::getUrl('view', ['record' => $record->release])),
                TextColumn::make('album_name')->label('Spotify albümü')
                    ->url(fn (SpotifyMatch $record): string => $record->album_url, shouldOpenInNewTab: true)
                    ->description(fn (SpotifyMatch $record): string => $record->album_url),
                TextColumn::make('matched_by')->label('Eşleşme')
                    ->formatStateUsing(fn (SpotifyMatch $record): string => $record->matched_by === 'upc' ? 'UPC' : 'ISRC · '.($record->track?->displayTitle() ?? '')),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (SpotifyMatchStatus $state): string => $state->label())
                    ->color(fn (SpotifyMatchStatus $state): string => match ($state) {
                        SpotifyMatchStatus::Pending => 'warning',
                        SpotifyMatchStatus::Accepted => 'success',
                        SpotifyMatchStatus::Dismissed => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')
                    ->options(collect(SpotifyMatchStatus::cases())->mapWithKeys(fn (SpotifyMatchStatus $s): array => [$s->value => $s->label()])->all())
                    ->default(SpotifyMatchStatus::Pending->value),
            ])
            ->recordActions([
                Action::make('accept')
                    ->label('Ekle ve yayına al')
                    ->icon('lucide-link')
                    ->color('success')
                    ->authorize('update')
                    ->visible(fn (SpotifyMatch $record): bool => $record->status === SpotifyMatchStatus::Pending)
                    ->requiresConfirmation()
                    ->modalDescription('Albüm bağlantısı Spotify mağaza bağlantısı olarak eklenir ve yayın "Yayında" olur. Kullanıcıya bildirim gider.')
                    ->action(function (SpotifyMatch $record, Action $action): void {
                        try {
                            app(SpotifyReleaseTracker::class)->accept($record, ReleaseActions::admin());
                        } catch (InvalidTransition $e) {
                            Notification::make()->danger()->title('İşlem yapılamadı')->body($e->getMessage())->send();
                            $action->halt();
                        }

                        Notification::make()->success()->title('Spotify bağlantısı eklendi.')->send();
                    }),
                Action::make('dismiss')
                    ->label('Yok say')
                    ->icon('lucide-x')
                    ->color('gray')
                    ->authorize('update')
                    ->visible(fn (SpotifyMatch $record): bool => $record->status === SpotifyMatchStatus::Pending)
                    ->requiresConfirmation()
                    ->action(fn (SpotifyMatch $record) => app(SpotifyReleaseTracker::class)->dismiss($record, ReleaseActions::admin())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSpotifyMatches::route('/'),
        ];
    }
}
