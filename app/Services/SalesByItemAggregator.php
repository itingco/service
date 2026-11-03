<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\SalesByItemFilter;
use App\Data\SalesByItemReport;
use App\Data\SalesByItemRow;

final class SalesByItemAggregator
{
    /**
     * @param list<array{item_code:string,item_name:string,quantity:float,sales:float,source:string}> $transactions
     */
    public function aggregate(SalesByItemFilter $filter, array $transactions): SalesByItemReport
    {
        /** @var array<string,array{item_code:string,item_name:string,quantity:float,sales:float,sources:array<string,bool>}> $groups */
        $groups = [];

        foreach ($transactions as $transaction) {
            $code = strtoupper(trim($transaction['item_code']));
            if ($code === '') {
                continue;
            }

            if (!isset($groups[$code])) {
                $groups[$code] = [
                    'item_code' => $code,
                    'item_name' => trim($transaction['item_name']) ?: $code,
                    'quantity' => 0.0,
                    'sales' => 0.0,
                    'sources' => [],
                ];
            }

            if ($groups[$code]['item_name'] === $code && trim($transaction['item_name']) !== '') {
                $groups[$code]['item_name'] = trim($transaction['item_name']);
            }

            $groups[$code]['quantity'] += (float) $transaction['quantity'];
            $groups[$code]['sales'] += (float) $transaction['sales'];
            $groups[$code]['sources'][strtoupper($transaction['source'])] = true;
        }

        $totalSales = array_sum(array_column($groups, 'sales'));
        $totalQuantity = array_sum(array_column($groups, 'quantity'));
        $rows = [];

        foreach ($groups as $group) {
            $sources = array_keys($group['sources']);
            sort($sources);

            $rows[] = new SalesByItemRow(
                itemCode: $group['item_code'],
                itemName: $group['item_name'],
                totalQuantity: $group['quantity'],
                totalSales: $group['sales'],
                averagePrice: $group['quantity'] !== 0.0 ? $group['sales'] / $group['quantity'] : 0.0,
                contribution: $totalSales !== 0.0 ? ($group['sales'] / $totalSales) * 100 : 0.0,
                source: $this->sourceLabel($sources),
            );
        }

        usort(
            $rows,
            static fn (SalesByItemRow $a, SalesByItemRow $b): int =>
                $b->totalSales <=> $a->totalSales ?: strcmp($a->itemCode, $b->itemCode)
        );

        $totalItems = count($rows);

        return new SalesByItemReport(
            filter: $filter,
            rows: $rows,
            totalQuantity: $totalQuantity,
            totalSales: $totalSales,
            totalItems: $totalItems,
            averageSalesPerItem: $totalItems > 0 ? $totalSales / $totalItems : 0.0,
        );
    }

    /** @param list<string> $sources */
    private function sourceLabel(array $sources): string
    {
        $hasIngco = in_array('INGCO', $sources, true);
        $hasSmi = in_array('SMI', $sources, true);

        if ($hasIngco && $hasSmi) {
            return 'INGCO & SMI';
        }

        return $sources[0] ?? '-';
    }
}
