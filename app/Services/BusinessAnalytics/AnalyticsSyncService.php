<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

use App\Contracts\SalesDataSource;
use App\Support\SalesDashboardRowNormalizer;
use App\Support\SalesLineName;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class AnalyticsSyncService
{
    public function __construct(
        private readonly SalesDataSource $dataSource,
        private readonly SalesDashboardRowNormalizer $normalizer,
    ) {
    }

    /** @return array{source_rows:int,aggregate_rows:int} */
    public function syncMonth(string $source, DateTimeImmutable $month): array
    {
        $source = strtoupper(trim($source));
        $start = $month->modify('first day of this month');
        $end = $month->modify('last day of this month');
        $period = $month->format('Y-m');
        $startedAt = now();
        $runId = DB::connection('sqlite_targets')->table('business_analytics_sync_runs')->insertGetId([
            'source' => $source,
            'period' => $period,
            'status' => 'RUNNING',
            'started_at' => $startedAt,
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
        ]);

        try {
            $raw = $this->dataSource->fetch($source, $start, $end);
            if ($raw !== []) {
                $missing = $this->normalizer->missingRequiredColumns($raw[0]);
                if ($missing !== []) {
                    throw new RuntimeException('Kolom wajib tidak ditemukan: '.implode(', ', $missing));
                }
            }

            /** @var array<string,array<string,mixed>> $groups */
            $groups = [];
            foreach ($raw as $row) {
                $n = $this->normalizer->normalize($row, $source);
                $invoice = trim((string) ($n['invoice_number'] ?? ''));
                $line = SalesLineName::normalize((string) ($n['line'] ?? 'LAINNYA'));
                $aliases = (array) config('sales_dashboard.aliases', []);
                $line = SalesLineName::normalize((string) ($aliases[$line] ?? $line));
                $keyParts = [
                    $line,
                    trim((string) ($n['customer_code'] ?? '')),
                    trim((string) ($n['customer_name'] ?? '')),
                    trim((string) ($n['sales_person_name'] ?? '')),
                    trim((string) ($n['item_code'] ?? '')),
                    trim((string) ($n['item_name'] ?? '')),
                ];
                $key = implode("\x1F", $keyParts);
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'period' => $period,
                        'source' => $source,
                        'sales_line' => $keyParts[0] !== '' ? $keyParts[0] : 'LAINNYA',
                        'customer_code' => $keyParts[1],
                        'customer_name' => $keyParts[2],
                        'salesperson_name' => $keyParts[3],
                        'item_code' => $keyParts[4],
                        'item_name' => $keyParts[5],
                        'sales_amount' => 0.0,
                        'quantity' => 0.0,
                        '_invoices' => [],
                    ];
                }
                $groups[$key]['sales_amount'] += (float) ($n['sales'] ?? 0.0);
                $groups[$key]['quantity'] += (float) ($n['quantity'] ?? 0.0);
                if ($invoice !== '') {
                    $groups[$key]['_invoices'][$invoice] = true;
                }
            }

            $now = now();
            $rows = [];
            foreach ($groups as $group) {
                $invoices = $group['_invoices'];
                unset($group['_invoices']);
                $group['invoice_count'] = count($invoices);
                $group['created_at'] = $now;
                $group['updated_at'] = $now;
                $rows[] = $group;
            }

            DB::connection('sqlite_targets')->transaction(function () use ($source, $period, $rows): void {
                $db = DB::connection('sqlite_targets');
                $db->table('business_analytics_monthly_sales')
                    ->where('source', $source)
                    ->where('period', $period)
                    ->delete();

                foreach (array_chunk($rows, 300) as $chunk) {
                    if ($chunk !== []) {
                        $db->table('business_analytics_monthly_sales')->insert($chunk);
                    }
                }
            });

            DB::connection('sqlite_targets')->table('business_analytics_sync_runs')->where('id', $runId)->update([
                'status' => 'SUCCESS',
                'source_rows' => count($raw),
                'aggregate_rows' => count($rows),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);

            return ['source_rows' => count($raw), 'aggregate_rows' => count($rows)];
        } catch (Throwable $e) {
            DB::connection('sqlite_targets')->table('business_analytics_sync_runs')->where('id', $runId)->update([
                'status' => 'FAILED',
                'message' => mb_substr($e->getMessage(), 0, 2000),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
            throw $e;
        }
    }

    /** @return array{source_rows:int,aggregate_rows:int} */
    public function syncDailyMonth(
        string $source,
        DateTimeImmutable $month,
        ?DateTimeImmutable $clipStart = null,
        ?DateTimeImmutable $clipEnd = null,
    ): array {
        $source = strtoupper(trim($source));
        $monthStart = $month->modify('first day of this month');
        $monthEnd = $month->modify('last day of this month');
        $start = $clipStart !== null && $clipStart > $monthStart ? $clipStart : $monthStart;
        $end = $clipEnd !== null && $clipEnd < $monthEnd ? $clipEnd : $monthEnd;
        if ($start > $end) {
            return ['source_rows' => 0, 'aggregate_rows' => 0];
        }

        $raw = $this->dataSource->fetch($source, $monthStart, $monthEnd);
        if ($raw !== []) {
            $missing = $this->normalizer->missingRequiredColumns($raw[0]);
            if ($missing !== []) {
                throw new RuntimeException('Kolom wajib tidak ditemukan: '.implode(', ', $missing));
            }
        }

        /** @var array<string,array<string,mixed>> $groups */
        $groups = [];
        foreach ($raw as $row) {
            $n = $this->normalizer->normalize($row, $source);
            $dateText = trim((string) ($n['invoice_date'] ?? ''));
            if ($dateText === '' || $dateText < $start->format('Y-m-d') || $dateText > $end->format('Y-m-d')) {
                continue;
            }
            $invoice = trim((string) ($n['invoice_number'] ?? ''));
            $line = SalesLineName::normalize((string) ($n['line'] ?? 'LAINNYA'));
            $aliases = (array) config('sales_dashboard.aliases', []);
            $line = SalesLineName::normalize((string) ($aliases[$line] ?? $line));
            $keyParts = [
                $dateText,
                $line,
                trim((string) ($n['customer_code'] ?? '')),
                trim((string) ($n['customer_name'] ?? '')),
                trim((string) ($n['sales_person_name'] ?? '')),
                trim((string) ($n['item_code'] ?? '')),
                trim((string) ($n['item_name'] ?? '')),
            ];
            $key = implode("\x1F", $keyParts);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'sales_date' => $keyParts[0],
                    'source' => $source,
                    'sales_line' => $keyParts[1] !== '' ? $keyParts[1] : 'LAINNYA',
                    'customer_code' => $keyParts[2],
                    'customer_name' => $keyParts[3],
                    'salesperson_name' => $keyParts[4],
                    'item_code' => $keyParts[5],
                    'item_name' => $keyParts[6],
                    'sales_amount' => 0.0,
                    'quantity' => 0.0,
                    '_invoices' => [],
                ];
            }
            $groups[$key]['sales_amount'] += (float) ($n['sales'] ?? 0.0);
            $groups[$key]['quantity'] += (float) ($n['quantity'] ?? 0.0);
            if ($invoice !== '') {
                $groups[$key]['_invoices'][$invoice] = true;
            }
        }

        $now = now();
        $rows = [];
        foreach ($groups as $group) {
            $invoices = $group['_invoices'];
            unset($group['_invoices']);
            $group['invoice_count'] = count($invoices);
            $group['created_at'] = $now;
            $group['updated_at'] = $now;
            $rows[] = $group;
        }

        DB::connection('sqlite_targets')->transaction(function () use ($source, $start, $end, $rows): void {
            $db = DB::connection('sqlite_targets');
            $db->table('business_analytics_daily_sales')
                ->where('source', $source)
                ->whereBetween('sales_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->delete();
            foreach (array_chunk($rows, 300) as $chunk) {
                if ($chunk !== []) {
                    $db->table('business_analytics_daily_sales')->insert($chunk);
                }
            }
        });

        return ['source_rows' => count($raw), 'aggregate_rows' => count($rows)];
    }

}
