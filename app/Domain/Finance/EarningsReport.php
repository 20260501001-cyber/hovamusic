<?php

namespace App\Domain\Finance;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Release;
use App\Models\StreamStat;
use App\Models\Track;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kullanıcının kazanç ekranı ve CSV dökümü için onaylanmış raporlardan derlenen
 * özetler (stream_stats_monthly). Tutarlar kullanıcının payıdır (USD).
 */
class EarningsReport
{
    public const DEFAULT_MONTHS = 12;

    public const MAX_MONTHS = 36;

    /**
     * @var array<int, Track|null>
     */
    private array $tracks = [];

    /**
     * @var array<int, Release|null>
     */
    private array $releases = [];

    /**
     * Verisi olan aylar (YYYY-MM, eskiden yeniye).
     *
     * @return list<string>
     */
    public function months(User $user): array
    {
        return StreamStat::query()
            ->where('user_id', $user->id)
            ->distinct()
            ->orderBy('month')
            ->pluck('month')
            ->map(fn ($month): string => CarbonImmutable::parse($month)->format('Y-m'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Seçilen aralık; boşsa verisi olan son on iki ay.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(User $user, ?string $from, ?string $to): array
    {
        $months = $this->months($user);
        $latest = $months !== [] ? CarbonImmutable::createFromFormat('!Y-m', end($months)) : CarbonImmutable::now()->startOfMonth();

        $end = $to !== null ? CarbonImmutable::createFromFormat('!Y-m', $to) : $latest;
        $start = $from !== null ? CarbonImmutable::createFromFormat('!Y-m', $from) : $end->subMonths(self::DEFAULT_MONTHS - 1);

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        // Grafik ve tablo okunur kalsın diye aralık en fazla 36 ay.
        if ($start->diffInMonths($end) > self::MAX_MONTHS - 1) {
            $start = $end->subMonths(self::MAX_MONTHS - 1);
        }

        return [$start->startOfMonth(), $end->startOfMonth()];
    }

    /**
     * Aralıktaki her ay için dinlenme/satış adedi ve gelir; verisi olmayan aylar sıfır.
     *
     * @return list<array{month: CarbonImmutable, quantity: int, revenue: BigDecimal}>
     */
    public function monthly(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->scope($user, $from, $to)
            ->groupBy('month')
            ->selectRaw('month, SUM(quantity) as quantity, SUM(revenue_usd) as revenue')
            ->get()
            ->keyBy(fn ($row): string => CarbonImmutable::parse($row->month)->format('Y-m'));

        $result = [];

        for ($month = $from; $month->lessThanOrEqualTo($to); $month = $month->addMonth()) {
            $row = $rows->get($month->format('Y-m'));
            $result[] = [
                'month' => $month,
                'quantity' => (int) ($row->quantity ?? 0),
                'revenue' => Money::ledger((string) ($row->revenue ?? '0')),
            ];
        }

        return $result;
    }

    /**
     * Platform, ülke ya da parça kırılımı: en yüksek gelirli satırlar ve kalanların toplamı.
     *
     * @return array{rows: list<array{name: string, quantity: int, revenue: BigDecimal, share: string}>, others: array{count: int, quantity: int, revenue: BigDecimal, share: string}|null}
     */
    public function breakdown(User $user, CarbonImmutable $from, CarbonImmutable $to, string $dimension, int $limit = 10): array
    {
        $columns = match ($dimension) {
            'platform' => ['platform'],
            'country' => ['country'],
            'track' => ['track_id', 'release_id'],
        };

        $rows = $this->scope($user, $from, $to)
            ->groupBy($columns)
            ->select($columns)
            ->selectRaw('SUM(quantity) as quantity, SUM(revenue_usd) as revenue')
            ->orderByDesc('revenue')
            ->get();

        $total = $rows->reduce(fn (BigDecimal $carry, $row): BigDecimal => $carry->plus(Money::ledger((string) $row->revenue)), BigDecimal::zero());
        $names = $dimension === 'track' ? $this->trackNames($rows->take($limit)->all()) : [];

        $format = function ($row) use ($dimension, $names, $total): array {
            $revenue = Money::ledger((string) $row->revenue);

            return [
                'name' => match ($dimension) {
                    'track' => $names[$row->track_id.'-'.$row->release_id] ?? __('finance.earnings.breakdown.unknown'),
                    default => filled($row->{$dimension}) ? (string) $row->{$dimension} : __('finance.earnings.breakdown.unknown'),
                },
                'quantity' => (int) $row->quantity,
                'revenue' => $revenue,
                'share' => $this->share($revenue, $total),
            ];
        };

        $top = $rows->take($limit)->map($format)->values()->all();
        $rest = $rows->slice($limit);
        $others = null;

        if ($rest->isNotEmpty()) {
            $revenue = $rest->reduce(fn (BigDecimal $carry, $row): BigDecimal => $carry->plus(Money::ledger((string) $row->revenue)), BigDecimal::zero());
            $others = [
                'count' => $rest->count(),
                'quantity' => (int) $rest->sum(fn ($row): int => (int) $row->quantity),
                'revenue' => $revenue,
                'share' => $this->share($revenue, $total),
            ];
        }

        return ['rows' => $top, 'others' => $others];
    }

    /**
     * Tüm zamanların net kazancı (geri alınan raporlar düşülmüş).
     */
    public function lifetime(User $user): BigDecimal
    {
        return Money::ledger((string) LedgerEntry::query()
            ->where('user_id', $user->id)
            ->whereIn('type', [LedgerEntryType::Earning, LedgerEntryType::EarningReversal])
            ->sum('amount_usd'));
    }

    /**
     * CSV dökümü için satırlar (ay, platform, ülke, yayın, parça).
     *
     * @return iterable<array<int, string|int>>
     */
    public function csvRows(User $user, CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        $query = $this->scope($user, $from, $to)
            ->groupBy('month', 'platform', 'country', 'release_id', 'track_id')
            ->select(['month', 'platform', 'country', 'release_id', 'track_id'])
            ->selectRaw('SUM(quantity) as quantity, SUM(revenue_usd) as revenue')
            ->orderBy('month')
            ->orderBy('platform')
            ->orderBy('country')
            ->orderBy('release_id')
            ->orderBy('track_id');

        foreach ($query->cursor() as $row) {
            $track = $row->track_id ? $this->track((int) $row->track_id) : null;
            $release = $row->release_id ? $this->release((int) $row->release_id) : null;

            yield [
                CarbonImmutable::parse($row->month)->format('Y-m'),
                (string) $row->platform,
                (string) $row->country,
                (string) ($release->title ?? ''),
                (string) ($track->title ?? ''),
                (string) ($track->isrc ?? ''),
                (string) ($release->upc ?? ''),
                (int) $row->quantity,
                (string) Money::ledger((string) $row->revenue),
            ];
        }
    }

    /**
     * @return Builder<StreamStat>
     */
    private function scope(User $user, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return StreamStat::query()
            ->where('user_id', $user->id)
            ->whereBetween('month', [$from->toDateString(), $to->endOfMonth()->toDateString()]);
    }

    private function share(BigDecimal $part, BigDecimal $total): string
    {
        if ($total->isZero()) {
            return '0';
        }

        return (string) $part->multipliedBy(100)->dividedBy($total, 1, RoundingMode::HalfUp);
    }

    /**
     * @param  list<object>  $rows
     * @return array<string, string>
     */
    private function trackNames(array $rows): array
    {
        $tracks = Track::query()->whereIn('id', array_filter(array_map(fn ($row) => $row->track_id, $rows)))->get(['id', 'title', 'isrc'])->keyBy('id');
        $releases = Release::withTrashed()->whereIn('id', array_filter(array_map(fn ($row) => $row->release_id, $rows)))->get(['id', 'title'])->keyBy('id');
        $names = [];

        foreach ($rows as $row) {
            $track = $tracks->get($row->track_id);
            $release = $releases->get($row->release_id);
            $names[$row->track_id.'-'.$row->release_id] = $track !== null
                ? $track->title.($track->isrc ? ' · '.$track->isrc : '')
                : ($release !== null ? __('finance.earnings.breakdown.release_only', ['title' => $release->title]) : __('finance.earnings.breakdown.unknown'));
        }

        return $names;
    }

    private function track(int $id): ?Track
    {
        return $this->tracks[$id] ??= Track::query()->find($id, ['id', 'title', 'isrc']);
    }

    private function release(int $id): ?Release
    {
        return $this->releases[$id] ??= Release::withTrashed()->find($id, ['id', 'title', 'upc']);
    }
}
