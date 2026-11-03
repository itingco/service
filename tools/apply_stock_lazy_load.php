<?php
/** Stock Aging initial lazy load patch. Re-runnable, never touches USP. */
declare(strict_types=1);
$root = dirname(__DIR__);
if (!is_file($root.'/artisan')) { fwrite(STDERR, "Jalankan installer dari folder tools di root Laravel.\n"); exit(1); }
$controller = $root.'/app/Http/Controllers/StockAgingController.php';
$view = $root.'/resources/views/stock-aging/index.blade.php';
$landing = $root.'/resources/views/stock-aging/landing.blade.php';
if (!is_file($controller) || !is_file($view) || !is_file($landing)) { fwrite(STDERR,"Controller atau Blade tidak ditemukan. Salin folder resources dan tools dahulu.\n"); exit(1); }
function saveChange(string $file, string $old, string $new): void {
    if ($old === $new) { echo "SKIP ".basename($file)." (sudah ada)\n"; return; }
    if (!is_file($file.'.before-manual-load.bak')) copy($file, $file.'.before-manual-load.bak');
    if (file_put_contents($file, $new, LOCK_EX) === false) throw new RuntimeException("Gagal tulis {$file}");
    echo "UPDATED {$file}\n";
}
try {
    $s = file_get_contents($controller);
    if (!str_contains($s, 'STOCK-AGING-MANUAL-LOAD')) {
        if (!preg_match('/public function index\s*\(/', $s)) throw new RuntimeException('Method index() tidak ditemukan.');
        // Inject just after filter construction; zero stock, focus or warehouse SQL on first entry.
        $pattern = '/(public function index\s*\([^;]*?\)\s*:\s*[^\{]+\{\s*\$filter\s*=\s*\$request->filter\(\);)/s';
        $n=0;
        $s2 = preg_replace($pattern, '$1'."\n        // STOCK-AGING-MANUAL-LOAD: no ERP read until Apply is clicked.\n        if (!\$request->boolean('load') && !\$request->boolean('export_raw')) {\n            return view('stock-aging.landing', ['filter' => \$filter]);\n        }\n", $s, 1, $n);
        if ($n !== 1 || $s2 === null) throw new RuntimeException('Tidak menemukan letak aman untuk menyisipkan lazy load. Tidak ada file diubah.');
        $s = $s2;
        saveChange($controller, file_get_contents($controller), $s);
    } else { echo "SKIP controller (sudah dipasang)\n"; }
    $before = file_get_contents($view);
    $after=$before;
    if (!str_contains($after, 'STOCK-AGING-LOAD-FORM')) {
        $pattern = '/(<form\b(?=[^>]*\bid="stockFilterForm")[^>]*>)/s';
        $n=0;
        $after=preg_replace($pattern, '$1'."\n    {{-- STOCK-AGING-LOAD-FORM --}}\n    <input type=\"hidden\" name=\"load\" value=\"1\">", $after, 1, $n);
        if ($n !== 1 || $after === null) throw new RuntimeException('Form stockFilterForm belum ditemukan. Controller telah dibackup; silakan kirim Blade lokal untuk penyesuaian.');
    }
    // All raw Excel links must request loading explicitly even if user reaches report from shared link.
    if (!str_contains($after, 'STOCK-AGING-LOAD-EXPORT')) {
        if (str_contains($after, '$exportQuery')) {
            $after=str_replace("route('stock-aging.index', \$exportQuery)", "route('stock-aging.index', array_merge(\$exportQuery, ['load' => 1]))", $after);
        }
        $after="{{-- STOCK-AGING-LOAD-EXPORT --}}\n".$after;
    }
    saveChange($view, $before, $after);
    echo "OK. php artisan optimize:clear\n";
} catch(Throwable $e) { fwrite(STDERR, "ERROR: {$e->getMessage()}\n"); exit(1); }
