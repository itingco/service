<?php

declare(strict_types=1);

namespace App\Services\SalesKpi;

use App\Contracts\SalesKpiTargetLookup;
use App\Data\SalesKpi\SalesKpiFilter;
use App\Data\SalesKpi\SalesKpiLineAggregate;
use App\Data\SalesKpi\SalesKpiPeriod;
use App\Data\SalesKpi\SalesKpiReport;
use App\Data\SalesKpi\SalesKpiSourceContribution;
use App\Data\SalesKpi\SalesKpiTransaction;
use App\Data\SalesKpi\SalesLineKpi;

final class SalesKpiCalculator
{
    public function __construct(private readonly SalesKpiTargetLookup $targetService)
    {
    }

    /**
     * @param array<string,SalesKpiLineAggregate> $aggregates
     * @param list<SalesKpiTransaction> $detailRows
     */
    public function calculate(
        SalesKpiFilter $filter,
        SalesKpiPeriod $period,
        array $aggregates,
        array $detailRows,
    ): SalesKpiReport {
        $availableLines = [];
        foreach ($aggregates as $lineKey => $aggregate) {
            $availableLines[$lineKey] = $aggregate->line;
        }

        $selectedLineKey = $filter->lineKey();
        if ($selectedLineKey !== null) {
            $aggregates = isset($aggregates[$selectedLineKey])
                ? [$selectedLineKey => $aggregates[$selectedLineKey]]
                : [];
        }

        if ($aggregates === []) {
            return SalesKpiReport::empty($filter, $period, $availableLines);
        }

        $rows = [];
        foreach ($aggregates as $aggregate) {
            $target = $this->targetService->targetForLine($aggregate->lineKey);
            $targetConfigured = $target !== null;

            $sources = [];
            foreach ($filter->databaseKeys() as $databaseKey) {
                $raw = $aggregate->source($databaseKey);
                $sources[$databaseKey] = new SalesKpiSourceContribution(
                    database: $databaseKey,
                    target: null,
                    targetConfigured: false,
                    currentOmzet: $raw->currentOmzet,
                    growthCurrentOmzet: $raw->growthCurrentOmzet,
                    previousOmzet: $raw->previousOmzet,
                    discontinuedOmzet: $raw->discontinuedOmzet,
                    clearanceOmzet: $raw->clearanceOmzet,
                    gradeAOmzet: $raw->gradeAOmzet,
                    gradeBOmzet: $raw->gradeBOmzet,
                );
            }

            $currentOmzet = $aggregate->currentOmzet();
            $growthCurrentOmzet = $aggregate->growthCurrentOmzet();
            $previousOmzet = $aggregate->previousOmzet();
            $discontinuedOmzet = $aggregate->discontinuedOmzet();
            $clearanceOmzet = $aggregate->clearanceOmzet();
            $gradeAOmzet = $aggregate->gradeAOmzet();
            $gradeBOmzet = $aggregate->gradeBOmzet();

            $targetScore = $this->targetScore($currentOmzet, $target);
            $growth = $this->growthScore($growthCurrentOmzet, $previousOmzet);
            $riskRealization = $discontinuedOmzet + $clearanceOmzet;
            $risk = $this->riskScore($riskRealization, $target);
            $grades = $this->gradeScores($currentOmzet, $gradeAOmzet, $gradeBOmzet);

            $earnedPoints = array_sum(array_filter([
                $targetScore,
                $growth['score'],
                $risk['score'],
                $grades['a_score'],
                $grades['b_score'],
            ], static fn (?float $value): bool => $value !== null));

            $availableMaximum =
                ($targetScore !== null ? 40.0 : 0.0) +
                ($growth['score'] !== null ? 10.0 : 0.0) +
                ($risk['score'] !== null ? 20.0 : 0.0) +
                ($grades['a_score'] !== null ? 12.0 : 0.0) +
                ($grades['b_score'] !== null ? 8.0 : 0.0);

            $normalizedScore = $this->normalizeScore($earnedPoints, $availableMaximum);

            $rows[] = new SalesLineKpi(
                line: $aggregate->line,
                lineKey: $aggregate->lineKey,
                sources: $sources,
                targetConfigured: $targetConfigured,
                target: $target,
                currentOmzet: $currentOmzet,
                growthCurrentOmzet: $growthCurrentOmzet,
                previousOmzet: $previousOmzet,
                growthRate: $growth['rate'],
                discontinuedOmzet: $discontinuedOmzet,
                clearanceOmzet: $clearanceOmzet,
                riskRealization: $riskRealization,
                riskTarget: $risk['target'],
                gradeAOmzet: $gradeAOmzet,
                gradeBOmzet: $gradeBOmzet,
                gradeAContribution: $grades['a_contribution'],
                gradeBContribution: $grades['b_contribution'],
                targetScore: $targetScore,
                growthScore: $growth['score'],
                riskScore: $risk['score'],
                gradeAScore: $grades['a_score'],
                gradeBScore: $grades['b_score'],
                earnedPoints: $earnedPoints,
                availableMaximum: $availableMaximum,
                normalizedScore: $normalizedScore,
                status: $this->status($normalizedScore),
            );
        }

        usort($rows, static fn (SalesLineKpi $a, SalesLineKpi $b): int => strcasecmp($a->line, $b->line));

        $summary = [
            'overall' => $this->weightedScore($rows, static fn (SalesLineKpi $row): ?float => $row->normalizedScore),
            'target' => $this->weightedScore($rows, static fn (SalesLineKpi $row): ?float => $row->targetScore),
            'growth' => $this->weightedScore($rows, static fn (SalesLineKpi $row): ?float => $row->growthScore),
            'risk' => $this->weightedScore($rows, static fn (SalesLineKpi $row): ?float => $row->riskScore),
            'grade' => $this->weightedScore($rows, static fn (SalesLineKpi $row): ?float => $row->gradeScore()),
        ];

        return new SalesKpiReport(
            filter: $filter,
            period: $period,
            rows: $rows,
            detailRows: $this->filterDetails($detailRows, $filter),
            summary: $summary,
            availableLines: $availableLines,
        );
    }

    private function clamp(float $value, float $minimum, float $maximum): float
    {
        return max($minimum, min($maximum, $value));
    }

    private function targetScore(float $omzet, ?float $target): ?float
    {
        if ($target === null || $target <= 0.0) {
            return null;
        }

        return $this->clamp(($omzet / $target) / 0.70 * 40.0, 0.0, 40.0);
    }

    /** @return array{rate:?float,score:?float} */
    private function growthScore(float $current, float $previous): array
    {
        if ($previous == 0.0) {
            return ['rate' => null, 'score' => null];
        }

        $rate = ($current - $previous) / $previous;
        $score = $this->clamp(max($rate, 0.0) / 0.10 * 10.0, 0.0, 10.0);

        return ['rate' => $rate, 'score' => $score];
    }

    /** @return array{target:?float,score:?float} */
    private function riskScore(float $realization, ?float $target): array
    {
        if ($target === null || $target <= 0.0) {
            return ['target' => null, 'score' => null];
        }

        $riskTarget = $target * 0.09;

        return [
            'target' => $riskTarget,
            'score' => $this->clamp($realization / $riskTarget * 20.0, 0.0, 20.0),
        ];
    }

    /** @return array{a_contribution:?float,b_contribution:?float,a_score:?float,b_score:?float} */
    private function gradeScores(float $total, float $gradeA, float $gradeB): array
    {
        if ($total == 0.0) {
            return [
                'a_contribution' => null,
                'b_contribution' => null,
                'a_score' => null,
                'b_score' => null,
            ];
        }

        $aContribution = $gradeA / $total;
        $bContribution = $gradeB / $total;

        return [
            'a_contribution' => $aContribution,
            'b_contribution' => $bContribution,
            'a_score' => $this->clamp($aContribution / 0.35 * 12.0, 0.0, 12.0),
            'b_score' => $this->clamp($bContribution / 0.20 * 8.0, 0.0, 8.0),
        ];
    }

    private function normalizeScore(float $earned, float $availableMaximum): ?float
    {
        if ($availableMaximum <= 0.0) {
            return null;
        }

        return $this->clamp($earned / $availableMaximum * 90.0, 0.0, 90.0);
    }

    private function status(?float $normalizedScore): string
    {
        return match (true) {
            $normalizedScore === null => 'Tidak dapat dihitung',
            $normalizedScore >= 80.0 => 'Baik',
            $normalizedScore >= 60.0 => 'Perlu Perhatian',
            default => 'Kurang',
        };
    }

    /** @param list<SalesLineKpi> $rows */
    private function weightedScore(array $rows, callable $score): ?float
    {
        $weighted = 0.0;
        $weight = 0.0;

        foreach ($rows as $row) {
            if (!$row->hasTarget()) {
                continue;
            }

            $value = $score($row);
            if ($value === null) {
                continue;
            }

            $weighted += $value * $row->target;
            $weight += $row->target;
        }

        return $weight > 0.0 ? $weighted / $weight : null;
    }

    /**
     * @param list<SalesKpiTransaction> $rows
     * @return list<SalesKpiTransaction>
     */
    private function filterDetails(array $rows, SalesKpiFilter $filter): array
    {
        $lineKey = $filter->lineKey();
        $search = strtoupper($filter->search);

        $filtered = array_values(array_filter(
            $rows,
            static function (SalesKpiTransaction $row) use ($filter, $lineKey, $search): bool {
                if ($lineKey !== null && $row->lineKey !== $lineKey) {
                    return false;
                }
                if ($filter->discontinue === 'yes' && !$row->discontinued) {
                    return false;
                }
                if ($filter->discontinue === 'no' && $row->discontinued) {
                    return false;
                }
                if ($filter->clearance === 'yes' && !$row->clearance) {
                    return false;
                }
                if ($filter->clearance === 'no' && $row->clearance) {
                    return false;
                }
                if ($filter->grade === 'BLANK' && $row->grade !== '') {
                    return false;
                }
                if (!in_array($filter->grade, ['ALL', 'BLANK'], true) && $row->grade !== $filter->grade) {
                    return false;
                }
                if ($search !== '') {
                    $haystack = strtoupper(implode(' ', [
                        $row->database,
                        $row->invoiceNumber,
                        $row->line,
                        $row->itemCode,
                        $row->itemName,
                    ]));
                    if (!str_contains($haystack, $search)) {
                        return false;
                    }
                }

                return true;
            },
        ));

        usort($filtered, static function (SalesKpiTransaction $a, SalesKpiTransaction $b): int {
            $date = strcmp($b->invoiceDate, $a->invoiceDate);
            if ($date !== 0) {
                return $date;
            }
            $database = strcmp($a->database, $b->database);
            if ($database !== 0) {
                return $database;
            }
            $invoice = strcmp($a->invoiceNumber, $b->invoiceNumber);
            if ($invoice !== 0) {
                return $invoice;
            }

            return strcmp($a->itemCode, $b->itemCode);
        });

        return $filtered;
    }
}
