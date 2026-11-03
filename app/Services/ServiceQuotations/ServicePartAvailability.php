<?php

declare(strict_types=1);

namespace App\Services\ServiceQuotations;

use App\Services\Spareparts\SparepartSearchService;
use RuntimeException;

final class ServicePartAvailability
{
    public function __construct(private readonly SparepartSearchService $search)
    {
    }

    /** @return list<array<string,mixed>> */
    public function candidates(string $partCode): array
    {
        $result = $this->search->search(ServiceItemCatalog::validCode($partCode), 'all');
        if (($result['errors'] ?? []) !== []) {
            throw new RuntimeException('Koneksi stok ERP / master similar belum lengkap. Tidak dapat membuat harga penawaran yang aman.');
        }
        $items = [array_merge($result['original'], ['depth' => 0]), ...$result['similar']];
        $candidates = [];
        foreach ($items as $item) {
            foreach ($item['warehouses'] as $wh) {
                if ((float) ($wh['qty'] ?? 0) <= 0 || empty($wh['warehouse_id'])) continue;
                $candidates[] = [
                    'root_code' => $partCode,
                    'item_code' => (string) $item['item_code'],
                    'item_name' => (string) $item['item_name'],
                    'depth' => (int) $item['depth'],
                    'source' => (string) $wh['source'],
                    'warehouse_id' => (int) $wh['warehouse_id'],
                    'warehouse_code' => (string) ($wh['warehouse_code'] ?? ''),
                    'warehouse_name' => (string) ($wh['warehouse_name'] ?? ''),
                    'qty_available' => (float) $wh['qty'],
                    'retail_price' => $wh['retail_price'],
                    'reference_price' => $wh['reference_price'],
                    'selectable' => isset($wh['retail_price']) && (float) $wh['retail_price'] > 0,
                ];
            }
        }
        usort($candidates, static fn (array $a, array $b): int =>
            [$a['depth'], $a['selectable'] ? 0 : 1, -$a['qty_available'], $a['source']]
            <=> [$b['depth'], $b['selectable'] ? 0 : 1, -$b['qty_available'], $b['source']]
        );
        return $candidates;
    }

    /** @param array<string,mixed> $line @param list<array<string,mixed>> $candidates
     * @return array<string,mixed> */
    public function confirm(array $line, array $candidates): array
    {
        foreach ($candidates as $candidate) {
            if ($candidate['item_code'] !== strtoupper(trim((string) $line['item_code']))) continue;
            if ($candidate['source'] !== strtoupper(trim((string) $line['source']))) continue;
            if ($candidate['warehouse_id'] !== (int) $line['warehouse_id']) continue;
            if (!$candidate['selectable']) throw new RuntimeException('Sparepart ini belum memiliki harga RETAIL.');
            if ((float) $line['qty'] > (float) $candidate['qty_available']) {
                throw new RuntimeException('Jumlah '.$candidate['item_code'].' melebihi stok terbaru pada gudang yang dipilih.');
            }
            return $candidate;
        }
        throw new RuntimeException('Pilihan sparepart, sumber, gudang atau relasi Similar Items tidak valid / stok sudah kosong.');
    }
}
