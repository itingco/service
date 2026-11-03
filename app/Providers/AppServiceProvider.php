<?php

namespace App\Providers;

use App\Contracts\SalesDataSource;
use App\Contracts\SalesKpiTargetLookup;
use App\Contracts\StockAgingDataSource;
use App\Services\SalesDashboardAggregator;
use App\Services\SalesKpi\SalesKpiCalculator;
use App\Services\SalesKpi\SalesKpiTargetService;
use App\Services\SqlServerSalesDataSource;
use App\Services\SqlServerStockAgingDataSource;
use App\Services\StockAgingAggregator;
use App\Services\StockAgingService;
use App\Support\StockAgingRowNormalizer;
use App\Support\StockAgingSalesRowNormalizer;
use App\Services\Security\DeviceTokenService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            DeviceTokenService::class,
            fn (): DeviceTokenService => new DeviceTokenService((string) config('app.key')),
        );

        $this->app->bind(SalesDataSource::class, SqlServerSalesDataSource::class);
        $this->app->bind(StockAgingDataSource::class, SqlServerStockAgingDataSource::class);

        $this->app->singleton(StockAgingService::class, fn (): StockAgingService => new StockAgingService(
            stockDataSource: $this->app->make(StockAgingDataSource::class),
            salesDataSource: $this->app->make(SalesDataSource::class),
            stockNormalizer: $this->app->make(StockAgingRowNormalizer::class),
            salesNormalizer: $this->app->make(StockAgingSalesRowNormalizer::class),
            aggregator: $this->app->make(StockAgingAggregator::class),
            cacheSeconds: (int) config('stock_aging.cache_seconds', 300),
        ));

        $this->app->singleton(SalesKpiTargetService::class);
        $this->app->alias(SalesKpiTargetService::class, SalesKpiTargetLookup::class);

        $this->app->singleton(
            SalesDashboardAggregator::class,
            fn (): SalesDashboardAggregator => new SalesDashboardAggregator(
                targetLookup: $this->app->make(SalesKpiTargetLookup::class),
                fallbackTargets: (array) config('sales_dashboard.targets', []),
                aliases: (array) config('sales_dashboard.aliases', []),
            ),
        );

        $this->app->singleton(
            SalesKpiCalculator::class,
            fn (): SalesKpiCalculator => new SalesKpiCalculator(
                $this->app->make(SalesKpiTargetLookup::class),
            ),
        );
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
