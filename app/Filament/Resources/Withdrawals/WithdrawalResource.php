<?php

namespace App\Filament\Resources\Withdrawals;

use App\Domain\Finance\Ledger;
use App\Enums\WithdrawalStatus;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Withdrawals\Pages\ListWithdrawals;
use App\Filament\Resources\Withdrawals\Pages\ViewWithdrawal;
use App\Models\Withdrawal;
use App\Support\Admin\AdminUrls;
use App\Support\Format;
use App\Support\Locale\Countries;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Para çekme talepleri: onay, ret (sebep zorunlu) ve Wise ödemesi sonrası "ödendi".
 */
class WithdrawalResource extends Resource
{
    protected static ?string $model = Withdrawal::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-banknote';

    protected static string|UnitEnum|null $navigationGroup = 'Finans';

    protected static ?string $modelLabel = 'Para çekme talebi';

    protected static ?string $pluralModelLabel = 'Para çekme talepleri';

    protected static ?string $slug = 'para-cekme';

    protected static ?int $navigationSort = 20;

    public static function getNavigationBadge(): ?string
    {
        $open = Withdrawal::query()->whereIn('status', [WithdrawalStatus::Pending, WithdrawalStatus::Approved])->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        $tz = config('hova.display_timezone');
        $snapshot = fn (string $key) => fn (Withdrawal $record): ?string => $record->payout_snapshot[$key] ?? null;

        return $schema->columns(2)->components([
            Section::make('Talep')
                ->columns(2)
                ->schema([
                    TextEntry::make('user.email')->label('Kullanıcı')->copyable()
                        ->url(fn (Withdrawal $record): ?string => $record->user && ! $record->user->trashed() && UserResource::canView($record->user) ? UserResource::getUrl('view', ['record' => $record->user]) : null),
                    TextEntry::make('status')->label('Durum')->badge()
                        ->formatStateUsing(fn (WithdrawalStatus $state): string => $state->label())
                        ->color(fn (WithdrawalStatus $state): string => self::statusColor($state)),
                    TextEntry::make('amount_usd')->label('Tutar')->formatStateUsing(fn (Withdrawal $record): string => Format::money((string) $record->amount_usd)),
                    TextEntry::make('estimated_fee_usd')->label('Tahmini Wise ücreti')->formatStateUsing(fn (Withdrawal $record): string => Format::money((string) $record->estimated_fee_usd)),
                    TextEntry::make('created_at')->label('Talep tarihi')->dateTime('d.m.Y H:i')->timezone($tz),
                    TextEntry::make('tax_form')->label('Vergi formu')
                        ->state(fn (Withdrawal $record): ?string => $record->taxForm ? $record->taxForm->form_type->label().' · '.Format::shortDate($record->taxForm->signed_at) : null)
                        ->url(fn (Withdrawal $record): ?string => $record->taxForm?->pdf_path ? AdminUrls::taxForm($record->taxForm) : null, shouldOpenInNewTab: true)
                        ->placeholder('—'),
                    TextEntry::make('balances')->label('Kullanıcının güncel bakiyesi')->columnSpanFull()
                        ->state(function (Withdrawal $record): string {
                            $b = app(Ledger::class)->balances($record->user);

                            return sprintf('Çekilebilir %s · bloke %s · rezerve %s', Format::money($b->available), Format::money($b->blocked), Format::money($b->reserved));
                        }),
                ]),
            Section::make('Ödeme bilgisi (talep anındaki)')
                ->columns(2)
                ->schema([
                    TextEntry::make('holder')->label('Hesap sahibi')->state($snapshot('account_holder'))->copyable(),
                    TextEntry::make('iban')->label('IBAN')->state($snapshot('iban'))->fontFamily('mono')->copyable()->placeholder('—'),
                    TextEntry::make('account_number')->label('Hesap numarası')->state($snapshot('account_number'))->fontFamily('mono')->copyable()->placeholder('—'),
                    TextEntry::make('routing_number')->label('Yönlendirme numarası')->state($snapshot('routing_number'))->fontFamily('mono')->copyable()->placeholder('—'),
                    TextEntry::make('swift_bic')->label('SWIFT/BIC')->state($snapshot('swift_bic'))->fontFamily('mono')->copyable()->placeholder('—'),
                    TextEntry::make('bank_name')->label('Banka')->state($snapshot('bank_name'))->placeholder('—'),
                    TextEntry::make('bank_country')->label('Banka ülkesi')
                        ->state(fn (Withdrawal $record): ?string => ($code = $record->payout_snapshot['bank_country'] ?? null) ? Countries::name($code).' ('.$code.')' : null),
                    TextEntry::make('payout_currency')->label('Para birimi'),
                ]),
            Section::make('Sonuç')
                ->columns(3)
                ->columnSpanFull()
                ->visible(fn (Withdrawal $record): bool => $record->status !== WithdrawalStatus::Pending)
                ->schema([
                    TextEntry::make('approved_at')->label('Onay')->dateTime('d.m.Y H:i')->timezone($tz)->placeholder('—'),
                    TextEntry::make('rejected_at')->label('Ret')->dateTime('d.m.Y H:i')->timezone($tz)->placeholder('—')
                        ->helperText(fn (Withdrawal $record): ?string => $record->reject_reason),
                    TextEntry::make('paid_at')->label('Ödeme')->dateTime('d.m.Y H:i')->timezone($tz)->placeholder('—'),
                    TextEntry::make('fee_usd')->label('Gerçek Wise ücreti')->formatStateUsing(fn (Withdrawal $record): string => Format::money((string) $record->fee_usd))->placeholder('—'),
                    TextEntry::make('net_usd')->label('Net (USD)')->formatStateUsing(fn (Withdrawal $record): string => Format::money((string) $record->net_usd))->placeholder('—'),
                    TextEntry::make('paid_amount')->label('Gönderilen')->formatStateUsing(fn (Withdrawal $record): string => Format::money((string) $record->paid_amount, $record->payout_currency))->placeholder('—'),
                    TextEntry::make('wise_reference')->label('Wise referansı')->copyable()->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user:id,email,deleted_at'))
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('created_at')->label('Talep')->dateTime('d.m.Y H:i')->timezone(config('hova.display_timezone'))->sortable(),
                TextColumn::make('user.email')->label('Kullanıcı')->searchable(),
                TextColumn::make('amount_usd')->label('Tutar')->alignEnd()->formatStateUsing(fn (Withdrawal $record): string => Format::money((string) $record->amount_usd))->sortable(),
                TextColumn::make('estimated_fee_usd')->label('Tahmini ücret')->alignEnd()->formatStateUsing(fn (Withdrawal $record): string => Format::money((string) $record->estimated_fee_usd)),
                TextColumn::make('payout_currency')->label('Birim'),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (WithdrawalStatus $state): string => $state->label())
                    ->color(fn (WithdrawalStatus $state): string => self::statusColor($state)),
                TextColumn::make('paid_at')->label('Ödendi')->dateTime('d.m.Y')->timezone(config('hova.display_timezone'))->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')
                    ->multiple()
                    ->default([WithdrawalStatus::Pending->value, WithdrawalStatus::Approved->value])
                    ->options(collect(WithdrawalStatus::cases())->mapWithKeys(fn (WithdrawalStatus $s): array => [$s->value => $s->label()])->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWithdrawals::route('/'),
            'view' => ViewWithdrawal::route('/{record}'),
        ];
    }

    public static function statusColor(WithdrawalStatus $status): string
    {
        return match ($status) {
            WithdrawalStatus::Pending => 'warning',
            WithdrawalStatus::Approved => 'info',
            WithdrawalStatus::Rejected => 'danger',
            WithdrawalStatus::Paid => 'success',
        };
    }
}
