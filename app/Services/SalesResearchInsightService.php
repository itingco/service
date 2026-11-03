<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\SalesResearchInsights;

final class SalesResearchInsightService
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $previousRows
     */
    public function build(array $currentRows, array $previousRows): SalesResearchInsights
    {
        $current = $this->aggregateProducts($currentRows);
        $previous = $this->aggregateProducts($previousRows);
        $keys = array_values(array_unique([...array_keys($current), ...array_keys($previous)]));

        $signals = [];
        foreach ($keys as $key) {
            $now = $current[$key] ?? $this->emptyProduct($key);
            $before = $previous[$key] ?? $this->emptyProduct($key);
            $signals[] = $this->productSignal($now, $before);
        }

        usort($signals, static function (array $left, array $right): int {
            $priority = [
                'SCALE UP' => 7,
                'PERMINTAAN BARU' => 6,
                'VALIDASI PASAR' => 5,
                'RISIKO KONSENTRASI' => 4,
                'EVALUASI' => 3,
                'PERMINTAAN HILANG' => 2,
                'FASE KELUAR' => 1,
                'STABIL' => 0,
            ];

            return [
                $priority[$right['signal']] ?? 0,
                $right['current_sales'],
            ] <=> [
                $priority[$left['signal']] ?? 0,
                $left['current_sales'],
            ];
        });

        $newDemand = array_values(array_filter(
            $signals,
            static fn (array $row): bool => $row['signal'] === 'PERMINTAAN BARU',
        ));
        $lostDemand = array_values(array_filter(
            $signals,
            static fn (array $row): bool => $row['signal'] === 'PERMINTAAN HILANG',
        ));
        $concentrationRisks = array_values(array_filter(
            $signals,
            static fn (array $row): bool => $row['current_sales'] > 0.0
                && $row['top_customer_share'] !== null
                && $row['top_customer_share'] >= 0.70,
        ));
        usort($concentrationRisks, static fn (array $a, array $b): int => $b['current_sales'] <=> $a['current_sales']);

        $discountWatch = array_values(array_filter(
            $signals,
            static fn (array $row): bool => $row['current_sales'] > 0.0
                && $row['previous_sales'] > 0.0
                && !$row['discontinued']
                && !$row['clearance']
                && $row['average_discount'] > 0.0
                && $row['growth'] !== null
                && $row['growth'] <= 0.0,
        ));
        usort($discountWatch, static fn (array $a, array $b): int => [
            $b['average_discount'],
            $b['current_sales'],
        ] <=> [
            $a['average_discount'],
            $a['current_sales'],
        ]);

        $signalCounts = [];
        foreach ($signals as $row) {
            $signalCounts[$row['signal']] = ($signalCounts[$row['signal']] ?? 0) + 1;
        }

        return new SalesResearchInsights(
            productSignals: $signals,
            newDemand: $newDemand,
            lostDemand: $lostDemand,
            concentrationRisks: $concentrationRisks,
            discountWatch: $discountWatch,
            gradeDemand: $this->gradeDemand($currentRows, $previousRows),
            channelFit: $this->channelFit($currentRows),
            signalCounts: $signalCounts,
        );
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private function aggregateProducts(array $rows): array
    {
        $products = [];

        foreach ($rows as $row) {
            $key = $this->itemKey($row);
            $products[$key] ??= $this->emptyProduct($key);
            $product = &$products[$key];

            $sales = $this->sales($row);
            $quantity = (float) ($row['quantity'] ?? 0.0);
            $discount = max(0.0, (float) ($row['discount'] ?? 0.0))
                + max(0.0, (float) ($row['discount2'] ?? 0.0));
            $customer = $this->customerKey($row);
            $line = trim((string) ($row['line'] ?? '')) ?: 'LAINNYA';

            $product['item_code'] = trim((string) ($row['item_code'] ?? '')) ?: $product['item_code'];
            $product['item_name'] = trim((string) ($row['item_name'] ?? '')) ?: $product['item_name'];
            $product['grade'] = strtoupper(trim((string) ($row['grade'] ?? ''))) ?: $product['grade'];
            $product['sales'] += $sales;
            $product['quantity'] += $quantity;
            $product['discount_weighted'] += $discount * max(abs($sales), 1.0);
            $product['discount_weight'] += max(abs($sales), 1.0);
            $product['customers'][$customer] = ($product['customers'][$customer] ?? 0.0) + $sales;
            $product['lines'][$line] = ($product['lines'][$line] ?? 0.0) + $sales;
            $product['discontinued'] = $product['discontinued'] || (bool) ($row['discontinued'] ?? false);
            $product['clearance'] = $product['clearance'] || (bool) ($row['clearance'] ?? false);
            unset($product);
        }

        return $products;
    }

    /** @return array<string,mixed> */
    private function emptyProduct(string $key): array
    {
        return [
            'key' => $key,
            'item_code' => str_starts_with($key, 'NAME:') ? '' : $key,
            'item_name' => str_starts_with($key, 'NAME:') ? substr($key, 5) : '',
            'grade' => '',
            'sales' => 0.0,
            'quantity' => 0.0,
            'discount_weighted' => 0.0,
            'discount_weight' => 0.0,
            'customers' => [],
            'lines' => [],
            'discontinued' => false,
            'clearance' => false,
        ];
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $previous @return array<string,mixed> */
    private function productSignal(array $current, array $previous): array
    {
        $currentSales = (float) $current['sales'];
        $previousSales = (float) $previous['sales'];
        $growth = $previousSales !== 0.0 ? ($currentSales / $previousSales) - 1.0 : null;
        $customers = (array) $current['customers'];
        arsort($customers);
        $lines = (array) $current['lines'];
        arsort($lines);

        $topCustomerSales = $customers === [] ? 0.0 : (float) reset($customers);
        $topCustomerShare = $currentSales > 0.0 ? $topCustomerSales / $currentSales : null;
        $primaryLine = $lines === [] ? '—' : (string) array_key_first($lines);
        $primaryLineSales = $lines === [] ? 0.0 : (float) reset($lines);
        $primaryLineShare = $currentSales > 0.0 ? $primaryLineSales / $currentSales : null;
        $averageDiscount = (float) $current['discount_weight'] > 0.0
            ? (float) $current['discount_weighted'] / (float) $current['discount_weight']
            : 0.0;

        $itemCode = (string) ($current['item_code'] ?: $previous['item_code']);
        $itemName = (string) ($current['item_name'] ?: $previous['item_name']);
        $grade = (string) ($current['grade'] ?: $previous['grade']);
        $discontinued = (bool) ($current['discontinued'] || $previous['discontinued']);
        $clearance = (bool) ($current['clearance'] || $previous['clearance']);

        [$signal, $recommendation] = $this->classification(
            currentSales: $currentSales,
            previousSales: $previousSales,
            growth: $growth,
            customerCount: count($customers),
            topCustomerShare: $topCustomerShare,
            discontinued: $discontinued,
            clearance: $clearance,
            averageDiscount: $averageDiscount,
        );

        return [
            'item_code' => $itemCode,
            'item_name' => $itemName,
            'label' => trim($itemCode.' — '.$itemName, " —"),
            'grade' => $grade === '' ? 'KOSONG' : $grade,
            'current_sales' => $currentSales,
            'previous_sales' => $previousSales,
            'growth' => $growth,
            'quantity' => (float) $current['quantity'],
            'customer_count' => count($customers),
            'top_customer_share' => $topCustomerShare,
            'average_discount' => $averageDiscount,
            'primary_line' => $primaryLine,
            'primary_line_share' => $primaryLineShare,
            'discontinued' => $discontinued,
            'clearance' => $clearance,
            'signal' => $signal,
            'recommendation' => $recommendation,
        ];
    }

    /** @return array{0:string,1:string} */
    private function classification(
        float $currentSales,
        float $previousSales,
        ?float $growth,
        int $customerCount,
        ?float $topCustomerShare,
        bool $discontinued,
        bool $clearance,
        float $averageDiscount,
    ): array {
        if (($discontinued || $clearance) && $currentSales > 0.0) {
            return ['FASE KELUAR', 'Pantau sell-through dan sisa stok; hindari penambahan stok sebelum keputusan lifecycle disetujui.'];
        }
        if ($currentSales > 0.0 && $previousSales <= 0.0) {
            return ['PERMINTAAN BARU', 'Validasi repeat order, margin, ketersediaan stok, dan channel utama sebelum scale-up.'];
        }
        if ($currentSales <= 0.0 && $previousSales > 0.0) {
            return ['PERMINTAAN HILANG', 'Telusuri stok, perubahan harga, kompetitor, dan customer yang berhenti membeli.'];
        }
        if ($growth !== null && $growth >= 0.20 && $customerCount >= 3) {
            return ['SCALE UP', 'Pertimbangkan perluasan stok dan promosi pada channel utama; pertahankan validasi margin.'];
        }
        if ($growth !== null && $growth >= 0.20) {
            return ['VALIDASI PASAR', 'Pertumbuhan tinggi tetapi basis customer masih sempit; cari customer pembanding sebelum ekspansi.'];
        }
        if ($growth !== null && $growth <= -0.20) {
            return ['EVALUASI', $averageDiscount > 0.0
                ? 'Penjualan turun meski ada diskon; evaluasi product-market fit, harga, dan kualitas permintaan.'
                : 'Evaluasi stok, harga, fitur produk, customer hilang, dan aktivitas kompetitor.'];
        }
        if ($topCustomerShare !== null && $topCustomerShare >= 0.70) {
            return ['RISIKO KONSENTRASI', 'Permintaan terlalu bergantung pada satu customer; perluas validasi ke customer dan channel lain.'];
        }

        return ['STABIL', 'Pertahankan ketersediaan dan pantau repeat order, margin, serta perubahan demand.'];
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $previousRows
     * @return list<array<string,mixed>>
     */
    private function gradeDemand(array $currentRows, array $previousRows): array
    {
        $current = $this->aggregateDimension($currentRows, static fn (array $row): string => strtoupper(trim((string) ($row['grade'] ?? ''))) ?: 'KOSONG');
        $previous = $this->aggregateDimension($previousRows, static fn (array $row): string => strtoupper(trim((string) ($row['grade'] ?? ''))) ?: 'KOSONG');
        $grades = array_values(array_unique([...array_keys($current), ...array_keys($previous)]));
        $rows = [];

        foreach ($grades as $grade) {
            $now = (float) ($current[$grade] ?? 0.0);
            $before = (float) ($previous[$grade] ?? 0.0);
            $rows[] = [
                'grade' => $grade,
                'current_sales' => $now,
                'previous_sales' => $before,
                'growth' => $before !== 0.0 ? ($now / $before) - 1.0 : null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['current_sales'] <=> $a['current_sales']);
        return $rows;
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function channelFit(array $rows): array
    {
        $byLineItem = [];
        $lineTotals = [];

        foreach ($rows as $row) {
            $line = trim((string) ($row['line'] ?? '')) ?: 'LAINNYA';
            $item = $this->itemKey($row);
            $sales = $this->sales($row);
            $lineTotals[$line] = ($lineTotals[$line] ?? 0.0) + $sales;
            $byLineItem[$line][$item] ??= [
                'line' => $line,
                'item_code' => trim((string) ($row['item_code'] ?? '')),
                'item_name' => trim((string) ($row['item_name'] ?? '')),
                'sales' => 0.0,
            ];
            $byLineItem[$line][$item]['sales'] += $sales;
        }

        $result = [];
        foreach ($byLineItem as $line => $items) {
            usort($items, static fn (array $a, array $b): int => $b['sales'] <=> $a['sales']);
            $top = $items[0];
            $result[] = [
                ...$top,
                'share_of_line' => ($lineTotals[$line] ?? 0.0) > 0.0
                    ? $top['sales'] / $lineTotals[$line]
                    : null,
            ];
        }

        usort($result, static fn (array $a, array $b): int => $b['sales'] <=> $a['sales']);
        return $result;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param callable(array<string,mixed>):string $resolver
     * @return array<string,float>
     */
    private function aggregateDimension(array $rows, callable $resolver): array
    {
        $result = [];
        foreach ($rows as $row) {
            $key = $resolver($row);
            $result[$key] = ($result[$key] ?? 0.0) + $this->sales($row);
        }
        return $result;
    }

    /** @param array<string,mixed> $row */
    private function itemKey(array $row): string
    {
        $code = strtoupper(trim((string) ($row['item_code'] ?? '')));
        if ($code !== '') {
            return $code;
        }
        $name = strtoupper(trim((string) ($row['item_name'] ?? '')));
        return 'NAME:'.($name !== '' ? $name : 'ITEM TANPA NAMA');
    }

    /** @param array<string,mixed> $row */
    private function customerKey(array $row): string
    {
        $code = strtoupper(trim((string) ($row['customer_code'] ?? '')));
        if ($code !== '') {
            return $code;
        }
        $name = strtoupper(trim((string) ($row['customer_name'] ?? '')));
        return $name !== '' ? $name : 'CUSTOMER TANPA NAMA';
    }

    /** @param array<string,mixed> $row */
    private function sales(array $row): float
    {
        return (float) ($row['line_total'] ?? $row['sales'] ?? 0.0);
    }
}
