<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

use App\Data\BusinessAnalytics\BusinessAnalyticsFilter;

final class BusinessAnalyticsService
{
    public function __construct(
        private readonly AnalyticsRepository $repository,
        private readonly ForecastEngine $forecastEngine,
        private readonly TargetService $targets,
        private readonly InsightService $insights,
        private readonly StrategyRecommendationEngine $strategyEngine,
    ) {
    }

    /** @return array<string,mixed> */
    public function build(BusinessAnalyticsFilter $filter): array
    {
        $trend = $this->repository->monthlyTrend($filter, 36);
        $forecastTraining = $this->forecastTrainingSeries($trend, $filter->endMonth);
        $forecast = $this->forecastEngine->forecast($forecastTraining, 3);
        $forecastAccuracy = $this->forecastEngine->backtest($forecastTraining, 6);
        foreach ($forecast as &$row) {
            $target = $this->targets->targetFor($filter, $row['period']);
            $row['official_target'] = $target;
            $row['recommended_target'] = round($row['forecast'] * 1.05, -3);
            $row['status'] = ForecastEngine::targetStatus($row['forecast'], $target);
            $row['gap_to_target'] = $target !== null ? $row['forecast'] - $target : null;
        }
        unset($row);

        $breakdown = $this->repository->breakdown($filter);
        $current = $trend[$filter->endMonth] ?? 0.0;
        $previousMonthKey = date('Y-m', strtotime($filter->endMonth.'-01 -1 month'));
        $previousMonth = $trend[$previousMonthKey] ?? 0.0;
        $priorYearKey = date('Y-m', strtotime($filter->endMonth.'-01 -1 year'));
        $priorYear = $trend[$priorYearKey] ?? 0.0;
        $momGrowth = $previousMonth > 0.0 ? ($current / $previousMonth) - 1.0 : null;
        $yoyGrowth = $priorYear > 0.0 ? ($current / $priorYear) - 1.0 : null;

        $trendValues = array_values($trend);
        $last3 = array_sum(array_slice($trendValues, -3));
        $previous3 = array_sum(array_slice($trendValues, -6, 3));
        $momentum3m = $previous3 > 0.0 ? ($last3 / $previous3) - 1.0 : null;

        $channels = $this->repository->channelSummary($filter);
        $customers = $this->repository->customerMovement($filter);
        $products = $this->repository->productMovement($filter);
        $drivers = $this->repository->lineDrivers($filter);
        $forecastFirst = $forecast[0] ?? null;
        $strategyMetrics = [
            'current_sales' => $current,
            'mom_growth' => $momGrowth,
            'yoy_growth' => $yoyGrowth,
            'momentum_3m' => $momentum3m,
            'online' => $channels['online'] ?? [],
            'offline' => $channels['offline'] ?? [],
            'customer' => $customers['summary'] ?? [],
            'product' => $products['summary'] ?? [],
            'forecast' => [
                'm1' => $forecastFirst['forecast'] ?? null,
                'm1_vs_current' => $current > 0.0 && $forecastFirst ? (($forecastFirst['forecast'] / $current) - 1.0) : null,
                'confidence' => $forecastFirst['confidence'] ?? null,
            ],
        ];
        $strategy = $this->strategyEngine->build($strategyMetrics);

        return [
            'filter' => $filter,
            'trend' => $trend,
            'forecast' => $forecast,
            'forecast_accuracy' => $forecastAccuracy,
            'breakdown' => $breakdown,
            'yearly' => $this->repository->yearlySummary($filter),
            'insights' => $this->insights->build($trend, $breakdown),
            'current_sales' => $current,
            'mom_growth' => $momGrowth,
            'yoy_growth' => $yoyGrowth,
            'momentum_3m' => $momentum3m,
            'latest_period' => $this->repository->latestPeriod(),
            'target_scope' => $this->targets->scope($filter),
            'channels' => $channels,
            'customers' => $customers,
            'products' => $products,
            'line_drivers' => $drivers,
            'online_candidates' => $this->repository->channelItemCandidates($filter, 'ONLINE'),
            'offline_candidates' => $this->repository->channelItemCandidates($filter, 'OFFLINE'),
            'seasonality' => $this->repository->seasonality($trend),
            'anomalies' => $this->repository->anomalies($trend),
            'strategy' => $strategy,
        ];
    }

    /**
     * Empty but view-compatible payload used while the local SQLite analytics schema
     * is not ready yet. This prevents a missing-table condition from turning the
     * whole Business Analytics page into HTTP 500.
     *
     * @return array<string,mixed>
     */
    public function emptyReport(BusinessAnalyticsFilter $filter): array
    {
        return [
            'filter' => $filter,
            'trend' => [],
            'forecast' => [],
            'forecast_accuracy' => [
                'accuracy_score' => null,
                'wape' => null,
                'mape' => null,
                'tested_periods' => 0,
            ],
            'breakdown' => [],
            'yearly' => [],
            'insights' => [],
            'current_sales' => 0.0,
            'mom_growth' => null,
            'yoy_growth' => null,
            'momentum_3m' => null,
            'latest_period' => null,
            'target_scope' => $this->targets->scope($filter),
            'channels' => ['online' => [], 'offline' => []],
            'customers' => ['summary' => [], 'rows' => []],
            'products' => ['summary' => [], 'rows' => []],
            'line_drivers' => [],
            'online_candidates' => [],
            'offline_candidates' => [],
            'seasonality' => [],
            'anomalies' => [],
            'strategy' => [
                'health_score' => 0,
                'health_label' => 'N/A',
                'scores' => [],
                'actions' => [],
                'risks' => [],
                'opportunities' => [],
            ],
        ];
    }

    /** @param array<string,float> $trend @return array<string,float> */
    private function forecastTrainingSeries(array $trend, string $endMonth): array
    {
        if ($trend === [] || $endMonth !== now()->format('Y-m')) {
            return $trend;
        }

        $today = now();
        $daysInMonth = (int) $today->daysInMonth;
        if ((int) $today->day >= $daysInMonth) {
            return $trend;
        }

        $elapsedSellingDays = 0;
        $totalSellingDays = 0;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $today->copy()->day($day);
            if ($date->dayOfWeek === 0) {
                continue;
            }
            $totalSellingDays++;
            if ($day <= (int) $today->day) {
                $elapsedSellingDays++;
            }
        }

        if ($elapsedSellingDays > 0 && isset($trend[$endMonth])) {
            $trend[$endMonth] = $trend[$endMonth] / $elapsedSellingDays * max(1, $totalSellingDays);
        }

        return $trend;
    }
}
