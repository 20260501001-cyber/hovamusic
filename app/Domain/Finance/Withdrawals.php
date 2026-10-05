<?php

namespace App\Domain\Finance;

use App\Enums\EntityType;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Enums\WithdrawalStatus;
use App\Models\Admin;
use App\Models\TaxForm;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\WithdrawalStatusChanged;
use App\Support\Settings;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Para çekme. Talep tutarı kullanılabilir kovadan rezerve kovaya geçer. Ret tutarı
 * geri taşır. Ödeme sonrası admin gerçek Wise ücretini ve gönderilen tutarı girer;
 * deftere net ödeme ve ücret ayrı kayıt olarak düşer. Wise ücreti talep tutarından
 * düşülür.
 */
class Withdrawals
{
    public const MINIMUM_USD = '20';

    public function __construct(
        private readonly Ledger $ledger,
        private readonly Settings $settings,
    ) {}

    /**
     * Tahmini Wise ücreti: sabit + yüzde (admin ayarı).
     */
    public function estimatedFee(BigDecimal $amount): BigDecimal
    {
        $fixed = Money::of((string) $this->settings->get('wise_fee_fixed_usd'));
        $pct = Money::of((string) $this->settings->get('wise_fee_pct'));

        return $fixed->plus($amount->multipliedBy($pct)->dividedBy(100, 6, RoundingMode::HalfUp))->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * Hesap türüne uygun (bireysel W-8BEN, şirket W-8BEN-E), süresi dolmamış son form.
     */
    public function validTaxForm(User $user): ?TaxForm
    {
        $type = ($user->profile?->entity_type ?? EntityType::Individual)->taxForm();

        return $user->taxForms()
            ->where('status', 'valid')
            ->where('form_type', $type)
            ->latest('signed_at')
            ->get()
            ->first(fn (TaxForm $form): bool => $form->isValid());
    }

    /**
     * Para çekme ön koşulları: fatura bilgileri, ödeme bilgisi, geçerli vergi formu.
     *
     * @return array{profile: bool, payout: bool, tax_form: bool}
     */
    public function requirements(User $user): array
    {
        return [
            'profile' => $user->profile?->isComplete() ?? false,
            'payout' => $user->payoutMethod !== null,
            'tax_form' => $this->validTaxForm($user) !== null,
        ];
    }

    /**
     * @throws WithdrawalNotAllowed
     */
    public function request(User $user, string $amount): Withdrawal
    {
        $amount = Money::of($amount);

        if ($amount->getScale() > 2) {
            throw new WithdrawalNotAllowed(__('finance.withdrawals.errors.decimals'));
        }

        $amount = Money::payout($amount);

        if ($amount->isLessThan(self::MINIMUM_USD)) {
            throw new WithdrawalNotAllowed(__('finance.withdrawals.errors.minimum', ['amount' => self::MINIMUM_USD]));
        }

        if (! ($user->profile?->isComplete() ?? false)) {
            throw new WithdrawalNotAllowed(__('finance.withdrawals.errors.profile'));
        }

        $taxForm = $this->validTaxForm($user) ?? throw new WithdrawalNotAllowed(__('finance.withdrawals.errors.tax_form'));
        $method = $user->payoutMethod ?? throw new WithdrawalNotAllowed(__('finance.withdrawals.errors.payout_method'));
        $fee = $this->estimatedFee($amount);

        if ($fee->isGreaterThanOrEqualTo($amount)) {
            throw new WithdrawalNotAllowed(__('finance.withdrawals.errors.fee'));
        }

        $withdrawal = DB::transaction(function () use ($user, $amount, $taxForm, $method, $fee): Withdrawal {
            $this->ledger->lock($user);

            if ($user->withdrawals()->whereIn('status', [WithdrawalStatus::Pending, WithdrawalStatus::Approved])->exists()) {
                throw new WithdrawalNotAllowed(__('finance.withdrawals.errors.open_exists'));
            }

            if ($this->ledger->balance($user, LedgerBucket::Available)->isLessThan($amount)) {
                throw new WithdrawalNotAllowed(__('finance.withdrawals.errors.balance'));
            }

            $withdrawal = Withdrawal::query()->create([
                'user_id' => $user->id,
                'amount_usd' => (string) $amount,
                'payout_snapshot' => $method->snapshot(),
                'payout_currency' => $method->currency,
                'estimated_fee_usd' => (string) $fee,
                'tax_form_id' => $taxForm->id,
                'status' => WithdrawalStatus::Pending,
            ]);

            $this->ledger->transfer($user, LedgerBucket::Available, LedgerBucket::Reserved, LedgerEntryType::WithdrawalReserve, $amount, $withdrawal,
                __('finance.ledger.withdrawal_reserve'), $user);

            return $withdrawal;
        });

        DB::afterCommit(fn () => $user->notify(new WithdrawalStatusChanged($withdrawal)));

        return $withdrawal;
    }

    /**
     * @throws WithdrawalNotAllowed
     */
    public function approve(Withdrawal $withdrawal, Admin $admin): Withdrawal
    {
        return $this->change($withdrawal, [WithdrawalStatus::Pending], function (Withdrawal $locked) use ($admin): void {
            $locked->forceFill(['status' => WithdrawalStatus::Approved, 'approved_by' => $admin->id, 'approved_at' => now()])->save();
        });
    }

    /**
     * @throws WithdrawalNotAllowed
     */
    public function reject(Withdrawal $withdrawal, Admin $admin, string $reason): Withdrawal
    {
        if (trim($reason) === '') {
            throw new WithdrawalNotAllowed(__('finance.withdrawals.errors.reason'));
        }

        return $this->change($withdrawal, [WithdrawalStatus::Pending, WithdrawalStatus::Approved], function (Withdrawal $locked) use ($admin, $reason): void {
            $this->ledger->transfer($locked->user, LedgerBucket::Reserved, LedgerBucket::Available, LedgerEntryType::WithdrawalRelease,
                Money::payout((string) $locked->amount_usd), $locked, __('finance.ledger.withdrawal_release'), $admin);

            $locked->forceFill([
                'status' => WithdrawalStatus::Rejected,
                'reject_reason' => mb_substr(trim($reason), 0, 1000),
                'rejected_by' => $admin->id,
                'rejected_at' => now(),
            ])->save();
        });
    }

    /**
     * @param  string  $feeUsd  Wise'ın gerçek ücreti (USD)
     * @param  string  $paidAmount  Kullanıcıya gönderilen tutar (ödeme para biriminde)
     *
     * @throws WithdrawalNotAllowed
     */
    public function markPaid(Withdrawal $withdrawal, Admin $admin, string $feeUsd, string $paidAmount, ?string $reference): Withdrawal
    {
        $fee = Money::payout($feeUsd);
        $amount = Money::payout((string) $withdrawal->amount_usd);

        if ($fee->isNegative() || $fee->isGreaterThanOrEqualTo($amount)) {
            throw new WithdrawalNotAllowed(__('finance.withdrawals.errors.fee'));
        }

        if (Money::payout($paidAmount)->isNegativeOrZero()) {
            throw new WithdrawalNotAllowed(__('finance.withdrawals.errors.paid_amount'));
        }

        return $this->change($withdrawal, [WithdrawalStatus::Approved], function (Withdrawal $locked) use ($admin, $fee, $amount, $paidAmount, $reference): void {
            $net = $amount->minus($fee);
            $user = $locked->user;

            $this->ledger->post($user, LedgerBucket::Reserved, LedgerEntryType::WithdrawalPaid, $net->negated(), $locked,
                __('finance.ledger.withdrawal_paid'), actor: $admin);

            if ($fee->isPositive()) {
                $this->ledger->post($user, LedgerBucket::Reserved, LedgerEntryType::WithdrawalFee, $fee->negated(), $locked,
                    __('finance.ledger.withdrawal_fee'), actor: $admin);
            }

            $locked->forceFill([
                'status' => WithdrawalStatus::Paid,
                'fee_usd' => (string) $fee,
                'net_usd' => (string) $net,
                'paid_amount' => (string) Money::payout($paidAmount),
                'wise_reference' => $reference !== null ? mb_substr(trim($reference), 0, 120) : null,
                'paid_by' => $admin->id,
                'paid_at' => now(),
            ])->save();
        });
    }

    /**
     * @param  list<WithdrawalStatus>  $from
     *
     * @throws WithdrawalNotAllowed
     */
    private function change(Withdrawal $withdrawal, array $from, callable $apply): Withdrawal
    {
        $locked = DB::transaction(function () use ($withdrawal, $from, $apply): Withdrawal {
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->with('user')->firstOrFail();

            if (! in_array($locked->status, $from, true)) {
                throw new WithdrawalNotAllowed(__('finance.withdrawals.errors.status'));
            }

            $this->ledger->lock($locked->user);
            $apply($locked);

            return $locked;
        });

        DB::afterCommit(fn () => $locked->user?->notify(new WithdrawalStatusChanged($locked)));

        return $locked;
    }
}
