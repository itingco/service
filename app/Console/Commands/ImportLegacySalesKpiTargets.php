<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SalesKpiTarget;
use App\Services\SalesKpi\SalesKpiTargetService;
use Illuminate\Console\Command;

final class ImportLegacySalesKpiTargets extends Command
{
    protected $signature = 'sales-kpi:import-legacy-targets {--force : Replace existing rows with imported totals}';

    protected $description = 'Import and combine legacy per-database KPI targets into SQLite.';

    public function handle(SalesKpiTargetService $service): int
    {
        $combined = [];

        foreach ((array) config('sales_kpi_targets', []) as $lines) {
            foreach ((array) $lines as $line => $target) {
                $key = $service->normalizeLine((string) $line);
                $numeric = is_numeric($target) ? (float) $target : 0.0;

                if ($numeric > 0.0) {
                    $combined[$key] = ($combined[$key] ?? 0.0) + $numeric;
                }
            }
        }

        if (SalesKpiTarget::query()->exists() && !$this->option('force')) {
            $this->components->warn('Import dilewati karena tabel target sudah berisi data.');

            return self::SUCCESS;
        }

        if ($this->option('force')) {
            SalesKpiTarget::query()->delete();
        }

        $service->saveBatch($combined);
        $this->components->info(count($combined).' target gabungan berhasil diimpor.');

        return self::SUCCESS;
    }
}
