<?php

declare(strict_types=1);

namespace App\Data\SalesKpi;

final class SalesKpiReport
{
    /**
     * @param list<SalesLineKpi> $rows
     * @param list<SalesKpiTransaction> $detailRows
     * @param array{overall:?float,target:?float,growth:?float,risk:?float,grade:?float} $summary
     * @param array<string,string> $availableLines Normalized line key => display label.
     */
    public function __construct(
        public readonly SalesKpiFilter $filter,
        public readonly SalesKpiPeriod $period,
        public readonly array $rows,
        public readonly array $detailRows,
        public readonly array $summary,
        public readonly array $availableLines = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /** @param array<string,string> $availableLines */
    public static function empty(
        SalesKpiFilter $filter,
        SalesKpiPeriod $period,
        array $availableLines = [],
    ): self {
        return new self(
            filter: $filter,
            period: $period,
            rows: [],
            detailRows: [],
            summary: [
                'overall' => null,
                'target' => null,
                'growth' => null,
                'risk' => null,
                'grade' => null,
            ],
            availableLines: $availableLines,
        );
    }

    /** @return array<string,list<string|float|null>> */
    public function chartData(): array
    {
        return [
            'labels' => array_map(static fn (SalesLineKpi $row): string => $row->line, $this->rows),
            'overall_scores' => array_map(static fn (SalesLineKpi $row): ?float => $row->normalizedScore, $this->rows),
            'target_scores' => array_map(static fn (SalesLineKpi $row): ?float => $row->targetScore, $this->rows),
            'growth_scores' => array_map(static fn (SalesLineKpi $row): ?float => $row->growthScore, $this->rows),
            'risk_scores' => array_map(static fn (SalesLineKpi $row): ?float => $row->riskScore, $this->rows),
            'grade_scores' => array_map(static fn (SalesLineKpi $row): ?float => $row->gradeScore(), $this->rows),
            'targets' => array_map(static fn (SalesLineKpi $row): ?float => $row->target, $this->rows),
            'threshold_70' => array_map(static fn (SalesLineKpi $row): ?float => $row->target === null ? null : $row->target * 0.70, $this->rows),
            'current_omzet' => array_map(static fn (SalesLineKpi $row): float => $row->currentOmzet, $this->rows),
            'target_achievement' => array_map(static fn (SalesLineKpi $row): ?float => $row->targetAchievement(), $this->rows),
            'growth_current' => array_map(static fn (SalesLineKpi $row): float => $row->growthCurrentOmzet, $this->rows),
            'growth_previous' => array_map(static fn (SalesLineKpi $row): float => $row->previousOmzet, $this->rows),
            'discontinued' => array_map(static fn (SalesLineKpi $row): float => $row->discontinuedOmzet, $this->rows),
            'clearance' => array_map(static fn (SalesLineKpi $row): float => $row->clearanceOmzet, $this->rows),
            'risk_targets' => array_map(static fn (SalesLineKpi $row): ?float => $row->riskTarget, $this->rows),
            'grade_a_contribution' => array_map(static fn (SalesLineKpi $row): ?float => $row->gradeAContribution, $this->rows),
            'grade_b_contribution' => array_map(static fn (SalesLineKpi $row): ?float => $row->gradeBContribution, $this->rows),
        ];
    }

    /**
     * @return list<array{
     *   line:string,line_key:string,grade:string,item_code:string,item_name:string,
     *   quantity:float,line_total:float,contribution:float,invoice_date:string,invoice_number:string
     * }>
     */
    public function gradeDetailRows(): array
    {
        $omzetByLine = [];
        foreach ($this->rows as $row) {
            $omzetByLine[$row->lineKey] = $row->currentOmzet;
        }

        $details = [];
        foreach ($this->detailRows as $row) {
            if (!in_array($row->grade, ['A', 'B'], true)) {
                continue;
            }

            $lineOmzet = (float) ($omzetByLine[$row->lineKey] ?? 0.0);
            $details[] = [
                'line' => $row->line,
                'line_key' => $row->lineKey,
                'grade' => $row->grade,
                'item_code' => $row->itemCode,
                'item_name' => $row->itemName,
                'quantity' => $row->quantity,
                'line_total' => $row->lineTotal,
                'contribution' => $lineOmzet !== 0.0 ? $row->lineTotal / $lineOmzet : 0.0,
                'invoice_date' => $row->invoiceDate,
                'invoice_number' => $row->invoiceNumber,
            ];
        }

        return $details;
    }

    public function line(string $lineKey): ?SalesLineKpi
    {
        $lineKey = strtoupper(trim($lineKey));
        foreach ($this->rows as $row) {
            if ($row->lineKey === $lineKey) {
                return $row;
            }
        }

        return null;
    }
}
