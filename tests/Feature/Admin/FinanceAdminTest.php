<?php

use App\Domain\Finance\ReportImporter;
use App\Domain\Finance\Withdrawals;
use App\Enums\AdminRole;
use App\Filament\Pages\FinanceSettings;
use App\Filament\Resources\ExchangeRates\ExchangeRateResource;
use App\Filament\Resources\LedgerEntries\LedgerEntryResource;
use App\Filament\Resources\ReportImports\ReportImportResource;
use App\Filament\Resources\ReportMappings\ReportMappingResource;
use App\Filament\Resources\Withdrawals\WithdrawalResource;
use App\Models\Admin;
use App\Models\ReportMapping;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Notification::fake();
});

it('opens the finance screens only to finance and super admins', function (AdminRole $role, bool $allowed) {
    $this->actingAs(Admin::factory()->withRole($role)->create(), 'admin');

    expect(ReportImportResource::canViewAny())->toBe($allowed)
        ->and(ReportMappingResource::canViewAny())->toBe($allowed)
        ->and(WithdrawalResource::canViewAny())->toBe($allowed)
        ->and(LedgerEntryResource::canViewAny())->toBe($allowed)
        ->and(ExchangeRateResource::canViewAny())->toBe($allowed)
        ->and(FinanceSettings::canAccess())->toBe($allowed);
})->with([
    'finance' => [AdminRole::Finance, true],
    'super admin' => [AdminRole::SuperAdmin, true],
    'review editor' => [AdminRole::ReviewEditor, false],
]);

it('renders the finance pages with data', function () {
    Storage::fake('private');
    $admin = Admin::factory()->withRole(AdminRole::Finance)->withMfa()->create();
    $this->actingAs($admin, 'admin');

    $import = app(ReportImporter::class)->upload(believeCsv([
        ['month' => '2026-08', 'isrc' => 'USZZZ2600001', 'net' => '3', 'currency' => 'USD'],
    ]), 'rapor.csv', ReportMapping::query()->where('is_default', true)->firstOrFail(), null, $admin);

    $user = User::factory()->create();
    readyForWithdrawal($user);
    credit($user, '40');
    $withdrawal = app(Withdrawals::class)->request($user, '25');

    foreach ([
        ReportImportResource::getUrl('index'),
        ReportImportResource::getUrl('view', ['record' => $import->refresh()]),
        WithdrawalResource::getUrl('index'),
        WithdrawalResource::getUrl('view', ['record' => $withdrawal]),
        LedgerEntryResource::getUrl('index'),
        ExchangeRateResource::getUrl('index'),
        ReportMappingResource::getUrl('index'),
        FinanceSettings::getUrl(),
    ] as $url) {
        $this->get($url)->assertOk();
    }

    $this->actingAs(Admin::factory()->withRole(AdminRole::ReviewEditor)->withMfa()->create(), 'admin')
        ->get(WithdrawalResource::getUrl('view', ['record' => $withdrawal]))
        ->assertForbidden();
});
