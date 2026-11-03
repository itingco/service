<?php
/** Run from Laravel project root: php tools/apply_dashboard_patch.php */
declare(strict_types=1);

$root = getcwd();
if (!is_file($root.'/artisan')) { fwrite(STDERR, "Jalankan dari root Laravel (folder berisi artisan).\n"); exit(1); }

function applyChange(string $path, callable $modify): void {
    global $root;
    $file = $root.'/'.$path;
    if (!is_file($file)) throw new RuntimeException("File tidak ditemukan: $path");
    $before = file_get_contents($file);
    $after = $modify($before);
    if ($after === $before) { echo "SKIP $path (mungkin sudah dipatch)\n"; return; }
    file_put_contents($file.'.before-dashboard-classification.bak', $before);
    file_put_contents($file, $after);
    echo "OK $path\n";
}
function mustReplace(string $contents, string $from, string $to, string $path): string {
    if (!str_contains($contents, $from)) {
        if (str_contains($contents, $to)) return $contents;
        throw new RuntimeException("Marker tidak ditemukan di $path: ".substr($from, 0, 70));
    }
    return str_replace($from, $to, $contents);
}
try {
    applyChange('app/Http/Requests/SalesDashboardRequest.php', function ($s) {
        $p='SalesDashboardRequest.php';
        $s=mustReplace($s,"Rule::in(['all', 'ingco', 'smi'])","Rule::in(['all', 'ingco', 'wadfow', 'smi'])",$p);
        return mustReplace($s,"Rule::in(['all', 'normal', 'discontinued', 'clearance', 'both'])","Rule::in(['all', 'normal', 'discontinued', 'discontinued_sale', 'clearance'])",$p);
    });
    applyChange('app/Data/SalesDashboardFilter.php', function ($s) {
        $p='SalesDashboardFilter.php';
        $s=mustReplace($s,"['all', 'ingco', 'smi']","['all', 'ingco', 'wadfow', 'smi']",$p);
        $s=mustReplace($s,"['all', 'normal', 'discontinued', 'clearance', 'both']","['all', 'normal', 'discontinued', 'discontinued_sale', 'clearance']",$p);
        $s=mustReplace($s,"Database harus all, ingco, atau smi.","Database harus all, ingco, wadfow, atau smi.",$p);
        $s=mustReplace($s,"'smi' => ['SMI'],","'smi', 'wadfow' => ['SMI'],",$p);
        $s=mustReplace($s,"'smi' => 'SMI',","'smi' => 'SMI',\n            'wadfow' => 'WADFOW',",$p);
        $s=mustReplace($s,"default => 'INGCO & SMI',","default => 'INGCO + WADFOW + SMI',",$p);
        $s=mustReplace($s,"'discontinued' => 'ITEM DISCONTINUED',","'discontinued' => 'DISCONTINUE',",$p);
        $s=mustReplace($s,"'both' => 'DISCONTINUED & ITEM REJECT',","'discontinued_sale' => 'DISCONTINUE + SALE',",$p);
        $from="        return match (\$this->itemStatus) {\n            'normal' => !\$discontinued && !\$clearance,\n            'discontinued' => \$discontinued,\n            'clearance' => \$clearance,\n            'both' => \$discontinued && \$clearance,\n            default => true,\n        };";
        $to="        // Legacy method remains for integrations; use matchesCategory for the four mutually-exclusive statuses.\n        return \$this->matchesCategory(\$clearance ? 'ITEM REJECT' : (\$discontinued ? 'DISCONTINUE' : 'NORMAL'));";
        $s=mustReplace($s,$from,$to,$p);
        $needle="    private static function countNonSundayDays(";
        if (!str_contains($s,'public function matchesCategory(')) {
            $insert="    public function matchesCategory(string \$status): bool\n    {\n        return match (\$this->itemStatus) {\n            'normal' => \$status === 'NORMAL',\n            'discontinued' => \$status === 'DISCONTINUE',\n            'discontinued_sale' => \$status === 'DISCONTINUE + SALE',\n            'clearance' => \$status === 'ITEM REJECT',\n            default => true,\n        };\n    }\n\n    public function matchesBusiness(string \$business): bool\n    {\n        return \$this->database === 'all' || strtoupper(\$this->database) === strtoupper(\$business);\n    }\n\n";
            $s=mustReplace($s,$needle,$insert.$needle,$p);
        }
        return $s;
    });
    applyChange('app/Services/SalesDashboardService.php', function ($s) {
        $p='SalesDashboardService.php';
        $s=mustReplace($s,"private readonly SalesDashboardAggregator \$aggregator,","private readonly SalesDashboardAggregator \$aggregator,\n        private readonly SalesDashboardClassificationService \$classifier,",$p);
        $from="        \$normalizedRows = [];\n        foreach (\$rows as \$row) {";
        $to="        \$normalizedRows = [];\n        foreach (\$rows as \$row) {";
        // Preserve the existing normalization but classify after all rows have been normalized.
        if (!str_contains($s,'$this->classifier->classify(')) {
            $s=mustReplace($s,"            if (\$filter->matchesItemStatus(\$normalized['discontinued'], \$normalized['clearance'])) {\n                \$normalizedRows[] = \$normalized;\n            }", "            \$normalizedRows[] = \$normalized;",$p);
            $s=mustReplace($s,"        return \$normalizedRows;", "        \$classified = \$this->classifier->classify(\$normalizedRows, \$source);\n        return array_values(array_filter(\$classified, static fn (array \$row): bool =>\n            \$filter->matchesBusiness((string) \$row['database']) &&\n            \$filter->matchesCategory((string) \$row['item_status_category'])\n        ));",$p);
            // We read authoritative discontinue from IC_Items; reject from existing ERP source flag.
            $s=mustReplace($s,"        \$missingStatus = \$this->normalizer->missingColumnsForStatus(\$rows[0], \$filter->itemStatus);", "        \$missingStatus = []; // Status discontinue now comes from IC_Items metadata.",$p);
        }
        return $s;
    });
    applyChange('resources/views/sales-dashboard/index.blade.php', function ($s) {
        $p='sales-dashboard/index.blade.php';
        $from=<<<'OLD'
                <option value="all" @selected($filter->database === 'all')>INGCO &amp; SMI</option>
                <option value="ingco" @selected($filter->database === 'ingco')>INGCO — AS_INGCO</option>
                <option value="smi" @selected($filter->database === 'smi')>SMI — AS_SMI</option>
OLD;
        $to=<<<'NEW'
                <option value="all" @selected($filter->database === 'all')>Semua: INGCO + WADFOW + SMI</option>
                <option value="ingco" @selected($filter->database === 'ingco')>INGCO — AS_INGCO</option>
                <option value="wadfow" @selected($filter->database === 'wadfow')>WADFOW — AS_SMI (brand WADFOW)</option>
                <option value="smi" @selected($filter->database === 'smi')>SMI — AS_SMI (non-WADFOW)</option>
NEW;
        $s=mustReplace($s,$from,$to,$p);
        $from=<<<'OLD'
                <option value="all" @selected($filter->itemStatus === 'all')>Semua Item</option>
                <option value="normal" @selected($filter->itemStatus === 'normal')>Item Normal</option>
                <option value="discontinued" @selected($filter->itemStatus === 'discontinued')>Item Discontinued</option>
                <option value="clearance" @selected($filter->itemStatus === 'clearance')>Item Reject</option>
                <option value="both" @selected($filter->itemStatus === 'both')>Discontinued &amp; Item Reject</option>
OLD;
        $to=<<<'NEW'
                <option value="all" @selected($filter->itemStatus === 'all')>Semua Status</option>
                <option value="normal" @selected($filter->itemStatus === 'normal')>Normal</option>
                <option value="discontinued" @selected($filter->itemStatus === 'discontinued')>Discontinue</option>
                <option value="discontinued_sale" @selected($filter->itemStatus === 'discontinued_sale')>Discontinue + Sale</option>
                <option value="clearance" @selected($filter->itemStatus === 'clearance')>Item Reject</option>
NEW;
        return mustReplace($s,$from,$to,$p);
    });
    echo "Selesai. Jalankan php artisan optimize:clear, lalu test /sales-dashboard.\n";
} catch (Throwable $e) { fwrite(STDERR, "ERROR: ".$e->getMessage()."\n"); exit(1); }
