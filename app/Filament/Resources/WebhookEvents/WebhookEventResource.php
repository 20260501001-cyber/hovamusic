<?php

namespace App\Filament\Resources\WebhookEvents;

use App\Domain\Billing\PolarWebhookProcessor;
use App\Filament\Resources\WebhookEvents\Pages\ListWebhookEvents;
use App\Models\WebhookEvent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\CodeEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Throwable;
use UnitEnum;

/**
 * Gelen webhook olayları: idempotency kaydı ve hata ayıklama. İşlenemeyen olay
 * yeniden işlenebilir; işlenmiş olay ikinci kez işlenmez.
 */
class WebhookEventResource extends Resource
{
    protected static ?string $model = WebhookEvent::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-webhook';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $modelLabel = 'Webhook olayı';

    protected static ?string $pluralModelLabel = 'Webhook olayları';

    protected static ?string $slug = 'webhook-olaylari';

    protected static ?int $navigationSort = 95;

    public static function table(Table $table): Table
    {
        $tz = config('hova.display_timezone');

        return $table
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('received_at')->label('Alındı')->dateTime('d.m.Y H:i:s')->timezone($tz)->sortable(),
                TextColumn::make('provider')->label('Kaynak'),
                TextColumn::make('type')->label('Olay')->searchable(),
                TextColumn::make('event_id')->label('Olay kimliği')->fontFamily('mono')->limit(18)->copyable(),
                TextColumn::make('processed_at')->label('İşlendi')->dateTime('d.m.Y H:i:s')->timezone($tz)->placeholder('İşlenmedi'),
                TextColumn::make('attempts')->label('Deneme'),
                TextColumn::make('error')->label('Hata')->limit(60)->placeholder('—')->wrap(),
            ])
            ->filters([
                TernaryFilter::make('processed')->label('İşlendi')
                    ->nullable()->attribute('processed_at'),
                TernaryFilter::make('has_error')->label('Hatalı')
                    ->queries(true: fn ($q) => $q->whereNotNull('error'), false: fn ($q) => $q->whereNull('error')),
            ])
            ->recordActions([
                Action::make('payload')
                    ->label('Veri')
                    ->icon('lucide-braces')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->schema([
                        CodeEntry::make('payload')->hiddenLabel()
                            ->state(fn (WebhookEvent $record): string => (string) json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                    ]),
                Action::make('reprocess')
                    ->label('Yeniden işle')
                    ->icon('lucide-rotate-cw')
                    ->visible(fn (WebhookEvent $record): bool => $record->processed_at === null)
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->action(function (WebhookEvent $record): void {
                        try {
                            $result = app(PolarWebhookProcessor::class)->handle($record->event_id, $record->payload);
                            Notification::make()->success()->title('Olay işlendi: '.$result)->send();
                        } catch (Throwable $e) {
                            report($e);
                            Notification::make()->danger()->title('İşlenemedi')->body(mb_substr($e->getMessage(), 0, 300))->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhookEvents::route('/'),
        ];
    }
}
