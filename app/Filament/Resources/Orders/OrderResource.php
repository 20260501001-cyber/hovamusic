<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Users\UserResource;
use App\Models\Order;
use App\Support\Format;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Polar'dan gelen siparişler (ilk ödeme, yenileme, iade). Kayıtlar yalnızca
 * webhook'la oluşur; burada değiştirilmez.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-receipt';

    protected static string|UnitEnum|null $navigationGroup = 'Satış';

    protected static ?string $modelLabel = 'Ödeme';

    protected static ?string $pluralModelLabel = 'Siparişler ve ödemeler';

    protected static ?string $slug = 'siparisler';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        $tz = config('hova.display_timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'plan']))
            ->defaultSort('ordered_at', 'desc')
            ->columns([
                TextColumn::make('ordered_at')->label('Tarih')->dateTime('d.m.Y H:i')->timezone($tz)->sortable(),
                TextColumn::make('user.email')->label('Kullanıcı')->searchable()
                    ->url(fn (Order $record): ?string => $record->user && UserResource::canView($record->user) ? UserResource::getUrl('view', ['record' => $record->user]) : null),
                TextColumn::make('product_name')->label('Ürün')->placeholder('—')
                    ->description(fn (Order $record): ?string => $record->billing_reason ? __('plans.billing_reasons.'.$record->billing_reason) : null),
                TextColumn::make('subtotal')->label('Ara toplam')->formatStateUsing(fn (Order $record): string => Format::money((string) $record->subtotal, $record->currency))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax')->label('Vergi')->formatStateUsing(fn (Order $record): string => Format::money((string) $record->tax, $record->currency))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')->label('Toplam')->formatStateUsing(fn (Order $record): string => Format::money((string) $record->total, $record->currency))->sortable(),
                TextColumn::make('refunded')->label('İade')->formatStateUsing(fn (Order $record): string => Format::money((string) $record->refunded, $record->currency))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Paid => 'success',
                        OrderStatus::Pending => 'warning',
                        OrderStatus::Refunded, OrderStatus::PartiallyRefunded => 'gray',
                    }),
                TextColumn::make('invoice_number')->label('Fatura no')->placeholder('—')->copyable()->toggleable(),
                TextColumn::make('provider_id')->label('Polar sipariş kimliği')->fontFamily('mono')->copyable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $s): array => [$s->value => $s->label()])->all()),
                Filter::make('ordered')
                    ->schema([
                        DatePicker::make('from')->label('Başlangıç')->native(false)->displayFormat('d.m.Y'),
                        DatePicker::make('until')->label('Bitiş')->native(false)->displayFormat('d.m.Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->where('ordered_at', '>=', Carbon::parse($date, config('hova.display_timezone'))->startOfDay()->utc()))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->where('ordered_at', '<', Carbon::parse($date, config('hova.display_timezone'))->addDay()->startOfDay()->utc()))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }
}
