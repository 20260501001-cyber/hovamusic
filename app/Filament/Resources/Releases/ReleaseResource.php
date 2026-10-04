<?php

namespace App\Filament\Resources\Releases;

use App\Enums\ReleaseStatus;
use App\Filament\Resources\Releases\Pages\EditRelease;
use App\Filament\Resources\Releases\Pages\ListReleases;
use App\Filament\Resources\Releases\Pages\ViewRelease;
use App\Filament\Resources\Releases\RelationManagers\StoreLinksRelationManager;
use App\Filament\Resources\Releases\RelationManagers\TracksRelationManager;
use App\Filament\Resources\Releases\Schemas\ReleaseForm;
use App\Filament\Resources\Releases\Schemas\ReleaseInfolist;
use App\Filament\Resources\Releases\Tables\ReleasesTable;
use App\Models\Release;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Yayın inceleme: liste, detay (kopyalanabilir metadata, dinleme, indirme), durum
 * geçişleri ve her durumda metadata düzenleme. Süper Admin ve İnceleme Editörü.
 */
class ReleaseResource extends Resource
{
    protected static ?string $model = Release::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-disc-3';

    protected static string|UnitEnum|null $navigationGroup = 'İnceleme';

    protected static ?string $modelLabel = 'Yayın';

    protected static ?string $pluralModelLabel = 'Yayınlar';

    protected static ?string $slug = 'yayinlar';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationBadge(): ?string
    {
        $pending = Release::query()->where('status', ReleaseStatus::InReview)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'İnceleme bekleyen yayın';
    }

    /**
     * Taslaklar admin listesinde de görünür; kullanıcı silmediği sürece kalır.
     *
     * @return Builder<Release>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutTrashed();
    }

    public static function form(Schema $schema): Schema
    {
        return ReleaseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReleaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReleasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TracksRelationManager::class,
            StoreLinksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReleases::route('/'),
            'view' => ViewRelease::route('/{record}'),
            'edit' => EditRelease::route('/{record}/duzenle'),
        ];
    }

    public static function statusColor(ReleaseStatus $status): string
    {
        return match ($status) {
            ReleaseStatus::Draft, ReleaseStatus::TakenDown => 'gray',
            ReleaseStatus::InReview => 'info',
            ReleaseStatus::NeedsChanges, ReleaseStatus::TakedownRequested => 'warning',
            ReleaseStatus::Approved, ReleaseStatus::Delivered => 'primary',
            ReleaseStatus::Live => 'success',
            ReleaseStatus::Rejected => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return collect(ReleaseStatus::cases())
            ->mapWithKeys(fn (ReleaseStatus $status): array => [$status->value => $status->label()])
            ->all();
    }
}
