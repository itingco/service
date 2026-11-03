<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BusinessAnalytics\AnalyticsSyncService;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SyncBusinessAnalytics extends Command
{
    protected $signature = 'analytics:sync {--months=36 : Jumlah bulan historis} {--through= : Bulan akhir YYYY-MM} {--source=all : all|INGCO|SMI} {--only-missing : Lewati bulan/source yang sudah pernah sync SUCCESS}';
    protected $description = 'Sinkronisasi data mart Business Analytics dari dbo.fn_PenjualanLini';

    public function handle(AnalyticsSyncService $service): int
    {
        $months = max(1, min(60, (int) $this->option('months')));
        $throughText = trim((string) ($this->option('through') ?: now()->format('Y-m')));
        $through = DateTimeImmutable::createFromFormat('!Y-m', $throughText);
        if ($through === false || $through->format('Y-m') !== $throughText) {
            $this->error('--through harus format YYYY-MM.');
            return self::FAILURE;
        }

        $sourceOption = strtoupper(trim((string) $this->option('source')));
        $sources = match ($sourceOption) {
            'INGCO' => ['INGCO'],
            'SMI' => ['SMI'],
            'ALL' => ['INGCO', 'SMI'],
            default => [],
        };
        if ($sources === []) {
            $this->error('--source harus all, INGCO, atau SMI.');
            return self::FAILURE;
        }

        $start = $through->modify('-'.($months - 1).' months');
        $failures = 0;
        for ($cursor = $start; $cursor <= $through; $cursor = $cursor->modify('+1 month')) {
            foreach ($sources as $source) {
                $period = $cursor->format('Y-m');
                $label = $source.' '.$period;
                if ((bool) $this->option('only-missing') && DB::connection('sqlite_targets')->table('business_analytics_sync_runs')->where('source', $source)->where('period', $period)->where('status', 'SUCCESS')->exists()) {
                    $this->line('SKIP '.$label.' (sudah SUCCESS)');
                    continue;
                }
                $this->line('Sync '.$label.' ...');
                try {
                    $result = $service->syncMonth($source, $cursor);
                    $this->info(sprintf('OK %s: %d source rows -> %d aggregate rows', $label, $result['source_rows'], $result['aggregate_rows']));
                } catch (Throwable $e) {
                    $failures++;
                    $this->error('FAILED '.$label.': '.$e->getMessage());
                }
            }
        }

        if ($failures > 0) {
            $this->warn("Selesai dengan {$failures} kegagalan. Cek business_analytics_sync_runs.");
            return self::FAILURE;
        }

        $this->info('Business Analytics sync selesai.');
        return self::SUCCESS;
    }
}
