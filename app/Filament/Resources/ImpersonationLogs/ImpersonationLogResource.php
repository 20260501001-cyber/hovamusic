<?php

namespace App\Filament\Resources\ImpersonationLogs;

use App\Filament\Resources\ImpersonationLogs\Pages\ListImpersonationLogs;
use App\Models\ImpersonationLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * "Kullanıcı olarak görüntüle" kayıtları: kim, kimi, hangi sebeple, ne zaman başlayıp bitti.
 */
class ImpersonationLogResource extends Resource
{
    protected static ?string $model = ImpersonationLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-eye';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $modelLabel = 'Görüntüleme kaydı';

    protected static ?string $pluralModelLabel = 'Görüntüleme kayıtları';

    protected static ?string $slug = 'goruntuleme-kayitlari';

    protected static ?int $navigationSort = 96;

    public static function table(Table $table): Table
    {
        $tz = config('hova.display_timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['admin', 'user']))
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('started_at')->label('Başlangıç')->dateTime('d.m.Y H:i:s')->timezone($tz)->sortable(),
                TextColumn::make('ended_at')->label('Bitiş')->dateTime('d.m.Y H:i:s')->timezone($tz)->placeholder('Açık'),
                TextColumn::make('admin.name')->label('Admin'),
                TextColumn::make('user.email')->label('Kullanıcı')->searchable(),
                TextColumn::make('reason')->label('Sebep')->wrap()->limit(120),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImpersonationLogs::route('/'),
        ];
    }
}
