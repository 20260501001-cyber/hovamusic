<?php

namespace App\Jobs;

use App\Domain\Finance\ReportCalculator;
use App\Enums\ReportImportStatus;
use App\Models\ReportImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Önizlemedeki raporu kur girişi ya da elle eşleştirmeden sonra yeniden hesaplar.
 * Hesaplama sürerken rapor "işleniyor" durumunda kalır ve onaylanamaz.
 */
class RecalculateReportImport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public readonly int $importId) {}

    public function uniqueId(): string
    {
        return 'recalc-'.$this->importId;
    }

    public function handle(ReportCalculator $calculator): void
    {
        $updated = ReportImport::query()
            ->whereKey($this->importId)
            ->where('status', ReportImportStatus::Preview)
            ->update(['status' => ReportImportStatus::Processing->value]);

        if ($updated === 0) {
            return;
        }

        $calculator->calculate(ReportImport::query()->findOrFail($this->importId));
    }

    public function failed(Throwable $e): void
    {
        ReportImport::query()->whereKey($this->importId)->update([
            'status' => ReportImportStatus::Failed->value,
            'error' => mb_substr($e->getMessage(), 0, 1000),
        ]);
    }
}
