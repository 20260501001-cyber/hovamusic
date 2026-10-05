<?php

namespace App\Domain\Finance;

use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Enums\ReportImportStatus;
use App\Models\Admin;
use App\Models\LedgerEntry;
use App\Models\ReportImport;
use App\Models\ReportLine;
use App\Models\StreamStat;
use App\Models\User;
use App\Notifications\EarningsAdded;
use Illuminate\Support\Facades\DB;

/**
 * Önizlemedeki raporu onaylar: her kullanıcıya rapor başına tek defter kaydı yazılır.
 * Kullanıcının o an aktif planı varsa kayıt kullanılabilir kovaya, yoksa bloke kovaya
 * gider. Dinlenme ve gelir özeti (stream_stats_monthly) doldurulur.
 *
 * Geri alma: aynı tutarların ters kaydı yazılır, orijinal kayıtlar silinmez. Bloke
 * yazılmış kazanç sonradan serbest kaldıysa ters kayıt önce bloke kovadan, kalanı
 * kullanılabilir kovadan düşer.
 */
class ReportApproval
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly ReportCalculator $calculator,
    ) {}

    /**
     * @throws ReportUnreadable
     */
    public function approve(ReportImport $import, Admin $admin): ReportImport
    {
        $notify = [];

        DB::transaction(function () use ($import, $admin, &$notify): void {
            $import = ReportImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();

            if ($import->status !== ReportImportStatus::Preview) {
                throw new ReportUnreadable(__('finance.import.errors.not_preview'));
            }

            if (! empty($import->warnings['blocking'] ?? [])) {
                throw new ReportUnreadable(__('finance.import.errors.blocking'));
            }

            if ($this->calculator->periodConflicts($import, $import->periods ?? []) !== []) {
                throw new ReportUnreadable(__('finance.import.errors.period_taken'));
            }

            $totals = ReportLine::query()
                ->where('report_import_id', $import->id)
                ->where('match_status', 'matched')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->selectRaw('user_id, SUM(user_amount_usd) as total')
                ->pluck('total', 'user_id');

            foreach ($totals as $userId => $total) {
                $amount = Money::ledger((string) $total);

                if ($amount->isZero()) {
                    continue;
                }

                $user = User::withTrashed()->findOrFail($userId);
                $this->ledger->lock($user);
                $bucket = $user->activeSubscription() !== null ? LedgerBucket::Available : LedgerBucket::Blocked;

                $this->ledger->post($user, $bucket, LedgerEntryType::Earning, $amount, $import,
                    __('finance.ledger.earning', ['periods' => implode(', ', $import->periods ?? [])]), actor: $admin);

                $notify[$userId] = [$amount, $bucket];
            }

            StreamStat::query()->insertUsing(
                ['user_id', 'report_import_id', 'release_id', 'track_id', 'platform', 'country', 'month', 'quantity', 'revenue_usd'],
                ReportLine::query()
                    ->where('report_import_id', $import->id)
                    ->where('match_status', 'matched')
                    ->whereNotNull('user_id')
                    ->groupBy('user_id', 'release_id', 'track_id', 'platform', 'country', 'sales_month')
                    ->selectRaw('user_id, ? as report_import_id, release_id, track_id, platform, country, sales_month, SUM(quantity), SUM(user_amount_usd)', [$import->id]),
            );

            $import->forceFill([
                'status' => ReportImportStatus::Approved,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ])->save();
        });

        DB::afterCommit(function () use ($notify, $import): void {
            foreach ($notify as $userId => [$amount, $bucket]) {
                User::query()->find($userId)?->notify(new EarningsAdded($import, (string) $amount, $bucket));
            }
        });

        return $import->refresh();
    }

    /**
     * @throws ReportUnreadable
     */
    public function reverse(ReportImport $import, Admin $admin, string $reason): ReportImport
    {
        DB::transaction(function () use ($import, $admin, $reason): void {
            $import = ReportImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();

            if ($import->status !== ReportImportStatus::Approved) {
                throw new ReportUnreadable(__('finance.import.errors.not_approved'));
            }

            $entries = LedgerEntry::query()
                ->where('source_type', $import->getMorphClass())
                ->where('source_id', $import->id)
                ->where('type', LedgerEntryType::Earning)
                ->with('user')
                ->get();

            foreach ($entries as $entry) {
                $this->reverseEarning($entry, $import, $admin, $reason);
            }

            StreamStat::query()->where('report_import_id', $import->id)->delete();

            $import->forceFill([
                'status' => ReportImportStatus::Reversed,
                'reversed_by' => $admin->id,
                'reversed_at' => now(),
                'reverse_reason' => mb_substr($reason, 0, 500),
            ])->save();
        });

        return $import->refresh();
    }

    private function reverseEarning(LedgerEntry $entry, ReportImport $import, Admin $admin, string $reason): void
    {
        $user = $entry->user;
        $this->ledger->lock($user);
        $amount = Money::ledger((string) $entry->amount_usd);
        $description = __('finance.ledger.reversal', ['reason' => $reason]);

        if ($entry->bucket !== LedgerBucket::Blocked || $amount->isNegativeOrZero()) {
            $this->ledger->post($user, $entry->bucket, LedgerEntryType::EarningReversal, $amount->negated(), $import, $description, $entry, actor: $admin);

            return;
        }

        // Bloke yazılan kazancın bir kısmı plan yenilenince serbest kalmış olabilir.
        $blocked = $this->ledger->balance($user, LedgerBucket::Blocked);
        $fromBlocked = $blocked->isGreaterThan($amount) ? $amount : ($blocked->isPositive() ? $blocked : Money::ledger('0'));
        $fromAvailable = $amount->minus($fromBlocked);

        if ($fromBlocked->isPositive()) {
            $this->ledger->post($user, LedgerBucket::Blocked, LedgerEntryType::EarningReversal, $fromBlocked->negated(), $import, $description, $entry, actor: $admin);
        }

        if ($fromAvailable->isPositive()) {
            $this->ledger->post($user, LedgerBucket::Available, LedgerEntryType::EarningReversal, $fromAvailable->negated(), $import, $description, $entry, actor: $admin);
        }
    }
}
