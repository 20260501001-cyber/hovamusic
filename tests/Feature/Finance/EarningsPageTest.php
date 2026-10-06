<?php

use App\Domain\Finance\BlockedEarnings;
use App\Domain\Finance\Ledger;
use App\Domain\Finance\ReportApproval;
use App\Domain\Finance\ReportImporter;
use App\Enums\AdminRole;
use App\Enums\LedgerBucket;
use App\Models\Admin;
use App\Models\Release;
use App\Models\ReportMapping;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    Notification::fake();

    $this->user = User::factory()->create();
    planHistory($this->user, '80.00', '2025-01-01');
    activePlan($this->user);
    $release = Release::factory()->for($this->user)->complete()->create();
    $release->tracks()->first()->forceFill(['isrc' => 'TRABC2600001', 'title' => 'Gece Yarısı'])->save();

    $admin = Admin::factory()->withRole(AdminRole::Finance)->create();
    $import = app(ReportImporter::class)->upload(believeCsv([
        ['month' => '2026-07', 'isrc' => 'TRABC2600001', 'platform' => 'Deezer Test Platformu', 'net' => '12.50', 'quantity' => 1500],
        ['month' => '2026-08', 'isrc' => 'TRABC2600001', 'platform' => '=HYPERLINK("http://ornek.test")', 'country' => 'Germany', 'net' => '7.50', 'quantity' => 900],
    ]), 'rapor.csv', ReportMapping::query()->where('is_default', true)->firstOrFail(), null, $admin);

    app(ReportApproval::class)->approve($import->refresh(), $admin);
});

it('shows balances and the monthly breakdown to the owner', function () {
    $this->actingAs($this->user)
        ->get(route('panel.earnings.index', ['from' => '2026-07', 'to' => '2026-08']))
        ->assertOk()
        ->assertSee('Deezer Test Platformu')
        ->assertSee('Gece Yarısı')
        ->assertSee('$16,00');

    $this->actingAs(User::factory()->create())
        ->get(route('panel.earnings.index'))
        ->assertOk()
        ->assertDontSee('Deezer Test Platformu');
});

it('rejects a malformed month filter', function () {
    $this->actingAs($this->user)
        ->get(route('panel.earnings.index', ['from' => '2026-13', 'to' => 'dün']))
        ->assertSessionHasErrors(['from', 'to']);
});

it('exports the selected range as a spreadsheet-safe CSV', function () {
    $response = $this->actingAs($this->user)
        ->get(route('panel.earnings.export', ['from' => '2026-07', 'to' => '2026-08']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('Deezer Test Platformu')
        ->and($csv)->toContain('TRABC2600001')
        ->and($csv)->toContain("'=HYPERLINK")
        ->and($csv)->not->toContain(';=HYPERLINK');
});

it('moves blocked earnings to the available balance when a plan becomes active', function () {
    $user = User::factory()->create();
    credit($user, '12.345678', LedgerBucket::Blocked);
    credit($user, '5');

    app(BlockedEarnings::class)->release($user);
    app(BlockedEarnings::class)->release($user);

    $balances = app(Ledger::class)->balances($user);

    expect((string) $balances->blocked)->toBe('0.000000')
        ->and((string) $balances->available)->toBe('17.345678')
        ->and($user->ledgerEntries()->count())->toBe(4);
});
