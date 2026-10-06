<?php

namespace Database\Seeders;

use App\Domain\Finance\Ledger;
use App\Domain\Finance\Money;
use App\Enums\ArtistRole;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Enums\MediaKind;
use App\Enums\MediaStatus;
use App\Enums\ReleaseStatus;
use App\Enums\ReleaseType;
use App\Enums\ReportImportStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\MediaFile;
use App\Models\Platform;
use App\Models\Release;
use App\Models\ReportImport;
use App\Models\StreamStat;
use App\Models\Track;
use App\Models\User;
use App\Models\Withdrawal;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Yalnızca local: panel ekran görüntüleri için demo sanatçı, yayınlar (gerçek kapak
 * dosyalarıyla), onaylanmış bir demo rapordan dinlenme istatistikleri, bakiye ve
 * ödenmiş bir para çekme talebi. Tüm adlar ve rakamlar kurgudur.
 */
class DemoContentSeeder extends Seeder
{
    private const RELEASES = [
        ['Gece Yarısı Treni', ReleaseType::Single, ReleaseStatus::Live, ['Gece Yarısı Treni'], [20, 40, 60]],
        ['Kıyı', ReleaseType::Ep, ReleaseStatus::Live, ['Kıyı', 'Tuz', 'Rüzgârın Altı', 'Fener'], [160, 90, 70]],
        ['Uzak Şehirler', ReleaseType::Album, ReleaseStatus::InReview, ['Uzak Şehirler', 'Peron', 'Kar', 'Eski Fotoğraf', 'Akşam Üstü', 'Son Durak'], [40, 50, 110]],
        ['Son Vapur', ReleaseType::Single, ReleaseStatus::Draft, ['Son Vapur'], [200, 120, 50]],
    ];

    private const PLATFORM_WEIGHTS = ['Spotify' => 46, 'YouTube Music' => 18, 'Apple Music' => 14, 'fizy' => 8, 'Deezer' => 6, 'TikTok' => 5, 'Amazon Music' => 3];

    private const COUNTRY_WEIGHTS = ['Türkiye' => 58, 'Almanya' => 14, 'Hollanda' => 7, 'Azerbaycan' => 7, 'Fransa' => 5, 'Amerika Birleşik Devletleri' => 5, 'Birleşik Krallık' => 4];

    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $user = User::query()->where('email', 'sanatci@demo.hovamusic.test')->first();

        if ($user === null || $user->releases()->where('title', 'Gece Yarısı Treni')->exists()) {
            return;
        }

        $user->forceFill(['theme' => 'dark', 'display_currency' => 'USD'])->save();

        $artist = new Artist(['name' => 'Deniz Aras', 'create_new_spotify' => true, 'create_new_apple' => true]);
        $artist->user()->associate($user);
        $artist->save();
        $genre = Genre::query()->whereNull('parent_id')->orderBy('id')->first();
        $platformIds = Platform::query()->where('is_active', true)->pluck('id')->all();
        $releases = [];

        foreach (self::RELEASES as $index => [$title, $type, $status, $tracks, $color]) {
            $releases[] = $this->release($user, $artist, $genre?->id, $platformIds, $index, $title, $type, $status, $tracks, $color);
        }

        $this->earnings($user, array_slice($releases, 0, 2));
    }

    /**
     * @param  list<int>  $platformIds
     * @param  list<string>  $trackTitles
     * @param  array{0: int, 1: int, 2: int}  $color
     */
    private function release(User $user, Artist $artist, ?int $genreId, array $platformIds, int $index, string $title, ReleaseType $type, ReleaseStatus $status, array $trackTitles, array $color): Release
    {
        $live = $status === ReleaseStatus::Live;
        $releaseDate = $live ? today()->subMonths(14 - $index * 4) : today()->addDays(21 + $index * 7);

        $release = new Release([
            'type' => $type,
            'title' => $title,
            'label_name' => config('hova.default_label'),
            'genre_id' => $genreId,
            'language' => 'tr',
            'release_date' => $releaseDate,
            'p_line' => $releaseDate->year.' Deniz Aras',
            'c_line' => $releaseDate->year.' Deniz Aras',
            'upc' => $status === ReleaseStatus::Draft ? null : '19'.str_pad((string) (7300000000 + $index), 10, '0', STR_PAD_LEFT),
            'cover_media_id' => $this->cover($user, $title, $color)->id,
            'wizard_step' => $status === ReleaseStatus::Draft ? 2 : 6,
        ]);
        $release->user()->associate($user);
        $release->status = $status;

        if ($status !== ReleaseStatus::Draft) {
            $release->forceFill(['submitted_at' => $releaseDate->copy()->subDays(20), 'first_submitted_at' => $releaseDate->copy()->subDays(20)]);
        }

        $release->save();
        $release->platforms()->sync($platformIds);
        $release->artists()->create(['artist_id' => $artist->id, 'name' => $artist->name, 'role' => ArtistRole::Primary->value, 'position' => 0]);

        foreach ($trackTitles as $position => $trackTitle) {
            $audio = MediaFile::factory()->for($user)->audio(180_000 + $position * 17_000)->create();
            $track = new Track([
                'position' => $position + 1,
                'title' => $trackTitle,
                'language' => 'tr',
                'isrc' => $status === ReleaseStatus::Draft ? null : 'TRA0D'.substr((string) $releaseDate->year, -2).str_pad((string) ($index * 10 + $position + 1), 5, '0', STR_PAD_LEFT),
                'audio_file_id' => $audio->id,
                'duration_ms' => 180_000 + $position * 17_000,
            ]);
            $track->release()->associate($release);
            $track->save();
        }

        return $release;
    }

    /**
     * 3000×3000 demo kapak: düz zemin, geometrik biçim ve yayın adı.
     *
     * @param  array{0: int, 1: int, 2: int}  $color
     */
    private function cover(User $user, string $title, array $color): MediaFile
    {
        $size = 3000;
        $image = imagecreatetruecolor($size, $size);
        imagefill($image, 0, 0, imagecolorallocate($image, $color[0], $color[1], $color[2]));
        $light = imagecolorallocate($image, 242, 241, 239);
        $shade = imagecolorallocate($image, max(0, $color[0] - 40), max(0, $color[1] - 40), max(0, $color[2] - 40));
        imagefilledellipse($image, 2100, 1100, 1700, 1700, $shade);
        imagefilledrectangle($image, 260, 2380, 2740, 2420, $light);

        foreach (str_split(Str::upper(Str::ascii($title)), 18) as $line => $chunk) {
            imagestring($image, 5, 270, 2460 + $line * 40, $chunk, $light);
        }

        ob_start();
        imagejpeg($image, null, 88);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        $ulid = Str::lower((string) Str::ulid());
        $path = "covers/{$user->ulid}/{$ulid}.jpg";
        Storage::disk('private')->put($path, $jpeg);

        $media = new MediaFile([
            'kind' => MediaKind::Cover,
            'disk' => 'private',
            'path' => $path,
            'original_name' => Str::slug($title).'.jpg',
            'mime' => 'image/jpeg',
            'size' => strlen($jpeg),
            'sha256' => hash('sha256', $jpeg),
            'format' => 'jpeg',
            'width' => $size,
            'height' => $size,
            'color_space' => 'RGB',
            'validation_status' => MediaStatus::Valid,
            'analyzed_at' => now(),
        ]);
        $media->ulid = $ulid;
        $media->user()->associate($user);
        $media->save();

        return $media;
    }

    /**
     * Onaylanmış demo rapor: on aylık dinlenme istatistikleri, kazanç kaydı ve ödenmiş bir çekim.
     *
     * @param  list<Release>  $releases
     */
    private function earnings(User $user, array $releases): void
    {
        mt_srand(2026);
        $months = collect(range(11, 2))->map(fn (int $ago) => today()->startOfMonth()->subMonths($ago));
        $import = new ReportImport([
            'original_name' => 'demo-satis-raporu.csv',
            'file_path' => 'reports/demo.csv',
            'file_sha256' => hash('sha256', 'demo-report-'.$user->id),
            'status' => ReportImportStatus::Approved,
            'periods' => $months->map->format('Y-m')->all(),
            'warnings' => ['blocking' => [], 'notes' => []],
        ]);
        $import->forceFill(['approved_at' => now()->subDays(3)])->save();

        $rows = [];
        $total = BigDecimal::zero();
        $quantity = 0;

        foreach ($months as $m => $month) {
            $growth = 1 + $m * 0.12;

            foreach ($releases as $release) {
                foreach ($release->tracks()->get(['id', 'release_id']) as $track) {
                    foreach (self::PLATFORM_WEIGHTS as $platform => $pWeight) {
                        foreach (self::COUNTRY_WEIGHTS as $country => $cWeight) {
                            $qty = (int) round(mt_rand(60, 140) * $pWeight * $cWeight / 100 * $growth);
                            $rate = BigDecimal::of(mt_rand(20, 38))->dividedBy(10000, 6, RoundingMode::HalfUp);
                            $revenue = Money::ledger(BigDecimal::of($qty)->multipliedBy($rate)->multipliedBy('0.85'));
                            $total = $total->plus($revenue);
                            $quantity += $qty;
                            $rows[] = [
                                'user_id' => $user->id,
                                'report_import_id' => $import->id,
                                'release_id' => $release->id,
                                'track_id' => $track->id,
                                'platform' => $platform,
                                'country' => $country,
                                'month' => $month->toDateString(),
                                'quantity' => $qty,
                                'revenue_usd' => (string) $revenue,
                            ];
                        }
                    }
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            StreamStat::query()->insert($chunk);
        }

        $import->forceFill([
            'row_count' => count($rows),
            'matched_count' => count($rows),
            'totals' => ['users_usd' => (string) $total, 'amount_usd' => (string) $total, 'users' => 1, 'quantity' => $quantity, 'net_by_currency' => ['USD' => (string) $total->toScale(2, RoundingMode::HalfUp)]],
        ])->save();

        $ledger = app(Ledger::class);

        DB::transaction(function () use ($ledger, $user, $import, $total, $months): void {
            $ledger->post($user, LedgerBucket::Available, LedgerEntryType::Earning, $total, $import,
                __('finance.ledger.earning', ['periods' => $months->first()->format('Y-m').' – '.$months->last()->format('Y-m')]));

            $amount = Money::payout($total->multipliedBy('0.4'));
            $fee = Money::payout('3.20');
            $withdrawal = new Withdrawal([
                'user_id' => $user->id,
                'amount_usd' => (string) $amount,
                'payout_snapshot' => ['account_holder' => 'Deniz Aras', 'iban' => 'TR330006100519786457841326', 'account_number' => null, 'routing_number' => null, 'swift_bic' => null, 'bank_name' => 'Demo Bank', 'bank_country' => 'TR', 'currency' => 'TRY', 'last4' => '1326'],
                'payout_currency' => 'TRY',
                'estimated_fee_usd' => (string) $fee,
                'status' => WithdrawalStatus::Paid,
                'fee_usd' => (string) $fee,
                'net_usd' => (string) $amount->minus($fee),
                'paid_amount' => (string) Money::payout($amount->minus($fee)->multipliedBy(40)),
                'paid_at' => now()->subDays(1),
            ]);
            $withdrawal->save();

            $ledger->transfer($user, LedgerBucket::Available, LedgerBucket::Reserved, LedgerEntryType::WithdrawalReserve, $amount, $withdrawal, __('finance.ledger.withdrawal_reserve'), $user);
            $ledger->post($user, LedgerBucket::Reserved, LedgerEntryType::WithdrawalPaid, $amount->minus($fee)->negated(), $withdrawal, __('finance.ledger.withdrawal_paid'));
            $ledger->post($user, LedgerBucket::Reserved, LedgerEntryType::WithdrawalFee, $fee->negated(), $withdrawal, __('finance.ledger.withdrawal_fee'));
        });
    }
}
