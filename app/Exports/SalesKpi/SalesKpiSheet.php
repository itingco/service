<?php

declare(strict_types=1);

namespace App\Exports\SalesKpi;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class SalesKpiSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle
{
    /**
     * @param list<list<mixed>> $rows
     * @param array<string,string> $formats
     */
    public function __construct(
        private readonly string $sheetTitle,
        private readonly array $rows,
        private readonly int $headerRow,
        private readonly array $formats,
    ) {
    }

    /** @return list<list<mixed>> */
    public function array(): array
    {
        return $this->rows;
    }

    /** @return array<string,string> */
    public function columnFormats(): array
    {
        return $this->formats;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    /** @return array<int,array<string,mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $columnCount = max(1, ...array_map('count', $this->rows));
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $lastRow = max(1, count($this->rows));

        if ($columnCount > 1) {
            $sheet->mergeCells("A1:{$lastColumn}1");
        }

        $sheet->freezePane('B'.($this->headerRow + 1));
        $sheet->setAutoFilter("A{$this->headerRow}:{$lastColumn}{$this->headerRow}");
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()
            ->setTop(0.4)
            ->setRight(0.3)
            ->setBottom(0.4)
            ->setLeft(0.3);

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '101828']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle("A{$this->headerRow}:{$lastColumn}{$this->headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '101828']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $sheet->getStyle("A{$this->headerRow}:{$lastColumn}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D0D5DD'],
                ],
            ],
        ]);

        $sheet->getStyle("A2:{$lastColumn}6")->getFont()->setSize(10);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        return [];
    }
}
