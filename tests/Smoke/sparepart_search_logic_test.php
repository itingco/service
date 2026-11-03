<?php
/** Standalone smoke test: php tests/Smoke/sparepart_search_logic_test.php */
declare(strict_types=1);

require_once __DIR__.'/../../app/Services/Spareparts/SparepartCode.php';
require_once __DIR__.'/../../app/Services/Spareparts/SparepartRecommendationEngine.php';

use App\Services\Spareparts\SparepartCode;
use App\Services\Spareparts\SparepartRecommendationEngine;

function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$message);
    echo "PASS: {$message}\n";
}
function stock(string $code, float $qty, string $db = 'INGCO'): array {
    return [
        'item_code' => $code, 'item_name' => 'Test', 'qty' => $qty,
        'source' => $db, 'warehouse_id' => 1, 'warehouse_name' => 'Warehouse Test',
        'retail_price' => 10000.0,
    ];
}

$engine = new SparepartRecommendationEngine();
check(SparepartCode::parent('abcd-sp-1') === 'ABCD', 'Parent ABCD-SP-1 resolves ABCD');
check(SparepartCode::parent('BCD-G-SP-77') === 'BCD-G', 'Nested dash in parent retained');
check(SparepartCode::parent('ABCD-12') === null, 'Invalid sparepart suffix rejected');

$original = $engine->evaluate('ABCD-SP-1', ['BCDG-SP-7' => 1], [stock('ABCD-SP-1', 3), stock('BCDG-SP-7', 4)]);
check($original['priority'] === 'original' && $original['parents'] === [], 'Original stock wins');

$similar = $engine->evaluate('ABCD-SP-1', ['BCDG-SP-7' => 1], [stock('ABCD-SP-1', 0), stock('BCDG-SP-7', 2)]);
check($similar['priority'] === 'similar' && $similar['parents'] === [], 'Similar stock wins if original empty');

$donor = $engine->evaluate('ABCD-SP-1', ['BCDG-SP-7' => 1], [stock('ABCD-SP-1', 0), stock('BCDG-SP-7', 0)]);
check($donor['priority'] === 'donor', 'Donor phase is enabled only if all spareparts empty');
check(array_keys($donor['parents']) === ['ABCD', 'BCDG'], 'Donors are ABCD and BCDG');

$negative = $engine->evaluate('ABCD-SP-1', [], [stock('ABCD-SP-1', -2)]);
check($negative['priority'] === 'donor' && $negative['original']['total_qty'] === 0, 'Negative stock is not purchasable');

$locations = $engine->item('ABCD', [stock('ABCD', 2, 'INGCO'), stock('ABCD', 4, 'SMI')]);
check($locations['total_qty'] === 6.0 && count($locations['warehouses']) === 2, 'Warehouse/source balances are combined without hiding their location');

echo "\nALL SPAREPART SEARCH SMOKE TESTS PASS\n";
