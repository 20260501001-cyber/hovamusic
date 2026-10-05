<?php

namespace App\Domain\Finance;

use App\Enums\ReportImportStatus;
use App\Models\MatchRule;
use App\Models\Release;
use App\Models\ReportImport;
use App\Models\ReportLine;
use App\Models\Track;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Raporun satırlarını eşleştirir ve hesaplar; sonucu önizleme olarak saklar.
 *
 * Eşleştirme sırası: ISRC (parça), UPC (yayın), admin'in elle yaptığı eşleştirmeler
 * (ISRC, UPC ya da "sanatçı | parça adı" anahtarıyla). Kullanıcı payı = net gelir ×
 * USD kuru × satış ayının son günü geçerli planın gelir payı.
 */
class ReportCalculator
{
    private const CHUNK = 500;

    /**
     * @var array<string, array{track_id: int|null, release_id: int, user_id: int}|null>
     */
    private array $matches = [];

    public function __construct(
        private readonly ShareResolver $shares,
        private readonly FxRates $rates,
    ) {}

    public function calculate(ReportImport $import): ReportImport
    {
        $this->matches = [];
        $missingRates = [];
        $noPlan = [];
        $missingMonth = 0;

        ReportLine::query()->where('report_import_id', $import->id)->orderBy('id')->chunkById(self::CHUNK, function ($lines) use (&$missingRates, &$noPlan, &$missingMonth): void {
            DB::transaction(function () use ($lines, &$missingRates, &$noPlan, &$missingMonth): void {
                foreach ($lines as $line) {
                    /** @var ReportLine $line */
                    $match = $this->match($line);
                    $period = $line->sales_month?->format('Y-m');

                    if ($period === null) {
                        $missingMonth++;
                    }

                    $rate = $period !== null && $line->currency !== null ? $this->rates->toUsd($line->currency, $period) : null;

                    if ($rate === null && $period !== null) {
                        $missingRates[($line->currency ?? '?').' '.$period] = true;
                    }

                    $amountUsd = $rate !== null ? Money::line(Money::of((string) $line->net_amount)->multipliedBy($rate)) : null;
                    $share = null;
                    $userAmount = null;

                    if ($match !== null && $line->sales_month !== null) {
                        $share = $this->shares->shareFor($match['user_id'], Carbon::instance($line->sales_month));

                        if ($share === null) {
                            $noPlan[$match['user_id']] = true;
                        }

                        $userAmount = $amountUsd !== null
                            ? Money::line($amountUsd->multipliedBy(BigDecimal::of($share ?? '0'))->dividedBy(100, Money::LINE_SCALE, RoundingMode::HalfUp))
                            : null;
                    }

                    $line->forceFill([
                        'match_status' => $match !== null ? 'matched' : 'unmatched',
                        'match_source' => $match['source'] ?? null,
                        'track_id' => $match['track_id'] ?? null,
                        'release_id' => $match['release_id'] ?? null,
                        'user_id' => $match['user_id'] ?? null,
                        'share_pct' => $share,
                        'fx_rate' => $rate !== null ? (string) $rate->toScale(8, RoundingMode::HalfUp) : null,
                        'amount_usd' => $amountUsd !== null ? (string) $amountUsd : null,
                        'user_amount_usd' => $userAmount !== null ? (string) $userAmount : null,
                    ])->save();
                }
            });
        });

        return $this->summarize($import, array_keys($missingRates), array_keys($noPlan), $missingMonth);
    }

    /**
     * @param  list<string>  $missingRates
     * @param  list<int>  $usersWithoutPlan
     */
    private function summarize(ReportImport $import, array $missingRates, array $usersWithoutPlan, int $missingMonth): ReportImport
    {
        $lines = ReportLine::query()->where('report_import_id', $import->id);
        $periods = (clone $lines)->whereNotNull('sales_month')->distinct()->pluck('sales_month')
            ->map(fn ($month): string => Carbon::parse($month)->format('Y-m'))->unique()->sort()->values()->all();

        $byCurrency = (clone $lines)->groupBy('currency')->selectRaw('currency, SUM(net_amount) as total')->pluck('total', 'currency')
            ->map(fn ($total): string => (string) Money::of((string) $total)->toScale(2, RoundingMode::HalfUp))->all();

        $matched = (clone $lines)->where('match_status', 'matched');
        $unmatched = (clone $lines)->where('match_status', 'unmatched');

        $blocking = [];

        if ($missingRates !== []) {
            $blocking[] = __('finance.import.warnings.missing_rates', ['rates' => implode(', ', $missingRates)]);
        }

        if ($missingMonth > 0) {
            $blocking[] = __('finance.import.warnings.missing_month', ['count' => $missingMonth]);
        }

        $conflicts = $this->periodConflicts($import, $periods);

        if ($conflicts !== []) {
            $blocking[] = __('finance.import.warnings.period_conflict', ['periods' => implode(', ', $conflicts)]);
        }

        $notes = [];
        $unmatchedCount = (clone $unmatched)->count();

        if ($unmatchedCount > 0) {
            $notes[] = __('finance.import.warnings.unmatched', ['count' => $unmatchedCount]);
        }

        if ($usersWithoutPlan !== []) {
            $notes[] = __('finance.import.warnings.no_plan', ['count' => count($usersWithoutPlan)]);
        }

        $import->forceFill([
            'status' => ReportImportStatus::Preview,
            'periods' => $periods,
            'matched_count' => (clone $matched)->count(),
            'unmatched_count' => $unmatchedCount,
            'totals' => [
                'net_by_currency' => $byCurrency,
                'amount_usd' => $this->sum(clone $lines, 'amount_usd'),
                'matched_usd' => $this->sum(clone $matched, 'amount_usd'),
                'unmatched_usd' => $this->sum(clone $unmatched, 'amount_usd'),
                'users_usd' => $this->sum(clone $matched, 'user_amount_usd'),
                'users' => (clone $matched)->distinct()->count('user_id'),
                'quantity' => (int) (clone $lines)->sum('quantity'),
            ],
            'warnings' => ['blocking' => $blocking, 'notes' => $notes],
        ])->save();

        return $import;
    }

    /**
     * Aynı dönem başka bir raporla işlendiyse (önizlemede ya da onaylı) çakışma vardır.
     *
     * @param  list<string>  $periods
     * @return list<string>
     */
    public function periodConflicts(ReportImport $import, array $periods): array
    {
        if ($periods === []) {
            return [];
        }

        return ReportImport::query()
            ->whereKeyNot($import->id)
            ->whereIn('status', [ReportImportStatus::Preview->value, ReportImportStatus::Approved->value])
            ->get(['periods'])
            ->flatMap(fn (ReportImport $other): array => array_values(array_intersect($periods, $other->periods ?? [])))
            ->unique()->sort()->values()->all();
    }

    private function sum(mixed $query, string $column): string
    {
        return (string) Money::of((string) ($query->sum($column) ?? '0'))->toScale(6, RoundingMode::HalfUp);
    }

    /**
     * @return array{track_id: int|null, release_id: int, user_id: int, source: string}|null
     */
    private function match(ReportLine $line): ?array
    {
        $candidates = array_filter([
            $line->isrc ? ['isrc', $line->isrc] : null,
            $line->upc ? ['upc', $line->upc] : null,
            ['title', self::titleKey($line->artist_name, $line->track_title)],
        ]);

        foreach ($candidates as [$type, $key]) {
            if ($key === null) {
                continue;
            }

            $cacheKey = $type.':'.$key;

            if (! array_key_exists($cacheKey, $this->matches)) {
                $this->matches[$cacheKey] = $this->lookup($type, $key);
            }

            if ($this->matches[$cacheKey] !== null) {
                return $this->matches[$cacheKey];
            }
        }

        return null;
    }

    /**
     * @return array{track_id: int|null, release_id: int, user_id: int, source: string}|null
     */
    private function lookup(string $type, string $key): ?array
    {
        if ($type === 'isrc') {
            $track = Track::query()->with('release:id,user_id')->where('isrc', $key)->orderByDesc('id')->first();

            if ($track?->release !== null) {
                return ['track_id' => $track->id, 'release_id' => $track->release_id, 'user_id' => $track->release->user_id, 'source' => 'isrc'];
            }
        }

        if ($type === 'upc') {
            $release = Release::withTrashed()->where('upc', $key)->orderByDesc('id')->first(['id', 'user_id']);

            if ($release !== null) {
                return ['track_id' => null, 'release_id' => $release->id, 'user_id' => $release->user_id, 'source' => 'upc'];
            }
        }

        $rule = MatchRule::query()->where('key_type', $type)->where('key', $key)->first();

        if ($rule !== null) {
            $release = $rule->track_id !== null
                ? Track::query()->find($rule->track_id)?->release()->withTrashed()->first(['id', 'user_id'])
                : Release::withTrashed()->find($rule->release_id, ['id', 'user_id']);

            if ($release !== null) {
                return ['track_id' => $rule->track_id, 'release_id' => $release->id, 'user_id' => $release->user_id, 'source' => 'rule'];
            }
        }

        return null;
    }

    public static function titleKey(?string $artist, ?string $title): ?string
    {
        if (blank($title)) {
            return null;
        }

        $normalize = fn (?string $value): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''));

        return mb_substr($normalize($artist).' | '.$normalize($title), 0, 255);
    }
}
