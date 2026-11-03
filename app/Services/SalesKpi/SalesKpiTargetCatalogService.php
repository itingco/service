<?php

declare(strict_types=1);

namespace App\Services\SalesKpi;

use App\Data\SalesKpi\SalesKpiTargetCatalogRow;
use App\Exceptions\SalesKpiDataException;
use DateTimeImmutable;

final class SalesKpiTargetCatalogService
{
    public function __construct(
        private readonly SalesKpiDataService $dataService,
        private readonly SalesKpiTargetService $targetService,
    ) {
    }

    /** @return list<SalesKpiTargetCatalogRow> */
    public function currentMonth(DateTimeImmutable $today): array
    {
        $start = $today->modify('first day of this month')->setTime(0, 0);
        $end = $today->setTime(0, 0);
        $transactions = [];
        $failures = [];
        $exceptions = [];

        foreach (['INGCO', 'SMI'] as $source) {
            try {
                $transactions = array_merge(
                    $transactions,
                    $this->dataService->loadRange($source, $start, $end),
                );
            } catch (SalesKpiDataException $exception) {
                $failures = array_merge($failures, $exception->failedSources());
                $exceptions[] = $exception;
            }
        }

        if ($failures !== []) {
            if (count($exceptions) === 1) {
                throw $exceptions[0];
            }

            throw SalesKpiDataException::forSources(
                $failures,
                'Satu atau lebih database KPI gagal dimuat.',
            );
        }

        /** @var array<string,array{line:string,omzet:float,has_transactions:bool,target?:?float}> $catalog */
        $catalog = [];

        // Pastikan semua lini default tetap muncul di menu target,
        // meskipun belum ada transaksi bulan berjalan.
        foreach ((array) config('sales_dashboard.targets', []) as $line => $target) {
            $key = $this->targetService->normalizeLine((string) $line);
            $value = $target === null ? null : (float) $target;

            $catalog[$key] = [
                'line' => $key,
                'omzet' => 0.0,
                'has_transactions' => false,
                'target' => $value !== null && $value > 0.0 ? $value : null,
            ];
        }

        foreach ($transactions as $transaction) {
            $key = $this->targetService->normalizeLine($transaction->lineKey);
            $catalog[$key]['line'] ??= $this->targetService->normalizeLine($transaction->line);
            $catalog[$key]['omzet'] = ($catalog[$key]['omzet'] ?? 0.0) + $transaction->lineTotal;
            $catalog[$key]['has_transactions'] = true;
        }

        // Target SQLite meng-override target default config.
        foreach ($this->targetService->all() as $line => $target) {
            $key = $this->targetService->normalizeLine($line);
            $catalog[$key]['line'] ??= $key;
            $catalog[$key]['omzet'] ??= 0.0;
            $catalog[$key]['has_transactions'] ??= false;
            $catalog[$key]['target'] = $target;
        }

        $rows = [];
        foreach ($catalog as $key => $values) {
            $target = $values['target'] ?? null;
            $omzet = (float) ($values['omzet'] ?? 0.0);

            $rows[] = new SalesKpiTargetCatalogRow(
                line: $values['line'],
                lineKey: $key,
                monthlyTarget: $target,
                currentOmzet: $omzet,
                hasCurrentTransactions: (bool) ($values['has_transactions'] ?? false),
                hasTarget: $target !== null && $target > 0.0,
            );
        }

        usort(
            $rows,
            static fn (SalesKpiTargetCatalogRow $a, SalesKpiTargetCatalogRow $b): int =>
                strcasecmp($a->line, $b->line)
        );

        return $rows;
    }
}
