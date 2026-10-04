<?php

namespace App\Filament\Resources\Releases\RelationManagers;

use App\Models\Admin;
use App\Models\Platform;
use App\Models\ReleaseStoreLink;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Yayının mağaza bağlantıları. Kullanıcı, yayın "Yayında" olduğunda bunları görür.
 */
class StoreLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'storeLinks';

    protected static ?string $title = 'Mağaza bağlantıları';

    protected static ?string $modelLabel = 'Mağaza bağlantısı';

    protected static ?string $pluralModelLabel = 'Mağaza bağlantıları';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('platform_id')->label('Mağaza')
                ->options(fn (): array => Platform::query()->orderBy('sort')->pluck('name', 'id')->all())
                ->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('release_id', $this->getOwnerRecord()->getKey()))
                ->validationMessages(['unique' => 'Bu mağaza için bağlantı zaten var; mevcut bağlantıyı düzenle.']),
            TextInput::make('url')->label('Bağlantı')->url()->maxLength(500)->required()
                ->rule('starts_with:https://'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('platform'))
            ->paginated(false)
            ->columns([
                TextColumn::make('platform.name')->label('Mağaza'),
                TextColumn::make('url')->label('Bağlantı')->copyable()->limit(60)
                    ->url(fn (ReleaseStoreLink $record): string => $record->url, shouldOpenInNewTab: true),
                TextColumn::make('source')->label('Kaynak')->formatStateUsing(fn (string $state): string => $state === 'spotify' ? 'Spotify takibi' : 'Admin'),
            ])
            ->headerActions([
                CreateAction::make()->label('Bağlantı ekle')
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'source' => 'admin', 'confirmed_by' => auth('admin')->id()]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof Admin && $admin->isReviewer();
    }
}
