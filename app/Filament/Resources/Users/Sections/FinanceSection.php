<?php

namespace App\Filament\Resources\Users\Sections;

use App\Domain\Finance\Ledger;
use App\Domain\Finance\Withdrawals;
use App\Filament\Resources\LedgerEntries\LedgerEntryResource;
use App\Models\User;
use App\Support\Format;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

/**
 * Admin kullanıcı detayında bakiye, ödeme bilgisi ve vergi formu durumu.
 */
class FinanceSection
{
    public static function make(): Section
    {
        return Section::make('Bakiye')
            ->columns(2)
            ->schema([
                TextEntry::make('balance_available')->label('Çekilebilir')
                    ->state(fn (User $record): string => Format::money(app(Ledger::class)->balances($record)->available))
                    ->url(fn (User $record): string => LedgerEntryResource::getUrl('index', ['filters' => ['user' => ['value' => $record->id]]])),
                TextEntry::make('balance_blocked')->label('Bloke')
                    ->state(fn (User $record): string => Format::money(app(Ledger::class)->balances($record)->blocked)),
                TextEntry::make('balance_reserved')->label('Rezerve (bekleyen çekim)')
                    ->state(fn (User $record): string => Format::money(app(Ledger::class)->balances($record)->reserved)),
                TextEntry::make('payout_method')->label('Ödeme bilgisi')
                    ->state(fn (User $record): ?string => $record->payoutMethod
                        ? $record->payoutMethod->maskedAccount().' · '.$record->payoutMethod->currency.' · '.$record->payoutMethod->bank_country
                        : null)
                    ->placeholder('Girilmedi'),
                TextEntry::make('tax_form')->label('Vergi formu')
                    ->state(function (User $record): ?string {
                        $form = app(Withdrawals::class)->validTaxForm($record);

                        return $form ? $form->form_type->label().' · '.Format::shortDate($form->signed_at).' · '.Format::shortDate($form->expires_at).' tarihine kadar' : null;
                    })
                    ->placeholder('Geçerli form yok'),
                TextEntry::make('billing_profile')->label('Fatura bilgisi')
                    ->state(fn (User $record): string => $record->profile?->isComplete()
                        ? $record->profile->entity_type->label().' · '.($record->profile->company_name ?? $record->profile->legal_name).' · '.$record->profile->country
                        : 'Eksik'),
            ]);
    }
}
