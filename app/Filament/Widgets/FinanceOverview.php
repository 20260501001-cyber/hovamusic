<?php

namespace App\Filament\Widgets;

use App\Domain\Finance\Money;
use App\Enums\OrderStatus;
use App\Enums\ReportImportStatus;
use App\Enums\WithdrawalStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ReportImports\ReportImportResource;
use App\Filament\Resources\Withdrawals\WithdrawalResource;
use App\Models\Admin;
use App\Models\Order;
use App\Models\ReportImport;
use App\Models\Subscription;
use App\Models\Withdrawal;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard: plan satışları, bekleyen para çekme talepleri ve önizlemedeki raporlar.
 * Yalnızca Süper Admin ve Finans görür.
 */
class FinanceOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Satış ve finans';

    public static function canView(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof Admin && $admin->canManageFinance();
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $tz = config('hova.display_timezone');
        $monthStart = now($tz)->startOfMonth()->utc();
        $paid = fn () => Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::PartiallyRefunded])->where('currency', 'USD');

        $monthTotal = (string) ($paid()->where('ordered_at', '>=', $monthStart)->sum('total') ?: '0');
        $monthRefunds = (string) ($paid()->where('ordered_at', '>=', $monthStart)->sum('refunded') ?: '0');
        $monthCount = $paid()->where('ordered_at', '>=', $monthStart)->count();
        $last30 = (string) ($paid()->where('ordered_at', '>=', now()->subDays(30))->sum('total') ?: '0');

        $open = Withdrawal::query()->whereIn('status', [WithdrawalStatus::Pending, WithdrawalStatus::Approved]);
        $openSum = (string) ((clone $open)->sum('amount_usd') ?: '0');

        return [
            Stat::make('Bu ay satış', Format::money($monthTotal))
                ->description($monthCount.' ödeme'.(Money::of($monthRefunds)->isPositive() ? ' · iade '.Format::money($monthRefunds) : ''))
                ->icon('lucide-receipt')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Son 30 gün satış', Format::money($last30))
                ->description(Subscription::query()->active()->count().' aktif abonelik')
                ->icon('lucide-trending-up'),
            Stat::make('Bekleyen para çekme', (clone $open)->count())
                ->description('Toplam '.Format::money($openSum))
                ->icon('lucide-banknote')
                ->color((clone $open)->exists() ? 'warning' : null)
                ->url(WithdrawalResource::getUrl('index')),
            Stat::make('Önizlemedeki rapor', ReportImport::query()->where('status', ReportImportStatus::Preview)->count())
                ->description('Onay bekliyor')
                ->icon('lucide-file-spreadsheet')
                ->url(ReportImportResource::getUrl('index')),
        ];
    }
}
