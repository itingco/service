<?php

declare(strict_types=1);

namespace App\Services\SalesKpi;

use App\Data\SalesKpi\SalesKpiDataset;
use App\Data\SalesKpi\SalesKpiLineAggregate;
use App\Data\SalesKpi\SalesKpiSourceContribution;
use App\Data\SalesKpi\SalesKpiTransaction;

final class SalesKpiAggregator
{
    /** @return array<string,SalesKpiLineAggregate> */
    public function aggregate(SalesKpiDataset $dataset): array
    {
        /** @var array<string,array{line:string,sources:array<string,array<string,float>>}> $buckets */
        $buckets = [];

        foreach ($dataset->current as $row) {
            $source = &$this->sourceBucket($buckets, $row);
            $source['current'] += $row->lineTotal;
            $source['discontinued'] += $row->discontinued ? $row->lineTotal : 0.0;
            $source['clearance'] += $row->clearance ? $row->lineTotal : 0.0;
            $source['grade_a'] += $row->grade === 'A' ? $row->lineTotal : 0.0;
            $source['grade_b'] += $row->grade === 'B' ? $row->lineTotal : 0.0;
            unset($source);
        }

        foreach ($dataset->growthCurrent as $row) {
            $source = &$this->sourceBucket($buckets, $row);
            $source['growth_current'] += $row->lineTotal;
            unset($source);
        }

        foreach ($dataset->growthPrevious as $row) {
            $source = &$this->sourceBucket($buckets, $row);
            $source['previous'] += $row->lineTotal;
            unset($source);
        }

        ksort($buckets, SORT_NATURAL | SORT_FLAG_CASE);
        $result = [];
        foreach ($buckets as $lineKey => $bucket) {
            $sources = [];
            foreach ($bucket['sources'] as $database => $values) {
                $sources[$database] = new SalesKpiSourceContribution(
                    database: $database,
                    target: null,
                    targetConfigured: false,
                    currentOmzet: $values['current'],
                    growthCurrentOmzet: $values['growth_current'],
                    previousOmzet: $values['previous'],
                    discontinuedOmzet: $values['discontinued'],
                    clearanceOmzet: $values['clearance'],
                    gradeAOmzet: $values['grade_a'],
                    gradeBOmzet: $values['grade_b'],
                );
            }

            $result[$lineKey] = new SalesKpiLineAggregate(
                line: $bucket['line'] !== '' ? $bucket['line'] : $lineKey,
                lineKey: $lineKey,
                sources: $sources,
            );
        }

        return $result;
    }

    /**
     * @param array<string,array{line:string,sources:array<string,array<string,float>>}> $buckets
     * @return array<string,float>
     */
    private function &sourceBucket(array &$buckets, SalesKpiTransaction $row): array
    {
        if (!isset($buckets[$row->lineKey])) {
            $buckets[$row->lineKey] = [
                'line' => $row->line !== '' ? $row->line : $row->lineKey,
                'sources' => [],
            ];
        } elseif ($buckets[$row->lineKey]['line'] === '' && $row->line !== '') {
            $buckets[$row->lineKey]['line'] = $row->line;
        }

        if (!isset($buckets[$row->lineKey]['sources'][$row->database])) {
            $buckets[$row->lineKey]['sources'][$row->database] = [
                'current' => 0.0,
                'growth_current' => 0.0,
                'previous' => 0.0,
                'discontinued' => 0.0,
                'clearance' => 0.0,
                'grade_a' => 0.0,
                'grade_b' => 0.0,
            ];
        }

        return $buckets[$row->lineKey]['sources'][$row->database];
    }
}
