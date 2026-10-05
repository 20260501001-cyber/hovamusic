<section class="hm-card overflow-hidden" aria-labelledby="{{ $id }}">
    <div class="hm-card__head">
        <h2 id="{{ $id }}" class="hm-h3">{{ $title }}</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="hm-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('finance.earnings.breakdown.name') }}</th>
                    <th scope="col" class="text-right">{{ __('finance.earnings.breakdown.quantity') }}</th>
                    <th scope="col" class="text-right">{{ __('finance.earnings.breakdown.revenue') }}</th>
                    <th scope="col">{{ __('finance.earnings.breakdown.share') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['rows'] as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td class="text-right hm-num">{{ number_format($row['quantity'], 0, ',', '.') }}</td>
                        <td class="text-right hm-num">{{ $money->format($row['revenue']) }}</td>
                        <td>
                            <span class="hm-share">
                                <span class="hm-share__track"><span class="hm-share__bar" style="width: {{ $row['share'] }}%"></span></span>
                                <span class="hm-share__pct">{{ str_replace('.', ',', $row['share']) }}%</span>
                            </span>
                        </td>
                    </tr>
                @endforeach
                @if ($data['others'])
                    <tr>
                        <td class="hm-muted">{{ __('finance.earnings.breakdown.others', ['count' => $data['others']['count']]) }}</td>
                        <td class="text-right hm-num">{{ number_format($data['others']['quantity'], 0, ',', '.') }}</td>
                        <td class="text-right hm-num">{{ $money->format($data['others']['revenue']) }}</td>
                        <td>
                            <span class="hm-share">
                                <span class="hm-share__track"><span class="hm-share__bar" style="width: {{ $data['others']['share'] }}%"></span></span>
                                <span class="hm-share__pct">{{ str_replace('.', ',', $data['others']['share']) }}%</span>
                            </span>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</section>
