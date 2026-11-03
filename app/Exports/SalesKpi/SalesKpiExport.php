<?php

declare(strict_types=1);

namespace App\Exports\SalesKpi;

use App\Data\SalesKpi\SalesKpiReport;
use App\Data\SalesKpi\SalesKpiTransaction;
use App\Data\SalesKpi\SalesLineKpi;
use DateTimeImmutable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

final class SalesKpiExport implements WithMultipleSheets
{
    private const HEADER_ROW = 7;

    public function __construct(private readonly SalesKpiReport $report)
    {
    }

    /** @return list<SalesKpiSheet> */
    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->targetSheet(),
            $this->growthSheet(),
            $this->riskSheet(),
            $this->gradeSheet(),
            $this->detailSheet(),
        ];
    }

    private function summarySheet(): SalesKpiSheet
    {
        $data = array_map(static function (SalesLineKpi $row): array {
            $ingco = $row->source('AS_INGCO');
            $smi = $row->source('AS_SMI');

            return [
                $row->line,
                $row->target,
                $ingco->currentOmzet,
                $smi->currentOmzet,
                $row->currentOmzet,
                $row->targetScore,
                $row->growthScore,
                $row->riskScore,
                $row->gradeScore(),
                $row->normalizedScore,
                $row->status,
            ];
        }, $this->report->rows);

        $data[] = [
            'TOTAL TERTIMBANG',
            $this->sumTargets(),
            $this->sumSource('AS_INGCO', 'currentOmzet'),
            $this->sumSource('AS_SMI', 'currentOmzet'),
            $this->sumCurrentOmzet(),
            $this->report->summary['target'],
            $this->report->summary['growth'],
            $this->report->summary['risk'],
            $this->report->summary['grade'],
            $this->report->summary['overall'],
            $this->overallStatus(),
        ];

        return $this->sheet(
            'Ringkasan KPI',
            ['LiniPenjualan', 'Target Gabungan', 'Omzet AS_INGCO', 'Omzet AS_SMI', 'Omzet Gabungan', 'Skor Target', 'Skor Growth', 'Skor Discontinued/Item Reject', 'Skor Grade A/B', 'Skor Akhir', 'Status'],
            $data,
            ['B'=>'#,##0','C'=>'#,##0','D'=>'#,##0','E'=>'#,##0','F'=>'0.00','G'=>'0.00','H'=>'0.00','I'=>'0.00','J'=>'0.00'],
        );
    }

    private function targetSheet(): SalesKpiSheet
    {
        $data = array_map(static function (SalesLineKpi $row): array {
            $ingco = $row->source('AS_INGCO');
            $smi = $row->source('AS_SMI');
            return [$row->line, $row->target, $ingco->currentOmzet, $smi->currentOmzet, $row->currentOmzet, $row->targetThreshold(), $row->targetAchievement(), $row->gapToThreshold(), $row->gapToTarget(), $row->targetScore];
        }, $this->report->rows);

        return $this->sheet(
            'Target Omzet',
            ['LiniPenjualan','Target Gabungan','Omzet AS_INGCO','Omzet AS_SMI','Omzet Gabungan','Batas 70%','Achievement','Gap ke 70%','Gap ke 100%','Skor / 40'],
            $data,
            ['B'=>'#,##0','C'=>'#,##0','D'=>'#,##0','E'=>'#,##0','F'=>'#,##0','G'=>'0.00%','H'=>'#,##0','I'=>'#,##0','J'=>'0.00'],
        );
    }

    private function growthSheet(): SalesKpiSheet
    {
        $data = array_map(function (SalesLineKpi $row): array {
            $ingco = $row->source('AS_INGCO');
            $smi = $row->source('AS_SMI');
            return [$row->line,$ingco->growthCurrentOmzet,$smi->growthCurrentOmzet,$row->growthCurrentOmzet,$ingco->previousOmzet,$smi->previousOmzet,$row->previousOmzet,$row->growthDifference(),$row->growthRate,0.10,$row->growthScore,$this->componentStatus($row->growthScore,10.0)];
        }, $this->report->rows);

        return $this->sheet(
            'Growth',
            ['LiniPenjualan','Sekarang AS_INGCO','Sekarang AS_SMI','Sekarang Gabungan','Sebelumnya AS_INGCO','Sebelumnya AS_SMI','Sebelumnya Gabungan','Selisih','Growth Gabungan','Target Growth','Skor / 10','Status'],
            $data,
            ['B'=>'#,##0','C'=>'#,##0','D'=>'#,##0','E'=>'#,##0','F'=>'#,##0','G'=>'#,##0','H'=>'#,##0','I'=>'0.00%','J'=>'0.00%','K'=>'0.00'],
        );
    }

    private function riskSheet(): SalesKpiSheet
    {
        $data = array_map(static function (SalesLineKpi $row): array {
            $ingco = $row->source('AS_INGCO');
            $smi = $row->source('AS_SMI');
            return [$row->line,$row->target,$ingco->discontinuedOmzet,$smi->discontinuedOmzet,$ingco->clearanceOmzet,$smi->clearanceOmzet,$row->riskRealization,$row->riskTarget,$row->riskAchievement(),$row->riskScore];
        }, $this->report->rows);

        return $this->sheet(
            'Discontinued Item Reject',
            ['LiniPenjualan','Target Gabungan','Discontinued AS_INGCO','Discontinued AS_SMI','Item Reject AS_INGCO','Item Reject AS_SMI','Total Realisasi KPI','Target 9%','Achievement','Skor / 20'],
            $data,
            ['B'=>'#,##0','C'=>'#,##0','D'=>'#,##0','E'=>'#,##0','F'=>'#,##0','G'=>'#,##0','H'=>'#,##0','I'=>'0.00%','J'=>'0.00'],
        );
    }

    private function gradeSheet(): SalesKpiSheet
    {
        $data = array_map(static function (SalesLineKpi $row): array {
            $ingco = $row->source('AS_INGCO');
            $smi = $row->source('AS_SMI');
            return [$row->line,$row->currentOmzet,$ingco->gradeAOmzet,$smi->gradeAOmzet,$row->gradeAOmzet,$row->gradeAContribution,$row->gradeAScore,$ingco->gradeBOmzet,$smi->gradeBOmzet,$row->gradeBOmzet,$row->gradeBContribution,$row->gradeBScore,$row->gradeScore()];
        }, $this->report->rows);

        return $this->sheet(
            'Grade A B',
            ['LiniPenjualan','Total Omzet Gabungan','Grade A AS_INGCO','Grade A AS_SMI','Grade A Gabungan','Kontribusi A','Skor A / 12','Grade B AS_INGCO','Grade B AS_SMI','Grade B Gabungan','Kontribusi B','Skor B / 8','Total Skor / 20'],
            $data,
            ['B'=>'#,##0','C'=>'#,##0','D'=>'#,##0','E'=>'#,##0','F'=>'0.00%','G'=>'0.00','H'=>'#,##0','I'=>'#,##0','J'=>'#,##0','K'=>'0.00%','L'=>'0.00','M'=>'0.00'],
        );
    }

    private function detailSheet(): SalesKpiSheet
    {
        $data = array_map(static function (SalesKpiTransaction $row): array {
            $discount = trim($row->clearanceDiscount);
            return [$row->database,$row->invoiceDate,$row->invoiceNumber,$row->line,$row->itemCode,$row->itemName,$row->grade === '' ? 'KOSONG' : $row->grade,$row->discontinued ? 'YA' : 'TIDAK',$row->clearance ? 'ITEM REJECT' : 'BUKAN ITEM REJECT',$row->quantity,$row->lineTotal,$row->itemStatus,is_numeric($discount) ? (float) $discount : $discount];
        }, $this->report->detailRows);

        return $this->sheet(
            'Detail Item',
            ['Database','InvoiceDate','InvoiceNumber','LiniPenjualan','ItemCode','ItemName','Grade','Discontinue','Status Item Reject','Quantity','LineTotal','StatusItem','Reject Discount'],
            $data,
            ['J'=>'#,##0.00','K'=>'#,##0','M'=>'0.00%'],
        );
    }

    /** @param list<string> $headings @param list<list<mixed>> $data @param array<string,string> $formats */
    private function sheet(string $title, array $headings, array $data, array $formats): SalesKpiSheet
    {
        return new SalesKpiSheet($title, [...$this->metadataRows(), $headings, ...$data], self::HEADER_ROW, $formats);
    }

    /** @return list<list<string>> */
    private function metadataRows(): array
    {
        $period = $this->report->period;
        return [
            ['Sales KPI Dashboard'],
            ['Mode database: AS_INGCO + AS_SMI'],
            ['Periode KPI: '.$period->currentStart->format('d-m-Y').' s.d. '.$period->currentEnd->format('d-m-Y')],
            ['Periode Growth: '.$period->growthCurrentStart->format('d-m-Y').' s.d. '.$period->growthCurrentEnd->format('d-m-Y').' dibanding '.$period->growthPreviousStart->format('d-m-Y').' s.d. '.$period->growthPreviousEnd->format('d-m-Y')],
            ['Maksimum skor sistem: 90 poin; monitoring harian 10% berada di luar sistem'],
            ['Tanggal ekspor: '.(new DateTimeImmutable())->format('d-m-Y H:i')],
        ];
    }

    private function componentStatus(?float $score, float $maximum): string
    {
        return match (true) { $score === null => 'Tidak dapat dihitung', $score >= $maximum - 0.0001 => 'Tercapai', default => 'Belum Tercapai' };
    }

    private function sumTargets(): ?float
    {
        $targets = array_values(array_filter(array_map(static fn (SalesLineKpi $row): ?float => $row->target, $this->report->rows), static fn (?float $target): bool => $target !== null));
        return $targets === [] ? null : array_sum($targets);
    }

    private function sumCurrentOmzet(): float
    {
        return array_sum(array_map(static fn (SalesLineKpi $row): float => $row->currentOmzet, $this->report->rows));
    }

    private function sumSource(string $database, string $property): ?float
    {
        $values = [];
        foreach ($this->report->rows as $row) {
            $value = $row->source($database)->{$property};
            if ($value !== null) { $values[] = (float) $value; }
        }
        return $values === [] ? null : array_sum($values);
    }

    private function overallStatus(): string
    {
        $score = $this->report->summary['overall'];
        return match (true) { $score === null => 'Tidak dapat dihitung', $score >= 80.0 => 'Baik', $score >= 60.0 => 'Perlu Perhatian', default => 'Kurang' };
    }
}
