<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Para çekme talebi. Ödeme bilgisi talep anında kopyalanır ve şifreli saklanır.
 * Durum ve tutarlar yalnızca Withdrawals servisiyle değişir.
 */
#[Hidden(['payout_snapshot'])]
#[Fillable(['user_id', 'amount_usd', 'payout_snapshot', 'payout_currency', 'estimated_fee_usd', 'tax_form_id', 'status', 'reject_reason', 'fee_usd', 'net_usd', 'paid_amount', 'wise_reference', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'paid_by', 'paid_at'])]
class Withdrawal extends Model
{
    use HasPublicUlid;

    protected function casts(): array
    {
        return [
            'status' => WithdrawalStatus::class,
            'payout_snapshot' => 'encrypted:array',
            'amount_usd' => 'decimal:2',
            'estimated_fee_usd' => 'decimal:2',
            'fee_usd' => 'decimal:2',
            'net_usd' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<TaxForm, $this>
     */
    public function taxForm(): BelongsTo
    {
        return $this->belongsTo(TaxForm::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [WithdrawalStatus::Pending, WithdrawalStatus::Approved], true);
    }
}
