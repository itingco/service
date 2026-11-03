<?php

declare(strict_types=1);

$root = dirname(__DIR__);
function changeFile(string $path, callable $callback): void {
    global $root;
    $file = $root.'/'.$path;
    if (!is_file($file)) throw new RuntimeException('File not found: '.$path);
    $old = file_get_contents($file);
    $new = $callback($old);
    if ($new === $old) { echo "Skip: {$path}\n"; return; }
    if (!is_string($new) || $new === '') throw new RuntimeException('Unexpected empty patch '.$path);
    copy($file, $file.'.before-warehouse-patch.bak');
    file_put_contents($file, $new);
    echo "Updated: {$path}\n";
}

// 1. Spareparts: free-form ERP item codes with parentheses and slash.
changeFile('app/Http/Controllers/SparepartSearchController.php', static function (string $s): string {
    $s = str_replace("'regex:/^[a-zA-Z0-9._-]+$/'", "'regex:~^[a-zA-Z0-9._()/ -]+$~D'", $s);
    $s = str_replace('Kode hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda minus.', 'Kode boleh berisi huruf, angka, titik, garis bawah, spasi, tanda minus, garis miring dan tanda kurung.', $s);
    return $s;
});
changeFile('app/Services/Spareparts/SparepartInventoryRepository.php', static function (string $s): string {
    return str_replace("'/^[A-Z0-9._-]{1,100}$/D'", "'~^[A-Z0-9._()/ -]{1,100}$~D'", $s);
});
changeFile('resources/views/spareparts/search.blade.php', static function (string $s): string {
    return str_replace('pattern="[A-Za-z0-9._-]+"', 'pattern="[A-Za-z0-9._()/ -]+"', $s);
});

// 2. Brand export raw: guarantee no empty logical brand.
changeFile('app/Exports/StockAgingRawExport.php', static function (string $s): string {
    $old = "(string) (\$row['brand'] ?? ''),";
    $new = "self::brandForExport(\$row),";
    if (strpos($s, $new) === false) {
        if (substr_count($s, $old) !== 1) throw new RuntimeException('Raw export Brand expression not found exactly once');
        $s = str_replace($old, $new, $s);
    }
    if (!str_contains($s, 'private static function brandForExport')) {
        $helper = <<<'METHOD'
    /** @param array<string,mixed> $row */
    private static function brandForExport(array $row): string
    {
        $database = strtoupper(trim((string)($row['database'] ?? '')));
        $source = strtoupper(trim((string)($row['source'] ?? '')));
        $brand = trim((string)($row['brand'] ?? ''));
        if ($database === 'INGCO' || $source === 'INGCO' || $database === 'AS_INGCO') return 'INGCO';
        if ($database === 'WADFOW' || strtoupper($brand) === 'WADFOW') return 'WADFOW';
        if ($database === 'SMI') return 'SMI';
        return $brand !== '' ? $brand : ($source === 'SMI' ? 'SMI' : ($database !== '' ? $database : 'UNKNOWN'));
    }

METHOD;
        $pos = strrpos($s, "\n}");
        if ($pos === false) throw new RuntimeException('Could not append export helper');
        $s = substr_replace($s, "\n".$helper, $pos, 0);
    }
    return $s;
});

// 3. Warehouse category master routes, kept behind existing dashboard permissions.
changeFile('routes/web.php', static function (string $s): string {
    if (str_contains($s, "->name('warehouse-categories.index')")) return $s;
    $route = <<<'ROUTES'

// Master klasifikasi gudang - access restricted to existing Product Focus master editors.
Route::middleware(['dashboard.auth', 'dashboard.audit', 'dashboard.permission:product_focus'])->group(function (): void {
    Route::get('/warehouse-categories', [\App\Http\Controllers\WarehouseCategoryController::class, 'index'])->name('warehouse-categories.index');
    Route::post('/warehouse-categories', [\App\Http\Controllers\WarehouseCategoryController::class, 'store'])->name('warehouse-categories.store');
    Route::put('/warehouse-categories/{id}', [\App\Http\Controllers\WarehouseCategoryController::class, 'update'])->whereNumber('id')->name('warehouse-categories.update');
    Route::delete('/warehouse-categories/{id}', [\App\Http\Controllers\WarehouseCategoryController::class, 'destroy'])->whereNumber('id')->name('warehouse-categories.destroy');
});
ROUTES;
    return $s.$route."\n";
});
changeFile('resources/views/layouts/app.blade.php', static function (string $s): string {
    if (str_contains($s, "route('warehouse-categories.index')")) return $s;
    $pattern = '~(<a[^>]*href="\{\{ route\(\x27product-strategy\.product-focus\.index\x27\) \}\}"[^>]*>.*?</a>)~s';
    $replacement = '$1'."\n".'<a href="{{ route(\'warehouse-categories.index\') }}" class="sidebar-link {{ request()->routeIs(\'warehouse-categories.*\') ? \'active\' : \'\' }}"><i class="bi bi-building sidebar-icon"></i><span class="nav-label">Klasifikasi Gudang</span></a>';
    $out = preg_replace($pattern, $replacement, $s, 1, $count);
    if ($count !== 1) throw new RuntimeException('Sidebar Product Focus menu marker missing; use manual integration');
    return $out;
});

// 4. Stock & Aging: reuse warehouse-category master, combine with existing individual warehouse selections.
changeFile('app/Http/Controllers/StockAgingController.php', static function (string $s): string {
    if (!str_contains($s, 'StockAgingRawExport')) throw new RuntimeException('Stock & Aging merged patch must be installed first');
    if (str_contains($s, '$warehouseCategoryOptions =')) return $s;
    $marker = '$rows = StockStrategyWarehouseSelection::filterRows($baseReport->detailRows, $warehouseKeys);';
    if (substr_count($s, $marker) !== 1) throw new RuntimeException('Stock rows filter marker mismatch');
    $insert = <<<'CODE'
$rows = StockStrategyWarehouseSelection::filterRows($baseReport->detailRows, $warehouseKeys);
        $warehouseCategoryService = app(\App\Services\WarehouseCategoryService::class);
        $warehouseCategoryOptions = $warehouseCategoryService->categories();
        $warehouseCategoryIds = array_values(array_unique(array_filter(array_map('intval', (array)$request->query('warehouse_category', [])), fn($id) => $id > 0)));
        if ($warehouseCategoryIds !== []) {
            $rows = $warehouseCategoryService->filterRows($rows, $warehouseCategoryIds);
        }
CODE;
    $s = str_replace($marker, $insert, $s);
    $compactMarker = "'warehouseOptions',";
    if (substr_count($s, $compactMarker) < 1) throw new RuntimeException('Stock view compact marker not found');
    $s = preg_replace("/'warehouseOptions',/", "'warehouseOptions',\n            'warehouseCategoryOptions',\n            'warehouseCategoryIds',", $s, 1);
    return $s;
});
changeFile('resources/views/stock-aging/index.blade.php', static function (string $s): string {
    if (str_contains($s, 'warehouse_category[]')) return $s;
    $anchor = '<div class="col-12">' . "\n" . '            <div class="group-panel';
    $field = <<<'BLADE'
        <div class="col-md-6 col-xl-3">
            <label class="form-label fw-bold">Kategori Gudang</label>
            <select class="form-select" name="warehouse_category[]" multiple size="3">
                @foreach($warehouseCategoryOptions as $category)
                    <option value="{{ $category['CategoryID'] }}" @selected(in_array((int)$category['CategoryID'], $warehouseCategoryIds, true))>{{ $category['CategoryName'] }}</option>
                @endforeach
            </select>
            <div class="filter-note">Opsional. Pilih satu/lebih kategori, lalu gabungkan dengan pilihan gudang individual di atas.</div>
        </div>
BLADE;
    $pos = strpos($s, $anchor);
    if ($pos === false) throw new RuntimeException('Could not locate Stock Grouping panel; no changes made');
    return substr_replace($s, $field."\n", $pos, 0);
});

echo "DONE. Run php artisan optimize:clear\n";
