<?php

declare(strict_types=1);

namespace App\Services\SalesKpi;

use App\Contracts\SalesKpiTargetLookup;
use App\Models\SalesKpiTarget;
use App\Support\SalesLineName;
use Illuminate\Support\Facades\DB;

final class SalesKpiTargetService implements SalesKpiTargetLookup
{
    public function targetForLine(string $line): ?float
    {
        $value = $this->all()[$this->normalizeLine($line)] ?? null;

        return $value === null || $value <= 0.0 ? null : $value;
    }

    /** @return array<string,?float> */
    public function all(): array
    {
        $targets = [];

        foreach (SalesKpiTarget::query()->orderBy('sales_line')->get(['sales_line', 'monthly_target']) as $target) {
            $line = $this->normalizeLine($target->sales_line);
            $value = $target->monthly_target === null ? null : (float) $target->monthly_target;

            $isCanonicalRecord = $target->sales_line === $line;
            $hasNoValueYet = !array_key_exists($line, $targets) || $targets[$line] === null;

            if ($isCanonicalRecord || $hasNoValueYet) {
                $targets[$line] = $value;
            }
        }

        ksort($targets, SORT_NATURAL | SORT_FLAG_CASE);

        return $targets;
    }

    /** @param array<string,?float> $targets */
    public function saveBatch(array $targets): void
    {
        DB::connection('sqlite_targets')->transaction(function () use ($targets): void {
            foreach ($targets as $line => $target) {
                $normalized = $this->normalizeLine($line);
                $value = $target === null || $target <= 0.0 ? null : $target;

                if ($normalized === SalesLineName::OTHERS) {
                    SalesKpiTarget::query()
                        ->get(['id', 'sales_line'])
                        ->filter(fn (SalesKpiTarget $row): bool =>
                            $row->sales_line !== SalesLineName::OTHERS
                            && $this->normalizeLine($row->sales_line) === SalesLineName::OTHERS
                        )
                        ->each(fn (SalesKpiTarget $row): bool => (bool) $row->delete());
                }

                SalesKpiTarget::query()->updateOrCreate(
                    ['sales_line' => $normalized],
                    ['monthly_target' => $value],
                );
            }
        });
    }

    public function normalizeLine(string $line): string
    {
        return SalesLineName::normalize($line);
    }
}
