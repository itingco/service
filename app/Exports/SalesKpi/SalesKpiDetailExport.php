<?php

declare(strict_types=1);

namespace App\Exports\SalesKpi;

use App\Data\SalesKpi\SalesKpiReport;
use App\Data\SalesKpi\SalesKpiTransaction;
use DateTimeImmutable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

final class SalesKpiDetailExport implements WithMultipleSheets
{
    /**
     * @param list<SalesKpiTransaction> $rows
     */
    public function __construct(
        private readonly SalesKpiReport $report,
        private readonly string $detailType,
        private readonly array $rows,
        private readonly string $search = '',
    ) {
    }

    /** @return list<SalesKpiSheet> */
    public function sheets(): array
    {
        $rows = array_map(static function (SalesKpiTransaction $row): array {
            $discount = trim($row->clearanceDiscount);
            return [
                $row->database,
                $row->invoiceDate,
                $row->invoiceNumber,
                $row->line,
                $row->itemCode,
                $row->itemName,
                $row->grade === '' ? 'KOSONG' : $row->grade,
                $row->discontinued ? 'YA' : 'TIDAK',
                $row->clearance ? 'ITEM REJECT' : 'BUKAN ITEM REJECT',
                $row->quantity,
                $row->lineTotal,
                $row->itemStatus,
                is_numeric($discount) ? (float) $discount : $discount,
            ];
        }, $this->rows);

        $period = $this->report->period;
        $metadata = [
            ['Detail Sales KPI — '.$this->label()],
            ['Mode database: AS_INGCO + AS_SMI'],
            ['Periode: '.$period->currentStart->format('d-m-Y').' s.d. '.$period->currentEnd->format('d-m-Y')],
            ['Pencarian detail: '.($this->search === '' ? 'Semua' : $this->search)],
            ['Jumlah baris: '.count($rows)],
            ['Tanggal ekspor: '.(new DateTimeImmutable())->format('d-m-Y H:i')],
            ['Database','InvoiceDate','InvoiceNumber','LiniPenjualan','ItemCode','ItemName','Grade','Discontinue','Status Item Reject','Quantity','LineTotal','StatusItem','Reject Discount'],
        ];

        return [new SalesKpiSheet(
            'Detail '.$this->shortLabel(),
            [...$metadata, ...$rows],
            7,
            ['J'=>'#,##0.00','K'=>'#,##0','M'=>'0.00%'],
        )];
    }

    private function label(): string
    {
        return match ($this->detailType) {
            'risk' => 'Discontinued & Item Reject',
            'grade' => 'Grade A & B',
            default => 'Semua Transaksi',
        };
    }

    private function shortLabel(): string
    {
        return match ($this->detailType) {
            'risk' => 'Risk',
            'grade' => 'Grade',
            default => 'Semua',
        };
    }
}
