<table>
    <tr>
        <th colspan="8">SALES BY ITEM — {{ $report->filter->databaseLabel() }}</th>
    </tr>
    <tr><td colspan="2">Periode</td><td colspan="6">{{ $report->filter->startDate->format('d-m-Y') }} s/d {{ $report->filter->endDate->format('d-m-Y') }}</td></tr>
    <tr><td colspan="2">Total Sales</td><td colspan="2">{{ $report->totalSales }}</td><td colspan="2">Total Quantity</td><td colspan="2">{{ $report->totalQuantity }}</td></tr>
    <tr><td colspan="2">Jumlah Item</td><td colspan="2">{{ $report->totalItems }}</td><td colspan="2">Rata-rata / Item</td><td colspan="2">{{ $report->averageSalesPerItem }}</td></tr>
    <tr><td colspan="8"></td></tr>
    <tr>
        <th>No</th><th>Item Code</th><th>Item Name</th><th>Total Qty</th>
        <th>Total Sales</th><th>Average Price</th><th>Contribution</th><th>Source</th>
    </tr>
    @foreach ($report->rows as $index => $row)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $row->itemCode }}</td>
            <td>{{ $row->itemName }}</td>
            <td>{{ $row->totalQuantity }}</td>
            <td>{{ $row->totalSales }}</td>
            <td>{{ $row->averagePrice }}</td>
            <td>{{ $row->contribution / 100 }}</td>
            <td>{{ $row->source }}</td>
        </tr>
    @endforeach
</table>
