<?php

declare(strict_types=1);

namespace App\Exports;

use App\Data\SalesByItemReport;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesByItemExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle
{
    public function __construct(private readonly SalesByItemReport $report)
    {
    }

    public function view(): View
    {
        return view('sales-by-item.export-excel', ['report' => $this->report]);
    }

    public function title(): string
    {
        return 'Sales By Item';
    }

    public function columnFormats(): array
    {
        return [
            'D' => '#,##0.00',
            'E' => '#,##0',
            'F' => '#,##0.00',
            'G' => '0.00%',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A7');
        $lastRow = count($this->report->rows) + 6;
        $sheet->setAutoFilter("A6:H{$lastRow}");

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '17365D']],
                'alignment' => ['horizontal' => 'center'],
            ],
            6 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => 'center'],
            ],
        ];
    }
}
