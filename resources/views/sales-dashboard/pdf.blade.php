<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 16px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 6.5px; color: #111; }
        h1 { margin: 0 0 4px; font-size: 14px; }
        .meta { margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #111; padding: 3px; }
        th { background: #ffc000; font-weight: bold; text-align: center; }
        td:first-child { font-weight: bold; }
        .right { text-align: right; }
        .center { text-align: center; }
        .ach { background: #c00000; color: #fff; font-weight: bold; }
        .total td { background: #ffc000; font-weight: bold; }
    </style>
</head>
<body>
@php
    $number = static fn (float $value): string => number_format($value, 0, ',', '.');
    $percent = static fn (?float $value): string => $value === null ? '-' : number_format($value * 100, 0, ',', '.').'%';
@endphp
<h1>SALES REPORT — {{ $report->filter->databaseLabel() }}</h1>
<div class="meta">
    Tanggal {{ $report->filter->selectedDate->format('d-m-Y') }} ·
    {{ $report->filter->itemStatusLabel() }} ·
    MTD {{ $report->filter->currentStartDate->format('d-m-Y') }} s/d {{ $report->filter->currentEndDate->format('d-m-Y') }} ·
    LMTD {{ $report->filter->previousStartDate->format('d-m-Y') }} s/d {{ $report->filter->previousEndDate->format('d-m-Y') }}
</div>
<table>
    <thead>
    <tr>
        <th>Lini</th><th>Monthly Target</th><th>Daily Sales Target / HK</th><th>Required Daily Sales</th><th>DTD</th><th>LDTD</th><th>DTD Growth</th>
        <th>MTD</th><th>LMTD</th><th>MTD Growth</th><th>Avg Daily</th><th>Est EOM</th>
        <th>Est EOM %</th><th>Ach %</th><th>Gap</th><th>Contribution</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($report->rows as $row)
        <tr>
            <td>{{ $row->line }}</td>
            <td class="right">{{ $number($row->monthlyTarget) }}</td><td class="right">{{ $number($row->dailyTarget) }}</td><td class="right">{{ $number($row->requiredDailySales) }}</td>
            <td class="right">{{ $number($row->dtd) }}</td><td class="right">{{ $number($row->ldtd) }}</td><td class="center">{{ $percent($row->dtdGrowth) }}</td>
            <td class="right">{{ $number($row->mtd) }}</td><td class="right">{{ $number($row->lmtd) }}</td><td class="center">{{ $percent($row->mtdGrowth) }}</td>
            <td class="right">{{ $number($row->averageDailySales) }}</td><td class="right">{{ $number($row->estimatedEom) }}</td>
            <td class="center">{{ $percent($row->estimatedEomPercentage) }}</td><td class="center ach">{{ $percent($row->achievementPercentage) }}</td>
            <td class="right">{{ $number($row->gap) }}</td><td class="center">{{ $percent($row->contributionPercentage) }}</td>
        </tr>
    @endforeach
    @php($row = $report->totalRow)
    <tr class="total">
        <td>TOTAL</td>
        <td class="right">{{ $number($row->monthlyTarget) }}</td><td class="right">{{ $number($row->dailyTarget) }}</td><td class="right">{{ $number($row->requiredDailySales) }}</td>
        <td class="right">{{ $number($row->dtd) }}</td><td class="right">{{ $number($row->ldtd) }}</td><td class="center">{{ $percent($row->dtdGrowth) }}</td>
        <td class="right">{{ $number($row->mtd) }}</td><td class="right">{{ $number($row->lmtd) }}</td><td class="center">{{ $percent($row->mtdGrowth) }}</td>
        <td class="right">{{ $number($row->averageDailySales) }}</td><td class="right">{{ $number($row->estimatedEom) }}</td>
        <td class="center">{{ $percent($row->estimatedEomPercentage) }}</td><td class="center">{{ $percent($row->achievementPercentage) }}</td>
        <td class="right">{{ $number($row->gap) }}</td><td class="center">{{ $percent($row->contributionPercentage) }}</td>
    </tr>
    </tbody>
</table>
</body>
</html>
