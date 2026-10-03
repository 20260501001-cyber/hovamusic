<?php

namespace App\Filament\Resources\Admins;

use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Filament\Resources\Admins\Schemas\AdminForm;
use App\Filament\Resources\Admins\Tables\AdminsTable;
use App\Models\Admin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class AdminResource extends Resource
{
    protected static ?string $model = Admin::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $modelLabel = 'admin';

    protected static ?string $pluralModelLabel = 'Adminler';

    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        return AdminForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmins::route('/'),
            'create' => CreateAdmin::route('/olustur'),
            'edit' => EditAdmin::route('/{record}/duzenle'),
        ];
    }
}
