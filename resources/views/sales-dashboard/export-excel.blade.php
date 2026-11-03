@php
    $pct = static fn (?float $value): ?float => $value;
@endphp
<table>
    <tr><th colspan="15">SALES REPORT — {{ $report->filter->databaseLabel() }}</th></tr>
    <tr>
        <td colspan="2">Tanggal laporan</td><td colspan="3">{{ $report->filter->selectedDate->format('d-m-Y') }}</td>
        <td colspan="2">Status item</td><td colspan="3">{{ $report->filter->itemStatusLabel() }}</td>
        <td colspan="2">Total HK</td><td colspan="2">{{ $report->filter->totalSalesDays }}</td>
    </tr>
    <tr>
        <td colspan="2">MTD</td><td colspan="5">{{ $report->filter->currentStartDate->format('d-m-Y') }} s/d {{ $report->filter->currentEndDate->format('d-m-Y') }}</td>
        <td colspan="2">LMTD</td><td colspan="5">{{ $report->filter->previousStartDate->format('d-m-Y') }} s/d {{ $report->filter->previousEndDate->format('d-m-Y') }}</td>
    </tr>
    <tr><td colspan="15"></td></tr>
    <tr>
        <th>INGCO &amp; SMI</th>
        <th>Monthly Sales Target</th>
        <th>Daily Sales Target / HK</th>
        <th>Required Daily Sales</th>
        <th>DTD</th>
        <th>LDTD</th>
        <th>%Age Penjualan/Day</th>
        <th>MTD</th>
        <th>LMTD</th>
        <th>MTD Growth (%)</th>
        <th>Avg Daily Sales</th>
        <th>Est Ach EOM</th>
        <th>Est Ach EOM (%)</th>
        <th>Sales Ach VS Monthly Target (%)</th>
        <th>Gap Sales 100%</th>
    </tr>
    @foreach ($report->rows as $row)
        <tr>
            <td>{{ $row->line }}</td>
            <td>{{ $row->monthlyTarget }}</td>
            <td>{{ $row->dailyTarget }}</td>
            <td>{{ $row->requiredDailySales }}</td>
            <td>{{ $row->dtd }}</td>
            <td>{{ $row->ldtd }}</td>
            <td>{{ $pct($row->dtdGrowth) }}</td>
            <td>{{ $row->mtd }}</td>
            <td>{{ $row->lmtd }}</td>
            <td>{{ $pct($row->mtdGrowth) }}</td>
            <td>{{ $row->averageDailySales }}</td>
            <td>{{ $row->estimatedEom }}</td>
            <td>{{ $pct($row->estimatedEomPercentage) }}</td>
            <td>{{ $pct($row->achievementPercentage) }}</td>
            <td>{{ $row->gap }}</td>
        </tr>
    @endforeach
    @php($row = $report->totalRow)
    <tr>
        <td>TOTAL</td>
        <td>{{ $row->monthlyTarget }}</td><td>{{ $row->dailyTarget }}</td><td>{{ $row->requiredDailySales }}</td>
        <td>{{ $row->dtd }}</td><td>{{ $row->ldtd }}</td><td>{{ $pct($row->dtdGrowth) }}</td>
        <td>{{ $row->mtd }}</td><td>{{ $row->lmtd }}</td><td>{{ $pct($row->mtdGrowth) }}</td>
        <td>{{ $row->averageDailySales }}</td><td>{{ $row->estimatedEom }}</td>
        <td>{{ $pct($row->estimatedEomPercentage) }}</td><td>{{ $pct($row->achievementPercentage) }}</td>
        <td>{{ $row->gap }}</td>
    </tr>
</table>
