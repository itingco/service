<?php
/** Apply multi-select status to installed Laravel report files. Idempotent and backs up changed files. */
declare(strict_types=1);
$root = dirname(__DIR__);
if (!is_file($root.'/artisan')) { fwrite(STDERR, "Jalankan script dari dalam root Laravel/tools.\n"); exit(1); }
function change(string $path, callable $cb): void {
    global $root;
    $file = $root.'/'.$path;
    if (!is_file($file)) { throw new RuntimeException("File tidak ditemukan: {$path}"); }
    $before = file_get_contents($file);
    $after = $cb($before);
    if ($after === $before) { echo "SKIP {$path}\n"; return; }
    if (!is_string($after) || $after === '') throw new RuntimeException("Perubahan kosong: {$path}");
    copy($file, $file.'.before-multistatus.bak');
    file_put_contents($file, $after);
    echo "UPDATED {$path}\n";
}
function replaceFunction(string $s, string $method, string $body): string {
    // Functions patched here contain only plain PHP control-flow and no interpolated braces.
    $match = preg_match('/public function '.preg_quote($method,'/').'\s*\(/',$s,$m,PREG_OFFSET_CAPTURE);
    if (!$match) throw new RuntimeException("Tidak menemukan function {$method}");
    $start=strpos($s,'{',$m[0][1]); $level=0; $end=null;
    for ($i=$start,$len=strlen($s);$i<$len;$i++) {
        if($s[$i]==='{')$level++;
        elseif($s[$i]==='}' && --$level===0){$end=$i;break;}
    }
    if($end===null)throw new RuntimeException("Kurung function {$method} bermasalah");
    return substr($s,0,$start+1)."\n".$body."\n    ".substr($s,$end);
}
function normalizeValidation(string $s): string {
    if(str_contains($s,'// MULTISTATUS-PATCH')) return $s;
    $needle = "'item_status' => strtolower((string) \$this->input('item_status', 'all'))";
    if (!str_contains($s, $needle)) throw new RuntimeException('Normalisasi item_status tidak ditemukan pada Request');
    $replacement = "'item_status' => implode(',', array_values(array_unique(array_map('strtolower', array_filter(array_map('trim', (array) \$this->input('item_status', ['all'])))))))";
    $s = str_replace($needle, $replacement, $s);
    $s = preg_replace_callback("/('item_status'\\s*=>\\s*\\['required',\\s*)Rule::in\\(\\[[^\\]]+\\]\\)/", static fn ($m) => $m[1]."'regex:/^(all|normal|discontinued|discontinued_sale|clearance|reject)(,(normal|discontinued|discontinued_sale|clearance|reject))*$/D'", $s, 1, $n);
    if (!$n) throw new RuntimeException('Validasi item_status tidak ditemukan');
    return str_replace('    protected function prepareForValidation(): void',"    // MULTISTATUS-PATCH\n    protected function prepareForValidation(): void",$s);
}
function patchFilter(string $s,string $kind):string {
    if(str_contains($s,'// MULTISTATUS-FILTER'))return $s;
    $s=preg_replace("/private const ITEM_STATUSES = \[[^;]+;/","private const ITEM_STATUSES = ['all', 'normal', 'discontinued', 'discontinued_sale', 'clearance', 'reject'];",$s,1,$n);
    if(!$n)throw new RuntimeException('ITEM_STATUSES tidak ditemukan');
    $s=str_replace("if (!in_array(\$itemStatus, self::ITEM_STATUSES, true)) {","if (array_diff(explode(',', \$itemStatus), self::ITEM_STATUSES) !== [] || \$itemStatus === '') {",$s);
    if($kind==='stock') {
        $s=replaceFunction($s,'matchesItemStatus',<<<'PHP'
        $chosen = explode(',', $this->itemStatus);
        if (in_array('all', $chosen, true)) return true;
        $status = $itemReject ? 'reject' : ($discontinued ? ($hasDiscountRule ? 'discontinued_sale' : 'discontinued') : 'normal');
        return in_array($status, $chosen, true) || ($status === 'reject' && in_array('clearance', $chosen, true));
PHP);
    } else {
        $hasCategory=str_contains($s,'public function matchesCategory(');
        if($hasCategory) $s=replaceFunction($s,'matchesCategory',<<<'PHP'
        $chosen = explode(',', $this->itemStatus);
        if (in_array('all', $chosen, true)) return true;
        $status = match (strtoupper(trim($status))) {
            'NORMAL' => 'normal',
            'DISCONTINUE', 'DISCONTINUED' => 'discontinued',
            'DISCONTINUE + SALE' => 'discontinued_sale',
            'ITEM REJECT' => 'clearance',
            default => '',
        };
        return in_array($status, $chosen, true) || ($status === 'clearance' && in_array('reject', $chosen, true));
PHP);
        if(str_contains($s,'public function matchesItemStatus(')) $s=replaceFunction($s,'matchesItemStatus',<<<'PHP'
        $chosen = explode(',', $this->itemStatus);
        if (in_array('all', $chosen, true)) return true;
        $status = $clearance ? 'clearance' : ($discontinued ? 'discontinued' : 'normal');
        return in_array($status, $chosen, true) || ($status === 'clearance' && in_array('reject', $chosen, true));
PHP);
    }
    $labels="['normal'=>'Normal','discontinued'=>'Discontinue','discontinued_sale'=>'Discontinue + Sale','clearance'=>'Item Reject','reject'=>'Item Reject']";
    $s=replaceFunction($s,'itemStatusLabel',"        // MULTISTATUS-FILTER\n        \$selected = explode(',', \$this->itemStatus);\n        if (in_array('all', \$selected, true)) return 'Semua Status';\n        \$labels = {$labels};\n        return implode(' + ', array_values(array_unique(array_map(static fn (string \$key): string => \$labels[\$key] ?? \$key, \$selected))));");
    return $s;
}
function patchView(string $s,string $kind):string {
    if(str_contains($s,"@include('components.item-status-multiselect'"))return $s;
    $pattern='/<select\b[^>]*\bname="item_status"[^>]*>.*?<\/select>/s';
    $include=$kind==='stock' ? "@include('components.item-status-multiselect', ['filter' => \$filter, 'itemStatusRejectValue' => 'reject'])" : "@include('components.item-status-multiselect', ['filter' => \$filter])";
    $s=preg_replace($pattern,$include,$s,1,$n);
    if(!$n)throw new RuntimeException('Select status item tidak ditemukan di Blade');
    return $s;
}
try {
    foreach (['StockAgingRequest','SalesDashboardRequest','ThreeMonthSalesReviewRequest'] as $name)
        change("app/Http/Requests/{$name}.php",'normalizeValidation');
    foreach (['StockAgingFilter'=>'stock','SalesDashboardFilter'=>'sales','ThreeMonthSalesReviewFilter'=>'three'] as $name=>$kind)
        change("app/Data/{$name}.php",fn($s)=>patchFilter($s,$kind));
    foreach (['stock-aging/index.blade.php'=>'stock','sales-dashboard/index.blade.php'=>'sales','sales-insights/index.blade.php'=>'sales','sales-review-3-months/index.blade.php'=>'sales'] as $path=>$kind)
        change('resources/views/'.$path,fn($s)=>patchView($s,$kind));
    echo "Selesai. Jalankan php artisan optimize:clear\n";
} catch(Throwable $e) { fwrite(STDERR,"GAGAL: ".$e->getMessage()."\n"); exit(1); }
