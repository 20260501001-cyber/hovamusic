<?php

namespace App\Filament\Resources\Admins\Schemas;

use App\Enums\AdminRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Ad soyad')
                ->required()
                ->maxLength(120),
            TextInput::make('email')
                ->label('E-posta')
                ->email()
                ->required()
                ->maxLength(254)
                ->unique(ignoreRecord: true),
            TextInput::make('password')
                ->label('Şifre')
                ->password()
                ->revealable()
                ->rule(Password::default())
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Düzenlerken boş bırakırsan şifre değişmez.'),
            Select::make('roles')
                ->label('Roller')
                ->relationship('roles', 'name', fn (Builder $query) => $query->where('guard_name', 'admin'))
                ->getOptionLabelFromRecordUsing(fn (Role $record): string => AdminRole::tryFrom($record->name)?->label() ?? $record->name)
                ->multiple()
                ->preload()
                ->required(),
            Toggle::make('is_active')
                ->label('Hesap aktif')
                ->default(true),
        ]);
    }
}
