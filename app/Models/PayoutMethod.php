<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wise ile ödeme bilgisi. IBAN, hesap ve yönlendirme numarası şifreli; ekranda
 * yalnızca son dört hane gösterilir.
 */
#[Fillable(['account_holder', 'iban', 'account_number', 'routing_number', 'swift_bic', 'bank_name', 'bank_country', 'currency', 'last4'])]
#[Hidden(['iban', 'account_number', 'routing_number'])]
class PayoutMethod extends Model
{
    /**
     * Wise ile alınabilen başlıca para birimleri.
     *
     * @var list<string>
     */
    public const CURRENCIES = [
        'USD', 'EUR', 'GBP', 'TRY', 'AED', 'AUD', 'BGN', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'HKD', 'HUF', 'IDR',
        'ILS', 'INR', 'JPY', 'KRW', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RON', 'SEK', 'SGD', 'THB', 'UAH', 'ZAR',
    ];

    protected function casts(): array
    {
        return [
            'iban' => 'encrypted',
            'account_number' => 'encrypted',
            'routing_number' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function maskedAccount(): string
    {
        return ($this->iban ? 'IBAN' : __('finance.payout.account')).' •••• '.$this->last4;
    }

    /**
     * Para çekme talebine kopyalanan ödeme bilgisi; sonraki değişiklikler talebi etkilemez.
     *
     * @return array<string, string|null>
     */
    public function snapshot(): array
    {
        return [
            'account_holder' => $this->account_holder,
            'iban' => $this->iban,
            'account_number' => $this->account_number,
            'routing_number' => $this->routing_number,
            'swift_bic' => $this->swift_bic,
            'bank_name' => $this->bank_name,
            'bank_country' => $this->bank_country,
            'currency' => $this->currency,
            'last4' => $this->last4,
        ];
    }
}
