<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 20px 22px; }
        body { font-family: DejaVu Sans, sans-serif; color: #263247; font-size: 9px; }
        h1 { margin: 0; font-size: 18px; color: #17365d; }
        .meta { margin-top: 4px; color: #667085; }
        .summary { width: 100%; margin: 14px 0; border-collapse: separate; border-spacing: 6px 0; }
        .summary td { background: #eef3f9; padding: 8px; border-radius: 4px; }
        .label { font-size: 8px; color: #667085; text-transform: uppercase; }
        .value { margin-top: 3px; font-size: 12px; font-weight: bold; color: #17365d; }
        .data { width: 100%; border-collapse: collapse; }
        .data th { background: #17365d; color: white; padding: 6px 5px; text-align: left; }
        .data td { border-bottom: 1px solid #e4e8ee; padding: 5px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .code { font-family: DejaVu Sans Mono, monospace; font-weight: bold; }
        .footer { margin-top: 10px; color: #98a2b3; font-size: 8px; text-align: right; }
    </style>
</head>
<body>
    <h1>Sales By Item — {{ $report->filter->databaseLabel() }}</h1>
    <div class="meta">Periode {{ $report->filter->startDate->format('d M Y') }} sampai {{ $report->filter->endDate->format('d M Y') }}</div>

    <table class="summary">
        <tr>
            <td><div class="label">Total Sales</div><div class="value">Rp {{ number_format($report->totalSales, 0, ',', '.') }}</div></td>
            <td><div class="label">Total Quantity</div><div class="value">{{ number_format($report->totalQuantity, 2, ',', '.') }}</div></td>
            <td><div class="label">Jumlah Item</div><div class="value">{{ number_format($report->totalItems, 0, ',', '.') }}</div></td>
            <td><div class="label">Rata-rata / Item</div><div class="value">Rp {{ number_format($report->averageSalesPerItem, 0, ',', '.') }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
        <tr>
            <th class="center">No</th><th>Item Code</th><th>Item Name</th>
            <th class="right">Total Qty</th><th class="right">Total Sales</th>
            <th class="right">Average Price</th><th class="right">Contribution</th><th>Source</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($report->rows as $index => $row)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td class="code">{{ $row->itemCode }}</td>
                <td>{{ $row->itemName }}</td>
                <td class="right">{{ number_format($row->totalQuantity, 2, ',', '.') }}</td>
                <td class="right">Rp {{ number_format($row->totalSales, 0, ',', '.') }}</td>
                <td class="right">Rp {{ number_format($row->averagePrice, 0, ',', '.') }}</td>
                <td class="right">{{ number_format($row->contribution, 2, ',', '.') }}%</td>
                <td>{{ $row->source }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="center">Tidak ada data.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="footer">Dibuat {{ now()->format('d M Y H:i') }} WIB</div>
</body>
</html>
