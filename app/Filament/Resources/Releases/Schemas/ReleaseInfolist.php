<?php

namespace App\Filament\Resources\Releases\Schemas;

use App\Domain\Media\MediaUrl;
use App\Enums\ArtistRole;
use App\Enums\CreditRole;
use App\Enums\ReleaseStatus;
use App\Enums\RequestStatus;
use App\Enums\SpotifyMatchStatus;
use App\Enums\TerritoryMode;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Release;
use App\Models\ReleaseStatusLog;
use App\Models\SpotifyMatch;
use App\Models\Track;
use App\Support\Format;
use App\Support\Locale\Countries;
use App\Support\Locale\Languages;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Yayın detayı: her alan kopyalanabilir; kapak önizlenir, parçalar tarayıcıda
 * dinlenir ve dosyalar tek tek indirilir.
 */
class ReleaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Kapak')
                ->schema([
                    ImageEntry::make('cover_preview')
                        ->hiddenLabel()
                        ->state(fn (Release $record): ?string => $record->cover?->isValid() ? MediaUrl::temporary($record->cover) : null)
                        ->imageSize(240)
                        ->alt('Kapak'),
                    ViewEntry::make('cover_file')->hiddenLabel()->view('filament.releases.cover-file'),
                ])
                ->columnSpan(1),

            Section::make('Yayın bilgileri')
                ->columns(2)
                ->columnSpan(2)
                ->schema([
                    self::text('display_title', 'Başlık', fn (Release $r): string => (string) $r->title),
                    self::text('version', 'Sürüm'),
                    TextEntry::make('status')->label('Durum')->badge()
                        ->formatStateUsing(fn (ReleaseStatus $state): string => $state->label())
                        ->color(fn (ReleaseStatus $state): string => ReleaseResource::statusColor($state)),
                    self::text('type_label', 'Tür', fn (Release $r): ?string => $r->type?->label()),
                    self::text('primary_artists', 'Ana sanatçı(lar)', fn (Release $r): string => $r->artists->where('role', ArtistRole::Primary->value)->pluck('name')->implode(', ')),
                    self::text('featuring_artists', 'Konuk sanatçı(lar)', fn (Release $r): string => $r->artists->where('role', ArtistRole::Featuring->value)->pluck('name')->implode(', ')),
                    self::text('artist_ids', 'Ana sanatçı mağaza kimlikleri', fn (Release $r): string => $r->artists
                        ->where('role', ArtistRole::Primary->value)
                        ->map(fn ($a): string => $a->name.': Spotify '.($a->spotify_artist_id ?: '—').' · Apple '.($a->apple_music_id ?: '—'))
                        ->implode("\n"))->columnSpanFull(),
                    self::text('label_name', 'Plak şirketi'),
                    self::text('genre_label', 'Tür (müzik)', fn (Release $r): string => trim(($r->genre?->name ?? '').($r->subgenre ? ' / '.$r->subgenre->name : ''), ' /')),
                    self::text('language_label', 'Dil', fn (Release $r): ?string => $r->language ? Languages::name($r->language).' ('.$r->language.')' : null),
                    self::text('release_date_label', 'Yayın tarihi', fn (Release $r): ?string => $r->release_date?->format('d.m.Y')),
                    self::text('original_release_date_label', 'İlk yayın tarihi', fn (Release $r): ?string => $r->original_release_date?->format('d.m.Y')),
                    self::text('p_line', '℗ satırı'),
                    self::text('c_line', '© satırı'),
                    self::text('upc', 'UPC')->fontFamily('mono'),
                    self::text('explicit_label', 'Açık içerik', fn (Release $r): string => $r->explicit ? 'Evet' : 'Hayır'),
                    self::text('territories_label', 'Bölgeler', fn (Release $r): string => self::territories($r))->columnSpanFull(),
                    self::text('stores_label', 'Mağazalar', fn (Release $r): string => $r->platforms->sortBy('sort')->pluck('name')->implode(', '))->columnSpanFull(),
                ]),

            Section::make('Kullanıcı ve gönderim')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('user.email')->label('Kullanıcı')->copyable()
                        ->url(fn (Release $r): ?string => $r->user && UserResource::canView($r->user) ? UserResource::getUrl('view', ['record' => $r->user]) : null),
                    self::text('user_name', 'Ad', fn (Release $r): ?string => $r->user?->name),
                    self::text('ulid', 'Yayın kimliği')->fontFamily('mono'),
                    TextEntry::make('submitted_at')->label('Gönderim')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone'))->placeholder('—'),
                    TextEntry::make('locked_at')->label('Onay')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone'))->placeholder('—'),
                    TextEntry::make('spotify_checked_at')->label('Son Spotify kontrolü')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone'))->placeholder('—'),
                ]),

            Section::make('Parçalar')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('tracks')
                        ->hiddenLabel()
                        ->columns(3)
                        ->schema([
                            self::text('track_title', 'Parça', fn (Track $t): string => Format::position($t->position).'. '.(string) $t->title),
                            self::text('version', 'Sürüm'),
                            self::text('isrc', 'ISRC', fn (Track $t): ?string => $t->isrc)
                                ->fontFamily('mono')
                                ->helperText(fn (Track $t): ?string => $t->isrc_source ? 'Kaynak: '.$t->isrc_source->label() : ($t->has_own_isrc ? null : 'Gönderimde atanacak')),
                            self::text('track_primary', 'Ana sanatçı(lar)', fn (Track $t): string => self::trackPrimary($t)),
                            self::text('track_featuring', 'Konuk sanatçı(lar)', fn (Track $t): string => $t->artists->where('role', ArtistRole::Featuring->value)->pluck('name')->implode(', ')),
                            self::text('track_language', 'Dil', fn (Track $t): ?string => $t->language ? Languages::name($t->language).' ('.$t->language.')' : null),
                            self::text('composers', 'Besteci(ler)', fn (Track $t): string => implode(', ', $t->creditNames(CreditRole::Composer))),
                            self::text('lyricists', 'Söz yazar(lar)ı', fn (Track $t): string => implode(', ', $t->creditNames(CreditRole::Lyricist))),
                            self::text('producers', 'Yapımcı(lar)', fn (Track $t): string => implode(', ', $t->creditNames(CreditRole::Producer))),
                            self::text('track_explicit', 'Açık içerik', fn (Track $t): string => $t->explicit ? 'Evet' : 'Hayır'),
                            self::text('track_duration', 'Süre', fn (Track $t): ?string => $t->duration_ms ? Format::duration($t->duration_ms) : null),
                            self::text('preview_start_sec', 'Önizleme başlangıcı (sn)', fn (Track $t): string => (string) $t->preview_start_sec),
                            ViewEntry::make('audio_player')->hiddenLabel()->view('filament.releases.track-audio')->columnSpanFull(),
                            self::text('lyrics', 'Şarkı sözü')->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line']),
                        ]),
                ]),

            Section::make('Durum geçmişi')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('statusLogs')
                        ->hiddenLabel()
                        ->columns(4)
                        ->placeholder('Durum değişikliği yok.')
                        ->schema([
                            TextEntry::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone')),
                            TextEntry::make('change')->label('Değişiklik')
                                ->state(fn (ReleaseStatusLog $log): string => ($log->from_status?->label() ?? '—').' → '.$log->to_status->label()),
                            TextEntry::make('actor')->label('Yapan')->state(fn (ReleaseStatusLog $log): string => self::actor($log)),
                            TextEntry::make('template.title')->label('Şablon')->placeholder('—'),
                            TextEntry::make('note')->label('Not')->placeholder('—')->copyable()->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line']),
                        ]),
                ]),

            Section::make('Talepler')
                ->columnSpanFull()
                ->collapsible()
                ->visible(fn (Release $r): bool => $r->requests->isNotEmpty())
                ->schema([
                    RepeatableEntry::make('requests')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('type')->label('Tür')->formatStateUsing(fn ($state): string => $state->label()),
                            TextEntry::make('status')->label('Durum')->badge()
                                ->formatStateUsing(fn (RequestStatus $state): string => $state->label())
                                ->color(fn (RequestStatus $state): string => match ($state) {
                                    RequestStatus::Open => 'warning',
                                    RequestStatus::Resolved => 'success',
                                    RequestStatus::Rejected => 'danger',
                                }),
                            TextEntry::make('created_at')->label('Oluşturma')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone')),
                            TextEntry::make('handler.name')->label('Yanıtlayan')->placeholder('—'),
                            TextEntry::make('message')->label('Kullanıcının mesajı')->copyable()->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line']),
                            TextEntry::make('admin_note')->label('Yanıt')->placeholder('—')->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line']),
                        ]),
                ]),

            Section::make('Spotify önerileri')
                ->description('Yayın "Mağazalara gönderildi" durumundayken her gün UPC ve ISRC ile Spotify\'da aranır.')
                ->columnSpanFull()
                ->collapsible()
                ->visible(fn (Release $r): bool => $r->spotifyMatches->isNotEmpty())
                ->schema([
                    RepeatableEntry::make('spotifyMatches')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('album_name')->label('Albüm')
                                ->url(fn (SpotifyMatch $m): string => $m->album_url, shouldOpenInNewTab: true),
                            TextEntry::make('matched_by')->label('Eşleşme')
                                ->state(fn (SpotifyMatch $m): string => $m->matched_by === 'upc' ? 'UPC' : 'ISRC · '.($m->track?->displayTitle() ?? '')),
                            TextEntry::make('status')->label('Durum')->badge()
                                ->formatStateUsing(fn (SpotifyMatchStatus $state): string => $state->label())
                                ->color(fn (SpotifyMatchStatus $state): string => match ($state) {
                                    SpotifyMatchStatus::Pending => 'warning',
                                    SpotifyMatchStatus::Accepted => 'success',
                                    SpotifyMatchStatus::Dismissed => 'gray',
                                }),
                            TextEntry::make('album_url')->label('Bağlantı')->copyable()->limit(40),
                        ]),
                ]),

        ]);
    }

    /**
     * Kopyalanabilir metin alanı; boşsa çizgi gösterir.
     */
    private static function text(string $name, string $label, ?\Closure $state = null): TextEntry
    {
        $entry = TextEntry::make($name)->label($label)->placeholder('—')->copyable()->copyMessage('Kopyalandı');

        return $state ? $entry->state($state) : $entry;
    }

    private static function territories(Release $release): string
    {
        $countries = collect($release->territories ?? [])->map(fn (string $code): string => Countries::name($code).' ('.$code.')')->implode(', ');

        return match ($release->territory_mode) {
            TerritoryMode::Worldwide => 'Tüm dünya',
            TerritoryMode::Include => 'Yalnızca: '.$countries,
            TerritoryMode::Exclude => 'Tüm dünya, hariç: '.$countries,
        };
    }

    private static function trackPrimary(Track $track): string
    {
        $own = $track->artists->where('role', ArtistRole::Primary->value)->pluck('name');

        if ($own->isEmpty()) {
            $own = ($track->release?->artists ?? collect())->where('role', ArtistRole::Primary->value)->pluck('name');
        }

        return $own->implode(', ');
    }

    private static function actor(ReleaseStatusLog $log): string
    {
        return match ($log->actor_type) {
            'admin' => $log->admin?->name ?? 'Admin',
            'user' => 'Kullanıcı',
            default => 'Sistem',
        };
    }
}
