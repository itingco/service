<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\SalesDashboardInsights;
use App\Data\SalesDashboardLineRow;
use App\Data\SalesDashboardReport;
use DateInterval;
use DatePeriod;

final class SalesDashboardInsightService
{
    public function build(SalesDashboardReport $report): SalesDashboardInsights
    {
        $linePaces = array_map(
            fn (SalesDashboardLineRow $row): array => $this->paceRow($row),
            $report->rows,
        );

        $onTrackCount = count(array_filter(
            $linePaces,
            static fn (array $row): bool => in_array(
                $row['pace_status'],
                ['TARGET TERCAPAI', 'AHEAD', 'ON TRACK'],
                true,
            ),
        ));
        $atRiskCount = count(array_filter(
            $linePaces,
            static fn (array $row): bool => $row['pace_status'] === 'BEHIND',
        ));
        $targetMissingCount = count(array_filter(
            $linePaces,
            static fn (array $row): bool => $row['pace_status'] === 'TARGET BELUM DIATUR',
        ));

        $totalDetailSales = array_sum(array_map(
            static fn (array $row): float => self::sales($row),
            $report->detailRows,
        ));
        $topCustomers = $this->rankDetails(
            $report->detailRows,
            fn (array $row): string => $this->customerKey($row),
            fn (array $row): string => $this->customerLabel($row),
            $totalDetailSales,
        );

        return new SalesDashboardInsights(
            totalTarget: $report->totalRow->monthlyTarget,
            totalMtd: $report->totalRow->mtd,
            remainingTarget: max(0.0, $report->totalRow->gap),
            forecastEom: $report->totalRow->estimatedEom,
            forecastPercentage: $report->totalRow->estimatedEomPercentage,
            onTrackCount: $onTrackCount,
            atRiskCount: $atRiskCount,
            targetMissingCount: $targetMissingCount,
            linePaces: $linePaces,
            actions: $this->actions($report->rows),
            topAchievement: $this->lineRanking($report->rows, 'achievementPercentage', true),
            bottomAchievement: $this->lineRanking($report->rows, 'achievementPercentage', false),
            topGrowth: $this->lineRanking($report->rows, 'mtdGrowth', true),
            bottomGrowth: $this->lineRanking($report->rows, 'mtdGrowth', false),
            topCustomers: array_slice($topCustomers, 0, 10),
            topSalespersons: array_slice($this->rankDetails(
                $report->detailRows,
                fn (array $row): string => $this->text($row['sales_person_name'] ?? null, 'SALES TANPA NAMA'),
                fn (array $row): string => $this->text($row['sales_person_name'] ?? null, 'Sales tanpa nama'),
                $totalDetailSales,
            ), 0, 10),
            topItems: array_slice($this->rankDetails(
                $report->detailRows,
                fn (array $row): string => $this->itemKey($row),
                fn (array $row): string => $this->itemLabel($row),
                $totalDetailSales,
            ), 0, 10),
            topFiveCustomerConcentration: $totalDetailSales > 0.0
                ? array_sum(array_column(array_slice($topCustomers, 0, 5), 'sales')) / $totalDetailSales
                : null,
            gradeMix: $this->gradeMix($report->detailRows, $totalDetailSales),
            statusMix: $this->statusMix($report->detailRows, $totalDetailSales),
            dailyTrend: $this->dailyTrend($report),
        );
    }

    /** @return array<string,mixed> */
    private function paceRow(SalesDashboardLineRow $row): array
    {
        $status = $this->paceStatus($row);

        return [
            'line' => $row->line,
            'monthly_target' => $row->monthlyTarget,
            'mtd' => $row->mtd,
            'remaining_target' => max(0.0, $row->gap),
            'daily_sales_target_per_hk' => $row->dailyTarget,
            'required_daily_sales' => $row->requiredDailySales,
            'average_daily_sales' => $row->averageDailySales,
            'estimated_eom' => $row->estimatedEom,
            'forecast_gap' => max(0.0, $row->monthlyTarget - $row->estimatedEom),
            'estimated_eom_percentage' => $row->estimatedEomPercentage,
            'achievement_percentage' => $row->achievementPercentage,
            'mtd_growth' => $row->mtdGrowth,
            'pace_status' => $status,
            'pace_class' => match ($status) {
                'TARGET TERCAPAI', 'AHEAD' => 'success',
                'ON TRACK' => 'warning',
                'BEHIND' => 'danger',
                default => 'secondary',
            },
        ];
    }

    private function paceStatus(SalesDashboardLineRow $row): string
    {
        if ($row->monthlyTarget <= 0.0) {
            return 'TARGET BELUM DIATUR';
        }
        if (($row->achievementPercentage ?? 0.0) >= 1.0) {
            return 'TARGET TERCAPAI';
        }
        if (($row->estimatedEomPercentage ?? 0.0) >= 1.10) {
            return 'AHEAD';
        }
        if (($row->estimatedEomPercentage ?? 0.0) >= 0.90) {
            return 'ON TRACK';
        }

        return 'BEHIND';
    }

    /**
     * @param list<SalesDashboardLineRow> $rows
     * @return list<array<string,mixed>>
     */
    private function actions(array $rows): array
    {
        $actions = [];

        foreach ($rows as $row) {
            $issues = [];
            $priority = 0;

            if ($row->monthlyTarget <= 0.0) {
                $issues[] = 'Target bulanan belum diatur';
                $priority = 3;
            } else {
                if (($row->achievementPercentage ?? 0.0) < 0.70) {
                    $issues[] = sprintf(
                        'Achievement baru %.1f%%',
                        ($row->achievementPercentage ?? 0.0) * 100,
                    );
                    $priority = max($priority, 3);
                }
                if (($row->estimatedEomPercentage ?? 0.0) < 0.90) {
                    $issues[] = sprintf(
                        'Forecast akhir bulan %.1f%% dari target',
                        ($row->estimatedEomPercentage ?? 0.0) * 100,
                    );
                    $priority = max($priority, 3);
                }
                if ($row->mtdGrowth !== null && $row->mtdGrowth < 0.0) {
                    $issues[] = sprintf('Growth MTD turun %.1f%%', abs($row->mtdGrowth) * 100);
                    $priority = max($priority, 2);
                }
                if ($row->gap > 0.0 && $row->requiredDailySales > $row->averageDailySales) {
                    $issues[] = $row->averageDailySales > 0.0
                        ? sprintf(
                            'Kebutuhan harian %.1fx rata-rata aktual',
                            $row->requiredDailySales / $row->averageDailySales,
                        )
                        : 'Belum ada rata-rata penjualan harian yang memadai';
                    $priority = max($priority, $row->averageDailySales > 0.0 ? 2 : 3);
                }
            }

            if ($issues === []) {
                continue;
            }

            $actions[] = [
                'priority' => match ($priority) {
                    3 => 'TINGGI',
                    2 => 'SEDANG',
                    default => 'RENDAH',
                },
                'priority_rank' => $priority,
                'line' => $row->line,
                'issue' => implode(' · ', $issues),
                'impact' => max(0.0, $row->monthlyTarget - $row->estimatedEom),
                'suggestion' => $this->suggestion($row),
            ];
        }

        usort($actions, static function (array $left, array $right): int {
            return [$right['priority_rank'], $right['impact']] <=> [$left['priority_rank'], $left['impact']];
        });

        return array_map(static function (array $action): array {
            unset($action['priority_rank']);
            return $action;
        }, $actions);
    }

    private function suggestion(SalesDashboardLineRow $row): string
    {
        if ($row->monthlyTarget <= 0.0) {
            return 'Atur target bulanan agar achievement, gap, dan forecast dapat dinilai.';
        }
        if (($row->estimatedEomPercentage ?? 0.0) < 0.90 && ($row->mtdGrowth ?? 0.0) < 0.0) {
            return 'Review customer dan item penyumbang penurunan, lalu susun recovery plan harian.';
        }
        if (($row->estimatedEomPercentage ?? 0.0) < 0.90) {
            return 'Prioritaskan pipeline dan customer terbesar untuk menutup kebutuhan penjualan harian.';
        }
        if (($row->mtdGrowth ?? 0.0) < 0.0) {
            return 'Telusuri customer atau item yang menurun dibanding periode yang sama bulan lalu.';
        }

        return 'Pantau realisasi harian agar pace tetap sesuai target akhir bulan.';
    }

    /**
     * @param list<SalesDashboardLineRow> $rows
     * @return list<array<string,mixed>>
     */
    private function lineRanking(array $rows, string $property, bool $descending): array
    {
        $ranked = [];
        foreach ($rows as $row) {
            $value = $row->{$property};
            if ($value === null || ($property === 'achievementPercentage' && $row->monthlyTarget <= 0.0)) {
                continue;
            }
            $ranked[] = [
                'line' => $row->line,
                'value' => (float) $value,
                'sales' => $row->mtd,
            ];
        }

        usort($ranked, static fn (array $left, array $right): int => $descending
            ? $right['value'] <=> $left['value']
            : $left['value'] <=> $right['value']);

        return array_slice($ranked, 0, 5);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param callable(array<string,mixed>):string $keyResolver
     * @param callable(array<string,mixed>):string $labelResolver
     * @return list<array{label:string,sales:float,percentage:?float}>
     */
    private function rankDetails(
        array $rows,
        callable $keyResolver,
        callable $labelResolver,
        float $totalSales,
    ): array {
        $groups = [];
        foreach ($rows as $row) {
            $key = $keyResolver($row);
            $groups[$key] ??= ['label' => $labelResolver($row), 'sales' => 0.0];
            $groups[$key]['sales'] += self::sales($row);
        }

        $ranked = array_values(array_map(
            static fn (array $group): array => [
                'label' => $group['label'],
                'sales' => (float) $group['sales'],
                'percentage' => $totalSales > 0.0 ? (float) $group['sales'] / $totalSales : null,
            ],
            $groups,
        ));
        usort($ranked, static fn (array $left, array $right): int => $right['sales'] <=> $left['sales']);

        return $ranked;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array{label:string,sales:float,percentage:?float}>
     */
    private function gradeMix(array $rows, float $totalSales): array
    {
        $amounts = ['A' => 0.0, 'B' => 0.0, 'OTHER' => 0.0];
        foreach ($rows as $row) {
            $grade = strtoupper(trim((string) ($row['grade'] ?? '')));
            $bucket = in_array($grade, ['A', 'B'], true) ? $grade : 'OTHER';
            $amounts[$bucket] += self::sales($row);
        }

        return [
            'A' => $this->mixRow('Grade A', $amounts['A'], $totalSales),
            'B' => $this->mixRow('Grade B', $amounts['B'], $totalSales),
            'OTHER' => $this->mixRow('Grade Lainnya', $amounts['OTHER'], $totalSales),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array{label:string,sales:float,percentage:?float}>
     */
    private function statusMix(array $rows, float $totalSales): array
    {
        $amounts = ['normal' => 0.0, 'discontinued' => 0.0, 'clearance' => 0.0, 'both' => 0.0];
        foreach ($rows as $row) {
            $discontinued = (bool) ($row['discontinued'] ?? false);
            $clearance = (bool) ($row['clearance'] ?? false);
            $bucket = $discontinued && $clearance
                ? 'both'
                : ($discontinued ? 'discontinued' : ($clearance ? 'clearance' : 'normal'));
            $amounts[$bucket] += self::sales($row);
        }

        return [
            'normal' => $this->mixRow('Item Normal', $amounts['normal'], $totalSales),
            'discontinued' => $this->mixRow('Discontinued', $amounts['discontinued'], $totalSales),
            'clearance' => $this->mixRow('Item Reject', $amounts['clearance'], $totalSales),
            'both' => $this->mixRow('Discontinued & Item Reject', $amounts['both'], $totalSales),
        ];
    }

    /** @return array{label:string,sales:float,percentage:?float} */
    private function mixRow(string $label, float $sales, float $totalSales): array
    {
        return [
            'label' => $label,
            'sales' => $sales,
            'percentage' => $totalSales > 0.0 ? $sales / $totalSales : null,
        ];
    }

    /**
     * @return array{labels:list<string>,actual_daily:list<float>,actual_cumulative:list<float>,target_cumulative:list<float>}
     */
    private function dailyTrend(SalesDashboardReport $report): array
    {
        $salesByDate = [];
        foreach ($report->detailRows as $row) {
            $date = substr(trim((string) ($row['invoice_date'] ?? '')), 0, 10);
            if ($date !== '') {
                $salesByDate[$date] = ($salesByDate[$date] ?? 0.0) + self::sales($row);
            }
        }

        $labels = [];
        $daily = [];
        $actualCumulative = [];
        $targetCumulative = [];
        $runningActual = 0.0;
        $runningTarget = 0.0;
        $targetPerSalesDay = $report->filter->totalSalesDays > 0
            ? $report->totalRow->monthlyTarget / $report->filter->totalSalesDays
            : 0.0;

        $period = new DatePeriod(
            $report->filter->currentStartDate,
            new DateInterval('P1D'),
            $report->filter->currentEndDate->modify('+1 day'),
        );
        foreach ($period as $date) {
            $dateKey = $date->format('Y-m-d');
            $amount = (float) ($salesByDate[$dateKey] ?? 0.0);
            $runningActual += $amount;
            if ((int) $date->format('w') !== 0) {
                $runningTarget += $targetPerSalesDay;
            }

            $labels[] = $date->format('d M');
            $daily[] = $amount;
            $actualCumulative[] = $runningActual;
            $targetCumulative[] = $runningTarget;
        }

        return [
            'labels' => $labels,
            'actual_daily' => $daily,
            'actual_cumulative' => $actualCumulative,
            'target_cumulative' => $targetCumulative,
        ];
    }

    /** @param array<string,mixed> $row */
    private function customerKey(array $row): string
    {
        $code = strtoupper(trim((string) ($row['customer_code'] ?? '')));
        $name = strtoupper(trim((string) ($row['customer_name'] ?? '')));
        return $code !== '' ? $code : ($name !== '' ? $name : 'CUSTOMER TANPA NAMA');
    }

    /** @param array<string,mixed> $row */
    private function customerLabel(array $row): string
    {
        $name = trim((string) ($row['customer_name'] ?? ''));
        $code = trim((string) ($row['customer_code'] ?? ''));
        return $name !== '' ? $name : ($code !== '' ? $code : 'Customer tanpa nama');
    }

    /** @param array<string,mixed> $row */
    private function itemKey(array $row): string
    {
        $code = strtoupper(trim((string) ($row['item_code'] ?? '')));
        $name = strtoupper(trim((string) ($row['item_name'] ?? '')));
        return $code !== '' ? $code : ($name !== '' ? $name : 'ITEM TANPA NAMA');
    }

    /** @param array<string,mixed> $row */
    private function itemLabel(array $row): string
    {
        $code = trim((string) ($row['item_code'] ?? ''));
        $name = trim((string) ($row['item_name'] ?? ''));
        if ($code !== '' && $name !== '') {
            return $code.' — '.$name;
        }
        return $code !== '' ? $code : ($name !== '' ? $name : 'Item tanpa nama');
    }

    private function text(mixed $value, string $fallback): string
    {
        $text = trim((string) $value);
        return $text !== '' ? $text : $fallback;
    }

    /** @param array<string,mixed> $row */
    private static function sales(array $row): float
    {
        return (float) ($row['line_total'] ?? $row['sales'] ?? 0.0);
    }
}
