<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Review Penjualan 3 Bulan</title>
<style>
    @page { margin: 18px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color:#111827; }
    h1 { margin:0 0 4px; font-size:18px; }
    .meta { margin-bottom:10px; color:#4b5563; }
    .summary { width:100%; margin-bottom:10px; border-collapse:separate; border-spacing:6px 0; }
    .summary td { width:25%; border:1px solid #d0d5dd; border-radius:5px; padding:8px; vertical-align:top; }
    .summary .label { font-size:8px; color:#667085; font-weight:bold; }
    .summary .value { font-size:14px; font-weight:bold; margin-top:4px; }
    table.report { width:100%; border-collapse:collapse; }
    .report th,.report td { border:1px solid #111; padding:4px; white-space:nowrap; }
    .report th { background:#ffc000; text-align:center; font-weight:bold; }
    .report .group { background:#ffd45e; }
    .num { text-align:right; }
    .center { text-align:center; }
    .total td { background:#f2f4f7; font-weight:bold; }
    .up { color:#15803d; font-weight:bold; }
    .down { color:#dc3545; font-weight:bold; }
</style>
</head>
<body>
@php
    $number = static fn (float $value): string => abs($value) < .5 ? '-' : number_format($value, 0, ',', '.');
    $percent = static fn (?float $value): string => $value === null ? '—' : number_format($value * 100, 2, ',', '.').'%';
    $growthClass = static fn (?float $value): string => $value === null ? '' : ($value >= 0 ? 'up' : 'down');
@endphp
<h1>Review Penjualan 3 Bulan</h1>
<div class="meta">
    Database: <strong>{{ $filter->databaseLabel() }}</strong> · Status item: <strong>{{ $filter->itemStatusLabel() }}</strong><br>
    Periode: {{ $filter->periodStartDate->format('d-m-Y') }} s/d {{ $filter->selectedDate->format('d-m-Y') }}<br>
    {{ $filter->comparisonNote() }}
</div>
<table class="summary">
<tr>
    <td><div class="label">TOTAL SALES 3 BULAN</div><div class="value">Rp {{ $number($report->totalRow->totalSales) }}</div></td>
    <td><div class="label">RATA-RATA / BULAN</div><div class="value">Rp {{ $number($report->totalRow->averageMonthlySales) }}</div></td>
    <td><div class="label">GROWTH BULAN TERAKHIR</div><div class="value {{ $growthClass($report->totalRow->currentMonthGrowth) }}">{{ $percent($report->totalRow->currentMonthGrowth) }}</div></td>
    <td><div class="label">KONTRIBUTOR TERBESAR</div><div class="value">{{ $report->topContributor?->line ?? '—' }}</div><div>{{ $percent($report->topContributor?->contributionPercentage) }}</div></td>
</tr>
</table>
<table class="report">
<thead>
<tr>
    <th rowspan="2">LINI PENJUALAN</th>
    <th class="group" colspan="2">{{ strtoupper($filter->firstMonthLabel()) }}</th>
    <th class="group" colspan="2">{{ strtoupper($filter->secondMonthLabel()) }}</th>
    <th class="group" colspan="2">{{ strtoupper($filter->currentMonthLabel()) }}</th>
    <th class="group" colspan="5">REVIEW 3 BULAN</th>
</tr>
<tr>
    <th>Sales</th><th>Growth</th><th>Sales</th><th>Growth</th><th>Sales</th><th>Growth</th>
    <th>Total Sales</th><th>Avg/Bulan</th><th>Growth 3 Bulan</th><th>Contribution</th><th>Trend</th>
</tr>
</thead>
<tbody>
@foreach($report->rows as $row)
<tr>
    <td><strong>{{ $row->line }}</strong></td>
    <td class="num">{{ $number($row->firstMonthSales) }}</td><td class="center">—</td>
    <td class="num">{{ $number($row->secondMonthSales) }}</td><td class="center {{ $growthClass($row->secondMonthGrowth) }}">{{ $percent($row->secondMonthGrowth) }}</td>
    <td class="num">{{ $number($row->currentMonthSales) }}</td><td class="center {{ $growthClass($row->currentMonthGrowth) }}">{{ $percent($row->currentMonthGrowth) }}</td>
    <td class="num">{{ $number($row->totalSales) }}</td><td class="num">{{ $number($row->averageMonthlySales) }}</td>
    <td class="center {{ $growthClass($row->threeMonthGrowth) }}">{{ $percent($row->threeMonthGrowth) }}</td><td class="center">{{ $percent($row->contributionPercentage) }}</td><td class="center">{{ strtoupper($row->trend) }}</td>
</tr>
@endforeach
@php($row = $report->totalRow)
<tr class="total">
    <td>TOTAL</td>
    <td class="num">{{ $number($row->firstMonthSales) }}</td><td class="center">—</td>
    <td class="num">{{ $number($row->secondMonthSales) }}</td><td class="center">{{ $percent($row->secondMonthGrowth) }}</td>
    <td class="num">{{ $number($row->currentMonthSales) }}</td><td class="center">{{ $percent($row->currentMonthGrowth) }}</td>
    <td class="num">{{ $number($row->totalSales) }}</td><td class="num">{{ $number($row->averageMonthlySales) }}</td>
    <td class="center">{{ $percent($row->threeMonthGrowth) }}</td><td class="center">{{ $percent($row->contributionPercentage) }}</td><td class="center">{{ strtoupper($row->trend) }}</td>
</tr>
</tbody>
</table>
</body>
</html>
