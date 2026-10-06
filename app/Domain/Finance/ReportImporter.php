<?php

namespace App\Domain\Finance;

use App\Enums\ReportImportStatus;
use App\Jobs\ProcessReportImport;
use App\Models\Admin;
use App\Models\ReportImport;
use App\Models\ReportLine;
use App\Models\ReportMapping;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Rapor yükleme ve okuma. Aynı dosya (SHA-256) geri alınmadıkça ikinci kez yüklenemez; dosya okunup
 * satırlar ham haliyle saklandıktan sonra hesaplama (eşleştirme, pay, kur) yapılır
 * ve rapor önizleme durumuna geçer. Bu aşamada bakiyeye hiçbir şey yazılmaz.
 */
class ReportImporter
{
    public const DISK = 'private';

    private const CHUNK = 500;

    public function __construct(private readonly ReportCalculator $calculator) {}

    /**
     * @throws ReportUnreadable
     */
    public function upload(string $path, string $originalName, ReportMapping $mapping, ?string $currency, Admin $admin): ReportImport
    {
        $this->assertReadableFile($path, $originalName);
        $hash = hash_file('sha256', $path);

        // Geri alınmış rapor (ör. yanlış eşleştirmeyle işlenmiş) düzeltilip yeniden yüklenebilir.
        $existing = ReportImport::query()->where('file_sha256', $hash)->where('status', '!=', ReportImportStatus::Reversed)->first();

        if ($existing !== null) {
            throw new ReportUnreadable(__('finance.import.errors.duplicate_file', ['date' => $existing->created_at->format('d.m.Y')]));
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'csv';
        $ulid = Str::lower((string) Str::ulid());
        $stored = "reports/{$ulid}.{$extension}";
        Storage::disk(self::DISK)->putFileAs('reports', new File($path), "{$ulid}.{$extension}");

        $import = new ReportImport([
            'original_name' => mb_substr($originalName, 0, 255),
            'file_path' => $stored,
            'file_sha256' => $hash,
            'report_mapping_id' => $mapping->id,
            'currency' => $currency !== null && $currency !== '' ? strtoupper($currency) : null,
            'status' => ReportImportStatus::Uploaded,
        ]);
        $import->forceFill(['ulid' => $ulid, 'uploaded_by' => $admin->id])->save();

        ProcessReportImport::dispatch($import->id)->onQueue('imports');

        return $import;
    }

    /**
     * Uzantıya güvenilmez: XLSX bir ZIP arşividir ("PK" imzası), CSV ise ikili veri
     * (NUL bayt) içermeyen metindir.
     *
     * @throws ReportUnreadable
     */
    private function assertReadableFile(string $path, string $originalName): void
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $handle = fopen($path, 'rb');
        $head = $handle ? (string) fread($handle, 8192) : '';

        if ($handle) {
            fclose($handle);
        }

        $valid = match ($extension) {
            'xlsx' => str_starts_with($head, "PK\x03\x04"),
            'csv', 'txt' => $head !== '' && ! str_contains($head, "\0"),
            default => false,
        };

        if (! $valid) {
            throw new ReportUnreadable(__('finance.import.errors.unreadable', ['message' => $originalName]));
        }
    }

    /**
     * Kuyrukta çalışır: dosyayı okur, satırları kaydeder, hesaplamayı yapar.
     */
    public function process(ReportImport $import): void
    {
        $import->forceFill(['status' => ReportImportStatus::Processing, 'error' => null])->save();
        $mapping = $import->mapping ?? ReportMapping::query()->where('is_default', true)->firstOrFail();
        $parser = new ReportValueParser($mapping->decimal_separator);
        $path = Storage::disk(self::DISK)->path($import->file_path);
        $extension = pathinfo($import->file_path, PATHINFO_EXTENSION);
        $fallbackCurrency = $import->currency ?? $mapping->default_currency;

        try {
            $import->lines()->delete();
            $batch = [];
            $count = 0;

            foreach (app(ReportReader::class)->rows($path, $extension, $mapping) as $row) {
                $values = $row['values'];
                $amount = $parser->amount($values['net_amount'] ?? null);
                $month = $parser->month($values['sales_month'] ?? null);
                $currency = strtoupper((string) ($parser->text($values['currency'] ?? null, 3) ?? $fallbackCurrency ?? ''));

                $batch[] = [
                    'report_import_id' => $import->id,
                    'row_number' => $row['row'],
                    'sales_month' => $month?->toDateString(),
                    'platform' => $parser->text($values['platform'] ?? null, 120),
                    'country' => $parser->text($values['country'] ?? null, 64),
                    'isrc' => $parser->isrc($values['isrc'] ?? null),
                    'upc' => $parser->upc($values['upc'] ?? null),
                    'artist_name' => $parser->text($values['artist_name'] ?? null),
                    'release_title' => $parser->text($values['release_title'] ?? null),
                    'track_title' => $parser->text($values['track_title'] ?? null),
                    'sale_type' => $parser->text($values['sale_type'] ?? null, 64),
                    'quantity' => $parser->quantity($values['quantity'] ?? null),
                    'net_amount' => (string) Money::line($amount ?? 0),
                    'currency' => $currency !== '' ? $currency : null,
                    'raw' => json_encode($row['raw'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                    'match_status' => 'unmatched',
                ];
                $count++;

                if (count($batch) >= self::CHUNK) {
                    ReportLine::query()->insert($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                ReportLine::query()->insert($batch);
            }

            if ($count === 0) {
                throw new ReportUnreadable(__('finance.import.errors.empty'));
            }

            $import->forceFill(['row_count' => $count])->save();
            $this->calculator->calculate($import);
        } catch (ReportUnreadable $e) {
            $import->forceFill(['status' => ReportImportStatus::Failed, 'error' => $e->getMessage()])->save();
        }
    }

    /**
     * Önizlemedeki ya da hatalı raporu siler; onaylanmış rapor silinmez, geri alınır.
     */
    public function discard(ReportImport $import): void
    {
        if (! in_array($import->status, [ReportImportStatus::Preview, ReportImportStatus::Failed, ReportImportStatus::Uploaded], true)) {
            throw new ReportUnreadable(__('finance.import.errors.not_discardable'));
        }

        DB::transaction(function () use ($import): void {
            $import->lines()->delete();
            $import->delete();
        });

        Storage::disk(self::DISK)->delete($import->file_path);
    }
}
