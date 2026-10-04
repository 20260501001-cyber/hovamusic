<?php

namespace App\Filament\Resources\Releases\Actions;

use App\Domain\Isrc\IsrcAllocator;
use App\Domain\Isrc\IsrcExhausted;
use App\Domain\Releases\InvalidTransition;
use App\Domain\Releases\NoteRequired;
use App\Domain\Releases\ReleaseRequests;
use App\Domain\Releases\ReleaseWorkflow;
use App\Domain\Releases\RequestNotAllowed;
use App\Domain\Releases\SpotifyReleaseTracker;
use App\Enums\ReleaseStatus;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\SpotifyMatchStatus;
use App\Enums\TemplateType;
use App\Models\Admin;
use App\Models\Release;
use App\Models\ReleaseRequest;
use App\Models\ReviewTemplate;
use App\Models\SpotifyMatch;
use App\Models\Track;
use App\Support\Admin\AdminUrls;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Livewire\Component;
use Throwable;

/**
 * Yayın detayındaki admin işlemleri: durum geçişleri, kaldırma talebi kararı,
 * tümünü indir, ISRC atama ve Spotify önerisini ekleme.
 */
class ReleaseActions
{
    /**
     * Hedef durum => [işlem adı, etiket, ikon, renk, şablon türü].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: TemplateType|null}>
     */
    private const TRANSITIONS = [
        'approved' => ['approve', 'Onayla', 'lucide-circle-check', 'success', null],
        'needs_changes' => ['requestChanges', 'Düzeltme iste', 'lucide-pencil-line', 'warning', TemplateType::NeedsChanges],
        'rejected' => ['reject', 'Reddet', 'lucide-circle-x', 'danger', TemplateType::Rejection],
        'delivered' => ['deliver', 'Mağazalara gönderildi', 'lucide-send', 'primary', null],
        'live' => ['goLive', 'Yayında', 'lucide-radio', 'success', null],
        'taken_down' => ['takeDown', 'Kaldırıldı', 'lucide-archive-x', 'gray', null],
    ];

    /**
     * @return list<Action>
     */
    public static function transitions(): array
    {
        $actions = [];

        foreach (self::TRANSITIONS as $status => [$name, $label, $icon, $color, $templateType]) {
            $target = ReleaseStatus::from($status);

            $actions[] = Action::make($name)
                ->label($label)
                ->icon($icon)
                ->color($color)
                ->authorize('transition')
                ->visible(fn (Release $record): bool => $record->status !== ReleaseStatus::TakedownRequested
                    && app(ReleaseWorkflow::class)->canTransition($record, $target, self::admin()))
                ->modalHeading(fn (Release $record): string => $label.': '.$record->displayTitle())
                ->modalDescription(fn (Release $record): string => $record->status->label().' → '.$target->label().'. Kullanıcıya e-posta ve panel bildirimi gider.')
                ->modalSubmitActionLabel($label)
                ->schema(fn (Release $record): array => self::noteSchema(app(ReleaseWorkflow::class)->requiresNote($record, $target), $templateType))
                ->action(function (array $data, Release $record, Action $action) use ($target, $label): void {
                    self::run($action, fn () => app(ReleaseWorkflow::class)->transition(
                        $record,
                        $target,
                        self::admin(),
                        $data['note'] ?? null,
                        isset($data['template_id']) ? ReviewTemplate::query()->find($data['template_id']) : null,
                    ), $label.' işlemi yapıldı.');
                });
        }

        return $actions;
    }

    /**
     * Kaldırma talebi durumundaki yayın için onay ve ret. Açık talep yoksa (eski kayıt)
     * doğrudan durum geçişi yapılır.
     *
     * @return list<Action>
     */
    public static function takedownDecision(): array
    {
        return [
            Action::make('approveTakedown')
                ->label('Kaldırmayı onayla')
                ->icon('lucide-archive-x')
                ->color('danger')
                ->authorize('transition')
                ->visible(fn (Release $record): bool => $record->status === ReleaseStatus::TakedownRequested)
                ->modalDescription('Yayın "Kaldırıldı" olur; kullanıcıya e-posta ve panel bildirimi gider.')
                ->modalSubmitActionLabel('Kaldırmayı onayla')
                ->schema(self::noteSchema(false, null))
                ->action(function (array $data, Release $record, Action $action): void {
                    $request = self::openTakedown($record);

                    self::run($action, fn () => $request
                        ? app(ReleaseRequests::class)->approveTakedown($request, self::admin(), $data['note'] ?? null)
                        : app(ReleaseWorkflow::class)->transition($record, ReleaseStatus::TakenDown, self::admin(), $data['note'] ?? null), 'Yayın kaldırıldı.');
                }),
            Action::make('rejectTakedown')
                ->label('Kaldırma talebini reddet')
                ->icon('lucide-undo-2')
                ->color('gray')
                ->authorize('transition')
                ->visible(fn (Release $record): bool => $record->status === ReleaseStatus::TakedownRequested
                    && app(ReleaseWorkflow::class)->statusBeforeTakedown($record) !== null)
                ->modalDescription(fn (Release $record): string => 'Yayın önceki durumuna ('.app(ReleaseWorkflow::class)->statusBeforeTakedown($record)?->label().') döner. Sebep kullanıcıya gönderilir.')
                ->modalSubmitActionLabel('Talebi reddet')
                ->schema(self::noteSchema(true, TemplateType::Rejection))
                ->action(function (array $data, Release $record, Action $action): void {
                    $request = self::openTakedown($record);
                    $template = isset($data['template_id']) ? ReviewTemplate::query()->find($data['template_id']) : null;

                    self::run($action, fn () => $request
                        ? app(ReleaseRequests::class)->rejectTakedown($request, self::admin(), (string) $data['note'])
                        : app(ReleaseWorkflow::class)->transition($record, app(ReleaseWorkflow::class)->statusBeforeTakedown($record), self::admin(), $data['note'], $template), 'Kaldırma talebi reddedildi.');
                }),
        ];
    }

    public static function downloadAll(): Action
    {
        return Action::make('downloadAll')
            ->label('Tümünü indir')
            ->icon('lucide-folder-down')
            ->color('gray')
            ->authorize('download')
            ->action(fn (Release $record, Component $livewire) => $livewire->redirect(AdminUrls::releaseArchive($record)));
    }

    public static function assignIsrc(): Action
    {
        return Action::make('assignIsrc')
            ->label('Eksik ISRC\'leri ata')
            ->icon('lucide-barcode')
            ->color('gray')
            ->authorize('update')
            ->visible(fn (Release $record): bool => app(IsrcAllocator::class)->isEnabled()
                && $record->tracks->contains(fn (Track $track): bool => blank($track->isrc)))
            ->requiresConfirmation()
            ->modalDescription(fn (Release $record): string => sprintf(
                'ISRC\'si olmayan %d parçaya sıradaki Hova Music kodu atanır (ilk kod: %s). Atanan kod bir daha kullanılmaz.',
                $record->tracks->filter(fn (Track $track): bool => blank($track->isrc))->count(),
                app(IsrcAllocator::class)->nextCode() ?? '—',
            ))
            ->action(function (Release $record, Action $action): void {
                self::run($action, function () use ($record): void {
                    foreach ($record->tracks()->whereNull('isrc')->get() as $track) {
                        $track->forceFill(['has_own_isrc' => false])->save();
                        app(IsrcAllocator::class)->assign($track, self::admin());
                    }
                }, 'ISRC\'ler atandı.');
            });
    }

    public static function acceptSpotify(): Action
    {
        return Action::make('acceptSpotify')
            ->label('Spotify bağlantısını ekle')
            ->icon('lucide-link')
            ->color('success')
            ->authorize('transition')
            ->visible(fn (Release $record): bool => $record->spotifyMatches->contains(fn (SpotifyMatch $match): bool => $match->status === SpotifyMatchStatus::Pending))
            ->modalDescription('Seçilen albüm Spotify bağlantısı olarak eklenir; yayın "Mağazalara gönderildi" durumundaysa "Yayında" olur.')
            ->modalSubmitActionLabel('Ekle ve yayına al')
            ->schema(fn (Release $record): array => [
                Radio::make('match_id')
                    ->label('Spotify albümü')
                    ->required()
                    ->options(self::pendingAlbums($record))
                    ->default(array_key_first(self::pendingAlbums($record))),
            ])
            ->action(function (array $data, Release $record, Action $action): void {
                $match = $record->spotifyMatches()->whereKey($data['match_id'])->where('status', SpotifyMatchStatus::Pending)->first();

                if ($match === null) {
                    Notification::make()->danger()->title('Öneri bulunamadı ya da işlenmiş.')->send();
                    $action->halt();
                }

                self::run($action, fn () => app(SpotifyReleaseTracker::class)->accept($match, self::admin()), 'Spotify bağlantısı eklendi.');
            });
    }

    /**
     * @return array<int, string>
     */
    public static function pendingAlbums(Release $record): array
    {
        return $record->spotifyMatches
            ->where('status', SpotifyMatchStatus::Pending)
            ->groupBy('spotify_album_id')
            ->mapWithKeys(function ($matches): array {
                $first = $matches->first();
                $by = $matches->pluck('matched_by')->unique()->map(fn (string $by): string => strtoupper($by))->implode(' + ');

                return [$first->id => $first->album_name.' · '.$by.' eşleşmesi · '.$first->album_url];
            })
            ->all();
    }

    /**
     * @return list<\Filament\Schemas\Components\Component|Field>
     */
    public static function noteSchema(bool $required, ?TemplateType $templateType): array
    {
        $fields = [];

        if ($templateType !== null) {
            $fields[] = Select::make('template_id')
                ->label('Hazır şablon')
                ->placeholder('Şablon seç (isteğe bağlı)')
                ->options(fn (): array => ReviewTemplate::query()->for($templateType)->pluck('title', 'id')->all())
                ->live()
                ->afterStateUpdated(function ($state, Set $set): void {
                    $template = $state ? ReviewTemplate::query()->find($state) : null;

                    if ($template !== null) {
                        $set('note', $template->body);
                    }
                });
        }

        $fields[] = Textarea::make('note')
            ->label($required ? 'Not (zorunlu)' : 'Not')
            ->helperText('Not kullanıcıya e-postada ve yayın geçmişinde gösterilir.')
            ->rows(5)
            ->maxLength(2000)
            ->required($required);

        return $fields;
    }

    private static function openTakedown(Release $record): ?ReleaseRequest
    {
        return $record->requests()
            ->where('type', RequestType::Takedown)
            ->where('status', RequestStatus::Open)
            ->first();
    }

    private static function run(Action $action, callable $callback, string $success): void
    {
        try {
            $callback();
        } catch (InvalidTransition|NoteRequired|RequestNotAllowed|IsrcExhausted $e) {
            Notification::make()->danger()->title('İşlem yapılamadı')->body($e->getMessage())->send();
            $action->halt();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title('İşlem yapılamadı')->body('Beklenmeyen bir hata oluştu; tekrar dene.')->send();
            $action->halt();
        }

        Notification::make()->success()->title($success)->send();
    }

    public static function admin(): Admin
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        return $admin;
    }
}
