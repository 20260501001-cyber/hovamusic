<?php

namespace App\Domain\Finance;

use App\Enums\ReportImportStatus;
use App\Jobs\RecalculateReportImport;
use App\Models\Admin;
use App\Models\MatchRule;
use App\Models\Release;
use App\Models\ReportLine;
use App\Models\Track;

/**
 * Admin'in eşleşmeyen rapor satırını bir yayına ya da parçaya bağlaması. Kural
 * (ISRC, UPC ya da "sanatçı | parça adı" anahtarı) saklanır ve sonraki raporlarda
 * da uygulanır; rapor yeniden hesaplanır.
 */
class ManualMatches
{
    public const KEY_TYPES = ['isrc', 'upc', 'title'];

    /**
     * @return array<string, string> anahtar türü => anahtar
     */
    public function keysFor(ReportLine $line): array
    {
        return array_filter([
            'isrc' => $line->isrc,
            'upc' => $line->upc,
            'title' => ReportCalculator::titleKey($line->artist_name, $line->track_title),
        ], fn (?string $key): bool => filled($key));
    }

    /**
     * @throws ReportUnreadable
     */
    public function match(ReportLine $line, string $keyType, Release $release, ?Track $track, Admin $admin): MatchRule
    {
        $import = $line->import;

        if ($import === null || $import->status !== ReportImportStatus::Preview) {
            throw new ReportUnreadable(__('finance.import.errors.not_matchable'));
        }

        $key = $this->keysFor($line)[$keyType] ?? throw new ReportUnreadable(__('finance.import.errors.not_matchable'));

        if ($track !== null && (int) $track->release_id !== (int) $release->id) {
            $track = null;
        }

        $rule = MatchRule::query()->updateOrCreate(
            ['key_type' => $keyType, 'key' => $key],
            ['release_id' => $release->id, 'track_id' => $track?->id, 'created_by' => $admin->id],
        );

        RecalculateReportImport::dispatch($import->id)->onQueue('imports');

        return $rule;
    }
}
