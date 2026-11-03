<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BusinessAnalytics\AnalyticsSyncService;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Throwable;

final class SyncBusinessAnalyticsDaily extends Command
{
    protected $signature = 'analytics:sync-daily {--days=180 : Rolling days to rebuild} {--through= : End date YYYY-MM-DD} {--source=all : all|INGCO|SMI}';
    protected $description = 'Build/rebuild rolling daily Business Analytics mart for experiment measurement';

    public function handle(AnalyticsSyncService $service): int
    {
        $days = max(1, min(366, (int) $this->option('days')));
        $throughText = trim((string) ($this->option('through') ?: now()->format('Y-m-d')));
        $through = DateTimeImmutable::createFromFormat('!Y-m-d', $throughText);
        if ($through === false || $through->format('Y-m-d') !== $throughText) {
            $this->error('--through harus format YYYY-MM-DD.');
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

        $start = $through->modify('-'.($days - 1).' days');
        $cursor = $start->modify('first day of this month');
        $endMonth = $through->modify('first day of this month');
        $failures = 0;

        for (; $cursor <= $endMonth; $cursor = $cursor->modify('+1 month')) {
            foreach ($sources as $source) {
                $this->line('Daily sync '.$source.' '.$cursor->format('Y-m').' ...');
                try {
                    $result = $service->syncDailyMonth($source, $cursor, $start, $through);
                    $this->info(sprintf('OK %s %s: %d source rows -> %d daily aggregate rows', $source, $cursor->format('Y-m'), $result['source_rows'], $result['aggregate_rows']));
                } catch (Throwable $e) {
                    $failures++;
                    $this->error('FAILED '.$source.' '.$cursor->format('Y-m').': '.$e->getMessage());
                }
            }
        }

        if ($failures > 0) {
            $this->warn("Daily sync selesai dengan {$failures} kegagalan.");
            return self::FAILURE;
        }

        $this->info('Business Analytics daily mart selesai.');
        return self::SUCCESS;
    }
}
