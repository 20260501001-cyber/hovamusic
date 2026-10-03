<?php

namespace App\Filament\Resources\AdminAllowedIps;

use App\Filament\Resources\AdminAllowedIps\Pages\ManageAdminAllowedIps;
use App\Models\AdminAllowedIp;
use BackedEnum;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AdminAllowedIpResource extends Resource
{
    protected static ?string $model = AdminAllowedIp::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-network';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $modelLabel = 'izinli IP';

    protected static ?string $pluralModelLabel = 'Admin IP kısıtlaması';

    protected static ?int $navigationSort = 91;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('cidr')
                ->label('IP veya CIDR')
                ->placeholder('203.0.113.10 veya 203.0.113.0/24')
                ->required()
                ->maxLength(64)
                ->unique(ignoreRecord: true)
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (! self::isValidRange((string) $value)) {
                        $fail('Geçerli bir IPv4/IPv6 adresi veya CIDR aralığı gir.');
                    }
                }),
            TextInput::make('label')
                ->label('Açıklama')
                ->placeholder('Ofis, ev, VPN')
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Liste boşken admin paneline her IP\'den erişilebilir. İlk kaydı eklemeden önce kendi IP adresini eklediğinden emin ol.')
            ->columns([
                TextColumn::make('cidr')->label('IP veya CIDR')->fontFamily('mono')->copyable(),
                TextColumn::make('label')->label('Açıklama'),
                TextColumn::make('creator.name')->label('Ekleyen'),
                TextColumn::make('created_at')->label('Eklendi')->dateTime('d.m.Y H:i'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = auth('admin')->id();

                        return $data;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAdminAllowedIps::route('/'),
        ];
    }

    public static function isValidRange(string $value): bool
    {
        [$ip, $mask] = array_pad(explode('/', trim($value), 2), 2, null);

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($mask === null) {
            return true;
        }

        $max = str_contains($ip, ':') ? 128 : 32;

        return ctype_digit($mask) && (int) $mask >= 0 && (int) $mask <= $max;
    }
}
