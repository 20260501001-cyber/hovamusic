<?php

namespace App\Jobs;

use App\Domain\Finance\ReportImporter;
use App\Enums\ReportImportStatus;
use App\Models\ReportImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Raporu "imports" kuyruğunda okur ve hesaplar; büyük dosyalar web isteğini bekletmez.
 */
class ProcessReportImport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public readonly int $importId) {}

    public function uniqueId(): string
    {
        return (string) $this->importId;
    }

    public function handle(ReportImporter $importer): void
    {
        $import = ReportImport::query()->find($this->importId);

        if ($import === null || ! in_array($import->status, [ReportImportStatus::Uploaded, ReportImportStatus::Failed, ReportImportStatus::Preview], true)) {
            return;
        }

        $importer->process($import);
    }

    public function failed(Throwable $e): void
    {
        ReportImport::query()->whereKey($this->importId)->update([
            'status' => ReportImportStatus::Failed->value,
            'error' => mb_substr($e->getMessage(), 0, 1000),
        ]);
    }
}
