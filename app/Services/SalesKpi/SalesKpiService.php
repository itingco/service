<?php

declare(strict_types=1);

namespace App\Services\SalesKpi;

use App\Data\SalesKpi\SalesKpiFilter;
use App\Data\SalesKpi\SalesKpiReport;

final class SalesKpiService
{
    public function __construct(
        private readonly SalesKpiPeriodService $periodService,
        private readonly SalesKpiCombinedDataService $dataService,
        private readonly SalesKpiAggregator $aggregator,
        private readonly SalesKpiCalculator $calculator,
    ) {
    }

    public function generate(SalesKpiFilter $filter): SalesKpiReport
    {
        $period = $this->periodService->make($filter->selectedDate);
        $dataset = $this->dataService->load($filter, $period);
        $aggregates = $this->aggregator->aggregate($dataset);

        return $this->calculator->calculate(
            filter: $filter,
            period: $period,
            aggregates: $aggregates,
            detailRows: $dataset->current,
        );
    }
}
