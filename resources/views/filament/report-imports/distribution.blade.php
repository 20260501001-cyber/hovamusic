{{-- Önizlemede kullanıcı bazlı dağılım; onay sonrası yazılan tutarlarla aynıdır. --}}
@php
    $record = $getRecord();
    $rows = $record ? \App\Models\ReportLine::query()
        ->where('report_import_id', $record->id)
        ->where('match_status', 'matched')
        ->whereNotNull('user_id')
        ->groupBy('user_id')
        ->selectRaw('user_id, COUNT(*) as line_count, SUM(quantity) as quantity, SUM(amount_usd) as gross, SUM(user_amount_usd) as total, MIN(share_pct) as min_share, MAX(share_pct) as max_share')
        ->orderByDesc('total')
        ->get() : collect();
    $users = \App\Models\User::withTrashed()->whereIn('id', $rows->pluck('user_id'))->get(['id', 'email'])->keyBy('id');
    $withPlan = \App\Models\Subscription::query()->active()->whereIn('user_id', $rows->pluck('user_id'))->pluck('user_id')->flip();
    $preview = $record?->status === \App\Enums\ReportImportStatus::Preview;
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @if ($rows->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">Eşleşen satır yok.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <tr>
                        <th class="py-2 pe-4 font-medium">Kullanıcı</th>
                        <th class="py-2 pe-4 text-end font-medium">Satır</th>
                        <th class="py-2 pe-4 text-end font-medium">Adet</th>
                        <th class="py-2 pe-4 text-end font-medium">Brüt (USD)</th>
                        <th class="py-2 pe-4 text-end font-medium">Pay %</th>
                        <th class="py-2 pe-4 text-end font-medium">Kullanıcıya (USD)</th>
                        @if ($preview)<th class="py-2 font-medium">Yazılacağı kova</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($rows as $row)
                        @php
                            $user = $users->get($row->user_id);
                            $share = $row->min_share === $row->max_share ? ($row->min_share ?? '0') : $row->min_share.'–'.$row->max_share;
                        @endphp
                        <tr>
                            <td class="py-2 pe-4">{{ $user?->email ?? '#'.$row->user_id }}</td>
                            <td class="py-2 pe-4 text-end tabular-nums">{{ number_format((int) $row->line_count, 0, ',', '.') }}</td>
                            <td class="py-2 pe-4 text-end tabular-nums">{{ number_format((int) $row->quantity, 0, ',', '.') }}</td>
                            <td class="py-2 pe-4 text-end tabular-nums">{{ \App\Support\Format::money((string) ($row->gross ?? '0')) }}</td>
                            <td class="py-2 pe-4 text-end tabular-nums">{{ $share }}</td>
                            <td class="py-2 pe-4 text-end font-medium tabular-nums">{{ \App\Support\Format::money((string) ($row->total ?? '0')) }}</td>
                            @if ($preview)
                                <td class="py-2">
                                    @if ($withPlan->has($row->user_id))
                                        <x-filament::badge color="success">Çekilebilir</x-filament::badge>
                                    @else
                                        <x-filament::badge color="warning">Bloke (aktif plan yok)</x-filament::badge>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-dynamic-component>
