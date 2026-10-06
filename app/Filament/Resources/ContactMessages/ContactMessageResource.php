<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\Releases\Actions\ReleaseActions;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * İletişim formundan gelen mesajlar. Yanıt e-postayla verilir; burada yalnızca
 * "yanıtlandı" işareti tutulur.
 */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-mail';

    protected static string|UnitEnum|null $navigationGroup = 'Kullanıcılar';

    protected static ?string $modelLabel = 'İletişim mesajı';

    protected static ?string $pluralModelLabel = 'İletişim mesajları';

    protected static ?string $slug = 'iletisim-mesajlari';

    protected static ?int $navigationSort = 60;

    public static function getNavigationBadge(): ?string
    {
        $open = ContactMessage::query()->whereNull('handled_at')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('name')->label('Ad'),
            TextEntry::make('email')->label('E-posta')->copyable()->url(fn (ContactMessage $record): string => 'mailto:'.$record->email),
            TextEntry::make('topic')->label('Konu')->formatStateUsing(fn (string $state): string => __('site.contact.topics.'.$state)),
            TextEntry::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone')),
            TextEntry::make('message')->label('Mesaj')->columnSpanFull()->prose(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone'))->sortable(),
                TextColumn::make('name')->label('Ad')->searchable()->description(fn (ContactMessage $record): string => $record->email),
                TextColumn::make('topic')->label('Konu')->formatStateUsing(fn (string $state): string => __('site.contact.topics.'.$state)),
                TextColumn::make('message')->label('Mesaj')->limit(80)->wrap(),
                TextColumn::make('handled_at')->label('Yanıtlandı')->since()->placeholder('Bekliyor'),
            ])
            ->filters([
                TernaryFilter::make('handled_at')->label('Yanıtlandı')->nullable()->default(false),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('handled')->label('Yanıtlandı')->icon('lucide-check')->color('success')
                    ->visible(fn (ContactMessage $record): bool => $record->handled_at === null)
                    ->authorize('update')
                    ->action(fn (ContactMessage $record) => $record->forceFill(['handled_at' => now(), 'handled_by' => ReleaseActions::admin()->id])->save()),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListContactMessages::route('/')];
    }
}
