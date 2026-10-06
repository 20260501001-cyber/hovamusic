<?php

use App\Domain\Billing\PlanHistoryRecorder;
use App\Domain\Finance\Ledger;
use App\Domain\Media\AudioProbe;
use App\Domain\Media\ProbeResult;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Enums\SubscriptionStatus;
use App\Enums\TaxFormType;
use App\Models\LedgerEntry;
use App\Models\PayoutMethod;
use App\Models\Plan;
use App\Models\PlanHistory;
use App\Models\Release;
use App\Models\Subscription;
use App\Models\TaxForm;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationData(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Deniz Yılmaz',
        'email' => 'Deniz@Example.com',
        'password' => 'guvenli-sifre-42',
        'password_confirmation' => 'guvenli-sifre-42',
        'account_type' => 'artist',
        'consents' => [
            'kvkk-aydinlatma' => '1',
            'uyelik-sozlesmesi' => '1',
        ],
    ], $overrides);
}

/**
 * 3000×3000 (ya da verilen boyutta) JPEG kapak üretir; $exif verilirse dosyaya
 * içinde bu metin geçen bir EXIF (APP1) bölümü eklenir.
 */
function makeJpeg(int $width = 3000, int $height = 3000, ?string $exif = null): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 76, 59, 255));
    $path = tempnam(sys_get_temp_dir(), 'hm-cover-');
    imagejpeg($image, $path, 70);
    imagedestroy($image);

    if ($exif !== null) {
        $bytes = file_get_contents($path);
        $payload = "Exif\0\0MM\0*\0\0\0\x08".$exif;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
        file_put_contents($path, substr($bytes, 0, 2).$segment.substr($bytes, 2));
    }

    return $path;
}

/**
 * Gri tonlamalı (renk tipi 0) PNG; GD bu tipte dosya yazamadığı için elle kurulur.
 */
function makeGrayscalePng(int $size = 3000): string
{
    $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    $raw = str_repeat("\0".str_repeat("\x80", $size), $size);
    $path = tempnam(sys_get_temp_dir(), 'hm-gray-');

    file_put_contents($path, "\x89PNG\r\n\x1A\n"
        .$chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 0, 0, 0, 0))
        .$chunk('IDAT', gzcompress($raw))
        .$chunk('IEND', ''));

    return $path;
}

/**
 * Geçerli başlığa sahip küçük bir WAV dosyasının içeriği (ses verisi sessizlik).
 */
function wavBytes(int $dataBytes = 10_000): string
{
    $header = 'RIFF'.pack('V', 36 + $dataBytes).'WAVE'
        .'fmt '.pack('VvvVVvv', 16, 1, 2, 44100, 44100 * 2 * 3, 6, 24)
        .'data'.pack('V', $dataBytes);

    return $header.str_repeat("\0", $dataBytes);
}

/**
 * ffprobe yerine sabit sonuç döndüren ses ölçer.
 */
function fakeAudioProbe(?ProbeResult $result = null): void
{
    $result ??= new ProbeResult('wav', 'pcm_s24le', 44100, 24, 2, 200_000);

    app()->instance(AudioProbe::class, new class($result) implements AudioProbe
    {
        public function __construct(private readonly ?ProbeResult $result) {}

        public function probe(string $absolutePath): ?ProbeResult
        {
            return $this->result;
        }
    });
}

/**
 * Kullanıcıya elle açılmış aktif abonelik ve plan geçmişi kaydı verir.
 *
 * @param  array<string, mixed>  $plan
 * @param  array<string, mixed>  $subscription
 */
function activePlan(User $user, array $plan = [], array $subscription = []): Subscription
{
    $model = Plan::query()->create(array_merge([
        'name' => 'Test Planı',
        'audience' => $user->account_type,
        'interval' => 'year',
        'price_usd' => '49.00',
        'release_limit' => 10,
        'artist_limit' => 3,
        'revenue_share_pct' => '85.00',
        'polar_product_id' => 'prod_'.Str::random(10),
        'is_active' => true,
    ], $plan));

    $record = new Subscription(array_merge([
        'plan_id' => $model->id,
        'provider' => 'manual',
        'status' => SubscriptionStatus::Active,
        'started_at' => now()->subMonth(),
        'current_period_start' => now()->subMonth(),
        'current_period_end' => now()->addMonths(11),
    ], $subscription));
    $record->user()->associate($user);
    $record->save();

    app(PlanHistoryRecorder::class)->sync($user);

    return $record;
}

/**
 * Yayının sahibine aktif plan verir (gönderim ve yükleme plan gerektirir).
 *
 * @param  array<string, mixed>  $plan
 */
function planned(Release $release, array $plan = []): Release
{
    activePlan($release->user, $plan);

    return $release;
}

/**
 * Belirli bir dönem için plan geçmişi kaydı (gelir payı hesabı testleri).
 */
function planHistory(User $user, string $sharePct, string $startsAt, ?string $endsAt = null): PlanHistory
{
    return PlanHistory::query()->create([
        'user_id' => $user->id,
        'plan_name' => 'Plan %'.$sharePct,
        'revenue_share_pct' => $sharePct,
        'release_limit' => 10,
        'artist_limit' => 3,
        'starts_at' => Carbon::parse($startsAt, 'UTC'),
        'ends_at' => $endsAt !== null ? Carbon::parse($endsAt, 'UTC') : null,
        'reason' => 'started',
    ]);
}

/**
 * Varsayılan (Believe) eşleştirme başlıklarıyla CSV satış raporu yazar.
 *
 * @param  list<array<string, string|int>>  $rows
 */
function believeCsv(array $rows, string $delimiter = ';'): string
{
    $headers = ['Sales Month', 'Platform', 'Country / Region', 'Artist Name', 'Release title', 'Track title', 'UPC', 'ISRC', 'Sales Type', 'Quantity', 'Net Revenue', 'Client Payment Currency'];
    $keys = ['month', 'platform', 'country', 'artist', 'release', 'track', 'upc', 'isrc', 'type', 'quantity', 'net', 'currency'];
    $path = tempnam(sys_get_temp_dir(), 'hm-report-').'.csv';
    $handle = fopen($path, 'w');
    fputcsv($handle, $headers, $delimiter, '"', '');

    foreach ($rows as $row) {
        $row += ['platform' => 'Spotify', 'country' => 'Turkey', 'artist' => 'Test Sanatçı', 'release' => 'Test Yayın', 'track' => 'Test Parça', 'upc' => '', 'isrc' => '', 'type' => 'Stream', 'quantity' => 100, 'currency' => 'USD'];
        fputcsv($handle, array_map(fn (string $key) => (string) ($row[$key] ?? ''), $keys), $delimiter, '"', '');
    }

    fclose($handle);

    return $path;
}

/**
 * Para çekme ön koşulları: tam fatura bilgisi, ödeme bilgisi ve geçerli vergi formu.
 */
function readyForWithdrawal(User $user): void
{
    $user->profile()->create([
        'entity_type' => 'individual',
        'legal_name' => 'Deniz Yılmaz',
        'country' => 'TR',
        'citizenship' => 'TR',
        'address_line' => 'Test Mah. 1',
        'city' => 'İstanbul',
        'postal_code' => '34000',
        'tax_id' => '12345678901',
        'date_of_birth' => '1990-01-01',
    ]);

    $method = new PayoutMethod([
        'account_holder' => 'Deniz Yılmaz',
        'iban' => 'TR330006100519786457841326',
        'bank_country' => 'TR',
        'currency' => 'TRY',
        'last4' => '1326',
    ]);
    $method->user()->associate($user);
    $method->save();

    $form = new TaxForm([
        'form_type' => TaxFormType::W8Ben,
        'data' => ['name' => 'Deniz Yılmaz'],
        'signed_name' => 'Deniz Yılmaz',
        'signed_at' => now(),
        'status' => 'valid',
        'expires_at' => now()->addYears(3)->endOfYear()->toDateString(),
    ]);
    $form->user()->associate($user);
    $form->save();

    $user->unsetRelation('profile');
    $user->unsetRelation('payoutMethod');
}

/**
 * Kullanıcının kovasına doğrudan kazanç yazar.
 */
function credit(User $user, string $amount, LedgerBucket $bucket = LedgerBucket::Available): LedgerEntry
{
    return app(Ledger::class)->post($user, $bucket, LedgerEntryType::Earning, BigDecimal::of($amount));
}
