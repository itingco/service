<?php

declare(strict_types=1);

namespace App\Services\Spareparts;

/** Pure recommendation decision logic; can be tested without SQL Server. */
final class SparepartRecommendationEngine
{
    /**
     * @param array<string,int> $alternatives
     * @param list<array<string,mixed>> $partRows
     * @return array<string,mixed>
     */
    public function evaluate(string $code, array $alternatives, array $partRows): array
    {
        $code = SparepartCode::normalize($code);
        $original = $this->item($code, $partRows);
        $similar = [];
        foreach ($alternatives as $alternative => $depth) {
            $candidate = $this->item($alternative, $partRows);
            $candidate['depth'] = $depth;
            $similar[] = $candidate;
        }
        usort($similar, static fn (array $a, array $b): int =>
            [(int) $a['depth'], -$a['total_qty'], (string) $a['item_code']]
            <=> [(int) $b['depth'], -$b['total_qty'], (string) $b['item_code']]
        );

        $hasOriginal = $original['total_qty'] > 0;
        $hasSimilar = count(array_filter($similar, static fn (array $x): bool => $x['total_qty'] > 0)) > 0;
        $parents = [];
        if (!$hasOriginal && !$hasSimilar) {
            foreach ([$code, ...array_keys($alternatives)] as $part) {
                $parent = SparepartCode::parent($part);
                if ($parent !== null) {
                    $parents[$parent][] = SparepartCode::normalize($part);
                }
            }
            ksort($parents);
        }

        return [
            'original' => $original,
            'similar' => $similar,
            'parents' => $parents,
            'priority' => $hasOriginal ? 'original' : ($hasSimilar ? 'similar' : 'donor'),
        ];
    }

    /** @param list<array<string,mixed>> $rows @return array<string,mixed> */
    public function item(string $code, array $rows): array
    {
        $code = SparepartCode::normalize($code);
        $matches = array_values(array_filter($rows, static fn (array $row): bool =>
            SparepartCode::normalize((string) ($row['item_code'] ?? '')) === $code
        ));
        $stock = array_values(array_filter($matches, static fn (array $r): bool =>
            (float) ($r['qty'] ?? 0) > 0 && !empty($r['warehouse_id'])
        ));
        usort($stock, static fn (array $a, array $b): int =>
            [(string) ($a['source'] ?? ''), (string) ($a['warehouse_name'] ?? '')]
            <=> [(string) ($b['source'] ?? ''), (string) ($b['warehouse_name'] ?? '')]
        );
        $names = array_values(array_filter(array_map(static fn (array $r): string =>
            trim((string) ($r['item_name'] ?? '')), $matches
        )));

        return [
            'item_code' => $code,
            'item_name' => $names[0] ?? '',
            'exists' => $matches !== [],
            'total_qty' => array_sum(array_map(static fn (array $r): float => (float) $r['qty'], $stock)),
            'warehouses' => $stock,
        ];
    }
}
