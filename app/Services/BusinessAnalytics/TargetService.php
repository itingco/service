<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

use App\Data\BusinessAnalytics\BusinessAnalyticsFilter;
use App\Models\BusinessAnalyticsTarget;

final class TargetService
{
    public function targetFor(BusinessAnalyticsFilter $filter, string $period): ?float
    {
        [$level, $source, $key] = $this->scope($filter);

        $row = BusinessAnalyticsTarget::query()
            ->where('period', $period)
            ->where('scope_level', $level)
            ->where('source', $source)
            ->where('dimension_key', $key)
            ->first();

        return $row?->official_target === null ? null : (float) $row->official_target;
    }

    public function save(string $period, string $scopeLevel, string $source, string $dimensionKey, ?float $target): void
    {
        BusinessAnalyticsTarget::query()->updateOrCreate(
            [
                'period' => $period,
                'scope_level' => strtoupper($scopeLevel),
                'source' => strtoupper($source),
                'dimension_key' => trim($dimensionKey) === '' ? 'TOTAL' : trim($dimensionKey),
            ],
            ['official_target' => $target !== null && $target > 0.0 ? $target : null],
        );
    }

    /** @return array{0:string,1:string,2:string} */
    public function scope(BusinessAnalyticsFilter $filter): array
    {
        if ($filter->line !== null) {
            return ['LINE', $filter->source ?? ($filter->database === 'all' ? 'ALL' : strtoupper($filter->database)), $filter->line];
        }
        if ($filter->source !== null || $filter->database !== 'all') {
            return ['COMPANY', $filter->source ?? strtoupper($filter->database), 'TOTAL'];
        }
        return ['TOTAL', 'ALL', 'TOTAL'];
    }
}
