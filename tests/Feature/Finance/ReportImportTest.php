<?php

use App\Domain\Finance\BlockedEarnings;
use App\Domain\Finance\Ledger;
use App\Domain\Finance\ManualMatches;
use App\Domain\Finance\ReportApproval;
use App\Domain\Finance\ReportImporter;
use App\Domain\Finance\ReportUnreadable;
use App\Enums\AdminRole;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Enums\ReportImportStatus;
use App\Jobs\RecalculateReportImport;
use App\Models\Admin;
use App\Models\ExchangeRate;
use App\Models\LedgerEntry;
use App\Models\MatchRule;
use App\Models\Release;
use App\Models\ReportImport;
use App\Models\ReportLine;
use App\Models\ReportMapping;
use App\Models\StreamStat;
use App\Models\User;
use App\Notifications\EarningsAdded;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    Notification::fake();

    $this->admin = Admin::factory()->withRole(AdminRole::Finance)->create();

    // Planı olan kullanıcı: kazanç kullanılabilir kovaya, satış ayındaki pay %80.
    $this->artist = User::factory()->create();
    planHistory($this->artist, '80.00', '2025-01-01');
    activePlan($this->artist);
    $this->release = Release::factory()->for($this->artist)->complete()->create();
    $this->release->forceFill(['upc' => '000000000017'])->save();
    $this->track = $this->release->tracks()->first();
    $this->track->forceFill(['isrc' => 'TRABC2600001'])->save();

    // Planı bitmiş kullanıcı: kazanç bloke kovaya, pay %70.
    $this->lapsed = User::factory()->create();
    planHistory($this->lapsed, '70.00', '2025-01-01', '2026-09-01');
    $this->lapsedRelease = Release::factory()->for($this->lapsed)->complete()->create();
    $this->lapsedRelease->tracks()->first()->forceFill(['isrc' => 'TRXYZ2600009'])->save();

    ExchangeRate::query()->create(['period' => '2026-08', 'from_currency' => 'EUR', 'to_currency' => 'USD', 'rate' => '1.10']);
});

function uploadReport(string $path, string $name = 'rapor.csv'): ReportImport
{
    $import = app(ReportImporter::class)->upload($path, $name, ReportMapping::query()->where('is_default', true)->firstOrFail(), null, test()->admin);

    return $import->refresh();
}

function standardReport(): string
{
    return believeCsv([
        ['month' => '2026-08', 'isrc' => 'TRABC2600001', 'net' => '10,00', 'currency' => 'EUR', 'quantity' => 2500],
        ['month' => '2026-08', 'upc' => '17', 'net' => '5.5', 'currency' => 'USD', 'quantity' => 1000, 'platform' => 'Apple Music'],
        ['month' => '2026-08', 'isrc' => 'TRXYZ2600009', 'net' => '20', 'currency' => 'USD', 'quantity' => 4000],
        ['month' => '2026-08', 'isrc' => 'USZZZ2600001', 'artist' => 'Bilinmeyen', 'track' => 'Eşleşmeyen Şarkı', 'net' => '3', 'currency' => 'USD', 'quantity' => 300],
    ]);
}

it('previews a report without touching any balance', function () {
    $import = uploadReport(standardReport());

    expect($import->status)->toBe(ReportImportStatus::Preview)
        ->and($import->row_count)->toBe(4)
        ->and($import->matched_count)->toBe(3)
        ->and($import->unmatched_count)->toBe(1)
        ->and($import->periods)->toBe(['2026-08'])
        ->and($import->warnings['blocking'])->toBe([])
        // 10 EUR × 1,10 = 11 USD + 5,5 + 20 + 3
        ->and($import->totals['amount_usd'])->toBe('39.500000')
        ->and($import->totals['unmatched_usd'])->toBe('3.000000')
        // (11 + 5,5) × %80 + 20 × %70
        ->and($import->totals['users_usd'])->toBe('27.200000')
        ->and(LedgerEntry::query()->count())->toBe(0)
        ->and(StreamStat::query()->count())->toBe(0);

    $isrcLine = ReportLine::query()->where('isrc', 'TRABC2600001')->firstOrFail();
    $upcLine = ReportLine::query()->where('upc', '000000000017')->firstOrFail();

    expect($isrcLine->match_source)->toBe('isrc')
        ->and($isrcLine->track_id)->toBe($this->track->id)
        ->and((string) $isrcLine->share_pct)->toBe('80.00')
        ->and((string) $isrcLine->user_amount_usd)->toBe('8.8000000000')
        ->and($upcLine->match_source)->toBe('upc')
        ->and($upcLine->user_id)->toBe($this->artist->id);
});

it('credits each user once on approval, available with a plan and blocked without', function () {
    $import = app(ReportApproval::class)->approve(uploadReport(standardReport()), $this->admin);
    $ledger = app(Ledger::class);

    expect($import->status)->toBe(ReportImportStatus::Approved)
        ->and((string) $ledger->balance($this->artist, LedgerBucket::Available))->toBe('13.200000')
        ->and((string) $ledger->balance($this->artist, LedgerBucket::Blocked))->toBe('0.000000')
        ->and((string) $ledger->balance($this->lapsed, LedgerBucket::Blocked))->toBe('14.000000')
        ->and((string) $ledger->balance($this->lapsed, LedgerBucket::Available))->toBe('0.000000')
        ->and(LedgerEntry::query()->where('type', LedgerEntryType::Earning)->count())->toBe(2)
        ->and((int) StreamStat::query()->where('user_id', $this->artist->id)->sum('quantity'))->toBe(3500)
        ->and((string) StreamStat::query()->where('user_id', $this->lapsed->id)->sum('revenue_usd'))->toStartWith('14');

    Notification::assertSentTo($this->artist, EarningsAdded::class);
    Notification::assertSentTo($this->lapsed, EarningsAdded::class);
});

it('refuses to approve the same report twice', function () {
    $import = app(ReportApproval::class)->approve(uploadReport(standardReport()), $this->admin);

    expect(fn () => app(ReportApproval::class)->approve($import, $this->admin))->toThrow(ReportUnreadable::class)
        ->and(LedgerEntry::query()->where('type', LedgerEntryType::Earning)->count())->toBe(2);
});

it('rejects a duplicate file until the original is reversed', function () {
    $path = standardReport();
    $copy = $path.'.copy.csv';
    copy($path, $copy);

    $import = app(ReportApproval::class)->approve(uploadReport($path), $this->admin);

    expect(fn () => uploadReport($copy, 'ayni-rapor.csv'))->toThrow(ReportUnreadable::class, __('finance.import.errors.duplicate_file', ['date' => $import->created_at->format('d.m.Y')]))
        ->and(ReportImport::query()->count())->toBe(1);

    app(ReportApproval::class)->reverse($import, $this->admin, 'Yanlış eşleştirme');

    expect(uploadReport($copy, 'ayni-rapor.csv')->status)->toBe(ReportImportStatus::Preview);
});

it('blocks approval while an exchange rate is missing and recalculates once it is entered', function () {
    $import = uploadReport(believeCsv([
        ['month' => '2026-07', 'isrc' => 'TRABC2600001', 'net' => '100', 'currency' => 'TRY'],
    ]));

    expect($import->warnings['blocking'])->toHaveCount(1)
        ->and($import->warnings['blocking'][0])->toContain('TRY 2026-07')
        ->and(fn () => app(ReportApproval::class)->approve($import, $this->admin))->toThrow(ReportUnreadable::class);

    // USD → TRY kuru girilirse ters kur kullanılır: 100 TRY / 40 = 2,5 USD.
    ExchangeRate::query()->create(['period' => '2026-07', 'from_currency' => 'USD', 'to_currency' => 'TRY', 'rate' => '40']);
    RecalculateReportImport::dispatch($import->id);

    $import->refresh();

    expect($import->status)->toBe(ReportImportStatus::Preview)
        ->and($import->warnings['blocking'])->toBe([])
        ->and($import->totals['amount_usd'])->toBe('2.500000')
        ->and($import->totals['users_usd'])->toBe('2.000000');

    app(ReportApproval::class)->approve($import, $this->admin);

    expect((string) app(Ledger::class)->balance($this->artist, LedgerBucket::Available))->toBe('2.000000');
});

it('does not let two reports cover the same period', function () {
    app(ReportApproval::class)->approve(uploadReport(standardReport()), $this->admin);

    $second = uploadReport(believeCsv([
        ['month' => '2026-08', 'isrc' => 'TRABC2600001', 'net' => '1', 'currency' => 'USD', 'platform' => 'Deezer'],
    ]));

    expect($second->warnings['blocking'])->toHaveCount(1)
        ->and(fn () => app(ReportApproval::class)->approve($second, $this->admin))->toThrow(ReportUnreadable::class)
        ->and(LedgerEntry::query()->where('type', LedgerEntryType::Earning)->count())->toBe(2);
});

it('reverses an approved report with counter entries and removes its statistics', function () {
    $import = app(ReportApproval::class)->approve(uploadReport(standardReport()), $this->admin);

    app(ReportApproval::class)->reverse($import, $this->admin, 'Hatalı dönem');
    $ledger = app(Ledger::class);

    expect($import->refresh()->status)->toBe(ReportImportStatus::Reversed)
        ->and($import->reverse_reason)->toBe('Hatalı dönem')
        ->and((string) $ledger->balances($this->artist)->total())->toBe('0.000000')
        ->and((string) $ledger->balances($this->lapsed)->total())->toBe('0.000000')
        ->and(LedgerEntry::query()->where('type', LedgerEntryType::Earning)->count())->toBe(2)
        ->and(LedgerEntry::query()->where('type', LedgerEntryType::EarningReversal)->whereNotNull('reversal_of_id')->count())->toBe(2)
        ->and(StreamStat::query()->count())->toBe(0)
        ->and(fn () => app(ReportApproval::class)->reverse($import, $this->admin, 'Tekrar'))->toThrow(ReportUnreadable::class);
});

it('takes a reversal from available funds when the blocked earning was released meanwhile', function () {
    $import = app(ReportApproval::class)->approve(uploadReport(standardReport()), $this->admin);
    $ledger = app(Ledger::class);

    // Bloke kazancın tamamı plan alınınca serbest kalır, sonra kullanıcıya 1 USD daha bloke yazılır.
    app(BlockedEarnings::class)->release($this->lapsed);
    credit($this->lapsed, '1', LedgerBucket::Blocked);

    app(ReportApproval::class)->reverse($import, $this->admin, 'Yanlış rapor');

    expect((string) $ledger->balance($this->lapsed, LedgerBucket::Blocked))->toBe('0.000000')
        ->and((string) $ledger->balance($this->lapsed, LedgerBucket::Available))->toBe('1.000000')
        ->and((string) $ledger->balances($this->lapsed)->total())->toBe('1.000000');
});

it('remembers a manual match and applies it to later reports', function () {
    $import = uploadReport(standardReport());
    $line = ReportLine::query()->where('match_status', 'unmatched')->firstOrFail();

    app(ManualMatches::class)->match($line, 'title', $this->release, $this->track, $this->admin);

    $import->refresh();
    $rule = MatchRule::query()->sole();

    expect($rule->key)->toBe('bilinmeyen | eşleşmeyen şarkı')
        ->and($import->matched_count)->toBe(4)
        ->and($import->unmatched_count)->toBe(0)
        ->and($line->refresh()->match_source)->toBe('rule')
        ->and($line->user_id)->toBe($this->artist->id);

    app(ReportApproval::class)->approve($import, $this->admin);

    $next = uploadReport(believeCsv([
        ['month' => '2026-09', 'artist' => ' BILINMEYEN', 'track' => 'Eşleşmeyen  Şarkı', 'net' => '2', 'currency' => 'USD'],
    ]));

    expect($next->matched_count)->toBe(1)
        ->and(ReportLine::query()->where('report_import_id', $next->id)->value('match_source'))->toBe('rule');
});

it('rejects files that are not what their extension claims', function () {
    $fake = tempnam(sys_get_temp_dir(), 'hm-fake-');
    file_put_contents($fake, "PK\x03\x04 değil, ikili \0 veri");

    expect(fn () => uploadReport($fake, 'rapor.csv'))->toThrow(ReportUnreadable::class)
        ->and(fn () => uploadReport(standardReport(), 'rapor.xlsx'))->toThrow(ReportUnreadable::class)
        ->and(fn () => uploadReport(standardReport(), 'rapor.php'))->toThrow(ReportUnreadable::class)
        ->and(ReportImport::query()->count())->toBe(0);
});

it('marks a report without data rows as failed', function () {
    $import = uploadReport(believeCsv([]));

    expect($import->status)->toBe(ReportImportStatus::Failed)
        ->and($import->error)->toBe(__('finance.import.errors.empty'));
});
