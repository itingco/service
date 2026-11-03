<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

final class SalesPersonDirectoryService
{
    /** @return array<int,string> */
    public function options(string $database = 'BOTH'): array
    {
        $sources = $this->sources($database);
        $combined = [];

        foreach ($sources as $source) {
            $connection = (string) config('customer_analysis.connections.'.$source);
            if ($connection === '') {
                continue;
            }

            try {
                $rows = DB::connection($connection)->select(
                    'SELECT SalesPersonID, SalesPersonName FROM AR_SalesPersons ORDER BY SalesPersonName, SalesPersonID'
                );
            } catch (Throwable) {
                continue;
            }

            foreach ($rows as $row) {
                $id = (int) ($row->SalesPersonID ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $name = trim((string) ($row->SalesPersonName ?? ''));
                if ($name === '') {
                    $name = 'Sales '.$id;
                }

                $combined[$id]['names'][$name] = true;
                $combined[$id]['sources'][$source] = true;
            }
        }

        ksort($combined, SORT_NUMERIC);
        $options = [];
        foreach ($combined as $id => $meta) {
            $names = array_keys($meta['names'] ?? []);
            sort($names, SORT_NATURAL | SORT_FLAG_CASE);
            $sourceNames = array_keys($meta['sources'] ?? []);
            sort($sourceNames);
            $sourceLabel = count($sourceNames) > 1 ? 'INGCO & SMI' : ($sourceNames[0] ?? 'ERP');
            $options[(int) $id] = sprintf('%d - %s [%s]', $id, implode(' / ', $names), $sourceLabel);
        }

        return $options;
    }

    /** @return list<string> */
    private function sources(string $database): array
    {
        return match (strtoupper(trim($database))) {
            'INGCO' => ['INGCO'],
            'SMI' => ['SMI'],
            default => ['INGCO', 'SMI'],
        };
    }
}
