<?php

declare(strict_types=1);

namespace App\Services\SalesKpi;

use App\Data\SalesKpi\SalesKpiDataset;
use App\Data\SalesKpi\SalesKpiFilter;
use App\Data\SalesKpi\SalesKpiPeriod;
use App\Exceptions\SalesKpiDataException;

final class SalesKpiCombinedDataService
{
    public function __construct(private readonly SalesKpiDataService $singleSource)
    {
    }

    public function load(SalesKpiFilter $filter, SalesKpiPeriod $period): SalesKpiDataset
    {
        $datasets = [];
        $failed = [];
        $exceptions = [];

        foreach ($filter->sources() as $source) {
            try {
                $datasets[] = $this->singleSource->loadSource($source, $period);
            } catch (SalesKpiDataException $exception) {
                $failed = array_merge($failed, $exception->failedSources());
                $exceptions[] = $exception;
            }
        }

        if ($failed !== []) {
            if (count($exceptions) === 1) {
                throw $exceptions[0];
            }

            throw SalesKpiDataException::forSources(
                $failed,
                'Satu atau lebih database KPI gagal dimuat.',
            );
        }

        return SalesKpiDataset::merge($datasets);
    }
}
