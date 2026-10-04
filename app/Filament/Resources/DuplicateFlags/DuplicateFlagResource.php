<?php

namespace App\Filament\Resources\DuplicateFlags;

use App\Domain\Media\MediaUrl;
use App\Enums\DuplicateFlagStatus;
use App\Filament\Resources\DuplicateFlags\Pages\ManageDuplicateFlags;
use App\Models\DuplicateFlag;
use App\Models\MediaFile;
use App\Models\Track;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Aynı ses dosyası (SHA-256) birden fazla hesapta yüklendiğinde oluşan işaretler.
 * Kullanıcıya bir şey gösterilmez; inceleme ekibi karar verir.
 */
class DuplicateFlagResource extends Resource
{
    protected static ?string $model = DuplicateFlag::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-copy';

    protected static string|UnitEnum|null $navigationGroup = 'İnceleme';

    protected static ?string $modelLabel = 'Aynı ses dosyası';

    protected static ?string $pluralModelLabel = 'Aynı ses dosyaları';

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $open = DuplicateFlag::query()->where('status', DuplicateFlagStatus::Open)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Aynı ses dosyası başka bir hesapta da yüklenmiş. Dosyaları dinleyip hak sahipliğini kontrol et.')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['mediaFile.user', 'matchedMediaFile.user', 'reviewer']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Tespit')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('mediaFile.user.email')->label('Yükleyen hesap')
                    ->description(fn (DuplicateFlag $record): string => self::trackLabel($record->mediaFile)),
                TextColumn::make('matchedMediaFile.user.email')->label('Eşleşen hesap')
                    ->description(fn (DuplicateFlag $record): string => self::trackLabel($record->matchedMediaFile)),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (DuplicateFlagStatus $state): string => __('release.duplicate_statuses.'.$state->value))
                    ->color(fn (DuplicateFlagStatus $state): string => match ($state) {
                        DuplicateFlagStatus::Open => 'warning',
                        DuplicateFlagStatus::Dismissed => 'gray',
                        DuplicateFlagStatus::Confirmed => 'danger',
                    }),
                TextColumn::make('reviewer.name')->label('İnceleyen')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')
                    ->options(collect(DuplicateFlagStatus::cases())->mapWithKeys(fn (DuplicateFlagStatus $status): array => [
                        $status->value => __('release.duplicate_statuses.'.$status->value),
                    ]))
                    ->default(DuplicateFlagStatus::Open->value),
            ])
            ->recordActions([
                Action::make('listen')
                    ->label('Yüklenen dosya')
                    ->icon('lucide-file-audio')
                    ->url(fn (DuplicateFlag $record): ?string => $record->mediaFile ? MediaUrl::temporary($record->mediaFile, download: true) : null)
                    ->openUrlInNewTab(),
                Action::make('listenMatched')
                    ->label('Eşleşen dosya')
                    ->icon('lucide-file-audio')
                    ->url(fn (DuplicateFlag $record): ?string => $record->matchedMediaFile ? MediaUrl::temporary($record->matchedMediaFile, download: true) : null)
                    ->openUrlInNewTab(),
                Action::make('dismiss')
                    ->label('Sorun yok')
                    ->icon('lucide-check')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (DuplicateFlag $record): bool => $record->status === DuplicateFlagStatus::Open)
                    ->authorize('update')
                    ->action(fn (DuplicateFlag $record) => self::review($record, DuplicateFlagStatus::Dismissed)),
                Action::make('confirm')
                    ->label('Doğrula')
                    ->icon('lucide-triangle-alert')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Aynı kaydın izinsiz yüklendiğini doğruluyorsun. Yayınla ilgili işlem inceleme ekranından yapılır.')
                    ->visible(fn (DuplicateFlag $record): bool => $record->status === DuplicateFlagStatus::Open)
                    ->authorize('update')
                    ->action(fn (DuplicateFlag $record) => self::review($record, DuplicateFlagStatus::Confirmed)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDuplicateFlags::route('/'),
        ];
    }

    private static function review(DuplicateFlag $flag, DuplicateFlagStatus $status): void
    {
        $flag->forceFill([
            'status' => $status,
            'reviewed_by' => auth('admin')->id(),
            'reviewed_at' => now(),
        ])->save();
    }

    private static function trackLabel(?MediaFile $media): string
    {
        if ($media === null) {
            return 'Dosya silinmiş';
        }

        $track = Track::query()->with('release')->where('audio_file_id', $media->id)->first();

        return $track?->release
            ? $track->release->displayTitle().' · '.$track->displayTitle()
            : $media->original_name;
    }
}
