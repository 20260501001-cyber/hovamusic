<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Finance\DisplayMoney;
use App\Domain\Finance\EarningsReport;
use App\Domain\Finance\Ledger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\EarningsFilterRequest;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EarningsController extends Controller
{
    public function index(EarningsFilterRequest $request, EarningsReport $report, Ledger $ledger): View
    {
        $user = $request->user();
        [$from, $to] = $report->range($user, $request->validated('from'), $request->validated('to'));

        return view('panel.earnings.index', [
            'balances' => $ledger->balances($user),
            'lifetime' => $report->lifetime($user),
            'money' => DisplayMoney::for($user),
            'missingRate' => DisplayMoney::missingRate($user),
            'months' => $report->months($user),
            'from' => $from,
            'to' => $to,
            'monthly' => $report->monthly($user, $from, $to),
            'platforms' => $report->breakdown($user, $from, $to, 'platform'),
            'countries' => $report->breakdown($user, $from, $to, 'country'),
            'tracks' => $report->breakdown($user, $from, $to, 'track'),
            'entries' => $user->ledgerEntries()->latest('id')->limit(10)->get(),
            'hasActivePlan' => $user->activeSubscription() !== null,
        ]);
    }

    public function export(EarningsFilterRequest $request, EarningsReport $report): StreamedResponse
    {
        $user = $request->user();
        [$from, $to] = $report->range($user, $request->validated('from'), $request->validated('to'));
        $filename = __('finance.earnings.csv.filename', ['from' => $from->format('Y-m'), 'to' => $to->format('Y-m')]);

        return response()->streamDownload(function () use ($report, $user, $from, $to): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(fn (string $key): string => __('finance.earnings.csv.'.$key),
                ['month', 'platform', 'country', 'release', 'track', 'isrc', 'upc', 'quantity', 'revenue_usd']), ';', '"', '');

            foreach ($report->csvRows($user, $from, $to) as $row) {
                fputcsv($out, array_map(fn ($value) => self::safeCell($value), $row), ';', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Elektronik tabloda formül olarak çalışabilecek hücreler (=, +, -, @) metne çevrilir.
     */
    private static function safeCell(string|int $value): string|int
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value)) {
            return "'".$value;
        }

        return $value;
    }
}
