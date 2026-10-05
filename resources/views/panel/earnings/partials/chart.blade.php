{{-- Aylık gelir çubuk grafiği; veriler aşağıdaki tabloda da var. --}}
@php
    $width = 720;
    $height = 220;
    $top = 24;
    $bottom = 28;
    $left = 4;
    $right = 4;
    $count = max(count($monthly), 1);
    $max = collect($monthly)->reduce(fn ($carry, $row) => $row['revenue']->isGreaterThan($carry) ? $row['revenue'] : $carry, \Brick\Math\BigDecimal::zero());
    $plot = $height - $top - $bottom;
    $slot = ($width - $left - $right) / $count;
    $barWidth = max(4, min(40, $slot * 0.62));
    $every = (int) ceil($count / 12);
@endphp

<svg class="hm-chart" viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-labelledby="gelir-grafik-baslik gelir-grafik-aciklama" preserveAspectRatio="xMidYMid meet">
    <title id="gelir-grafik-baslik">{{ __('finance.earnings.chart.title') }}</title>
    <desc id="gelir-grafik-aciklama">{{ __('finance.earnings.chart.caption', ['from' => $from->translatedFormat('F Y'), 'to' => $to->translatedFormat('F Y')]) }}</desc>

    <line class="hm-chart__grid" x1="{{ $left }}" y1="{{ $top }}" x2="{{ $width - $right }}" y2="{{ $top }}" />
    <line class="hm-chart__grid" x1="{{ $left }}" y1="{{ $top + $plot }}" x2="{{ $width - $right }}" y2="{{ $top + $plot }}" />
    @if ($max->isPositive())
        <text class="hm-chart__axis" x="{{ $left }}" y="{{ $top - 8 }}">{{ $money->format($max) }}</text>
    @endif

    @foreach ($monthly as $i => $row)
        @php
            $ratio = $max->isPositive() ? (float) (string) $row['revenue']->dividedBy($max, 4, \Brick\Math\RoundingMode::HalfUp) : 0;
            $barHeight = $row['revenue']->isPositive() ? max(2, $ratio * $plot) : 1;
            $x = $left + $slot * $i + ($slot - $barWidth) / 2;
        @endphp
        <rect @class(['hm-chart__bar', 'hm-chart__bar--empty' => ! $row['revenue']->isPositive()])
            x="{{ round($x, 2) }}" y="{{ round($top + $plot - $barHeight, 2) }}" width="{{ round($barWidth, 2) }}" height="{{ round($barHeight, 2) }}" rx="2">
            <title>{{ $row['month']->translatedFormat('F Y') }}: {{ $money->format($row['revenue']) }}</title>
        </rect>
        @if ($i % $every === 0)
            <text class="hm-chart__label" x="{{ round($left + $slot * $i + $slot / 2, 2) }}" y="{{ $height - 8 }}" text-anchor="middle">{{ $row['month']->translatedFormat('M y') }}</text>
        @endif
    @endforeach
</svg>
