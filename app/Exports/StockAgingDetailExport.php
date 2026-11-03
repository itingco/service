<?php

declare(strict_types=1);

namespace App\Exports;

use App\Data\StockAgingFilter;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class StockAgingDetailExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle
{
    private const HEADER_ROW = 7;

    /** @param list<array<string,mixed>> $rows */
    public function __construct(
        private readonly StockAgingFilter $filter,
        private readonly array $rows,
        private readonly string $source,
        private readonly int $warehouseId,
        private readonly string $warehouseName,
        private readonly string $warehouseCode,
        private readonly string $bucketLabel,
    ) {
    }

    /** @return list<list<mixed>> */
    public function array(): array
    {
        $data = array_map(static fn (array $row): array => [
            (string) $row['item_code'],
            (string) $row['item_name'],
            (int) $row['age'],
            (float) $row['qty'],
            (float) $row['price'],
            (float) $row['sisa_stock'],
            (float) $row['sales_mtd'],
            (string) ($row['status_label'] ?? 'NORMAL'),
        ], $this->rows);

        return [
            ['DETAIL STOCK AGING PER GUDANG'],
            ['Database', strtoupper($this->source)],
            ['Gudang', $this->warehouseName.($this->warehouseCode !== '' ? ' ('.$this->warehouseCode.')' : '').' · WarehouseID '.$this->warehouseId],
            ['Bucket Aging', $this->bucketLabel],
            ['Per Tanggal', $this->filter->selectedDate->format('d-m-Y').' · Status Item: '.$this->filter->itemStatusLabel()],
            ['Jumlah Baris', count($this->rows)],
            ['Item Code', 'Item Name', 'Age', 'Qty Sisa', 'Price KHUSUS-1', 'Sisa Stok', 'Sales MTD', 'Status'],
            ...$data,
        ];
    }

    public function title(): string
    {
        return 'Detail Stock Aging';
    }

    /** @return array<string,string> */
    public function columnFormats(): array
    {
        return [
            'C' => '0 "hari"',
            'D' => '#,##0.00',
            'E' => '"Rp" #,##0',
            'F' => '"Rp" #,##0',
            'G' => '"Rp" #,##0',
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(self::HEADER_ROW, self::HEADER_ROW + count($this->rows));

        $sheet->mergeCells('A1:H1');
        $sheet->freezePane('A8');
        $sheet->setAutoFilter('A'.self::HEADER_ROW.':H'.$lastRow);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getStyle('A1:H'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A'.self::HEADER_ROW.':H'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '111111']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFC000']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            self::HEADER_ROW => [
                'font' => ['bold' => true, 'color' => ['rgb' => '111111']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFC000']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
