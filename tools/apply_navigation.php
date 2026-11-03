<?php
/** Idempotent navigation installer: works with previously modified sidebar. */
declare(strict_types=1);
$root = dirname(__DIR__);
$layout = $root.'/resources/views/layouts/app.blade.php';
$routes = $root.'/routes/web.php';
foreach ([$layout, $routes] as $file) {
    if (!is_file($file) || !is_readable($file) || !is_writable($file)) {
        throw new RuntimeException("File missing/unwritable: {$file}");
    }
}
function saveIfChanged(string $file, string $before, string $after): void {
    if ($before === $after) { echo "Already applied: {$file}\n"; return; }
    $backup = $file.'.navigation-fix-'.date('Ymd-His').'.bak';
    if (!copy($file,$backup)) throw new RuntimeException("Failed to backup: {$file}");
    $tmp = $file.'.navigation-fix.tmp';
    if (file_put_contents($tmp, $after, LOCK_EX) === false || !rename($tmp,$file)) {
        @unlink($tmp);
        throw new RuntimeException("Failed to write: {$file}; backup: {$backup}");
    }
    echo "Updated: {$file}; backup: {$backup}\n";
}
function swapMenuBlock(string $content, string $permission, string $newBlock, string $expectedLink): string {
    // The sidebar groups use one-level @if ... @endif blocks; replace only the requested block.
    $re = '~^[ \t]*@if\s*\(\s*\$dashboardMenuAccess\[\'' . preg_quote($permission,'~') . '\'\]\s*\?\?\s*false\s*\)\s*\R.*?^[ \t]*@endif[ \t]*(?:\R|$)~ms';
    if (preg_match_all($re, $content, $m, PREG_OFFSET_CAPTURE) !== 1) {
        throw new RuntimeException("Sidebar block {$permission} missing or duplicated. No layout changes written.");
    }
    $old = $m[0][0][0];
    if (!str_contains($old, $expectedLink) && !str_contains($old, 'business-intelligence.index') && !str_contains($old, 'stock-aging.index')) {
        throw new RuntimeException("Sidebar block {$permission} is not recognized. No layout changes written.");
    }
    return substr_replace($content, $newBlock."\n", $m[0][0][1], strlen($old));
}
$routesBefore = file_get_contents($routes);
$routesAfter = $routesBefore;
if (!str_contains($routesBefore, "->name('business-intelligence.index')")) {
    $anchor="    Route::middleware('dashboard.permission:business_analytics')->group(function (): void {";
    if (substr_count($routesBefore,$anchor)!==1) throw new RuntimeException('Business Analytics route anchor missing/duplicate.');
    $addition="    Route::get('/business-intelligence', [\\App\\Http\\Controllers\\BusinessIntelligenceHubController::class, 'index'])\n".
              "        ->middleware('dashboard.permission:business_analytics')\n".
              "        ->name('business-intelligence.index');\n\n";
    $routesAfter=str_replace($anchor,$addition.$anchor,$routesBefore);
}
$layoutBefore = file_get_contents($layout);
$layoutAfter = $layoutBefore;
$stock = <<<'BLADE'
            @if ($dashboardMenuAccess['stock_aging'] ?? false)
                <a href="{{ route('stock-aging.index') }}" title="Stock &amp; Aging Review" class="sidebar-link {{ request()->routeIs('stock-aging.*') ? 'active' : '' }}"><i class="bi bi-box-seam sidebar-icon"></i><span class="nav-label">Stock &amp; Aging Review</span></a>
            @endif
BLADE;
$bi = <<<'BLADE'
            @if (($dashboardMenuAccess['business_analytics'] ?? false) || ($dashboardMenuAccess['sales_insights'] ?? false))
                <a href="{{ route('business-intelligence.index') }}" title="Business Intelligence &amp; R&amp;D" class="sidebar-link {{ request()->routeIs('business-intelligence.*') || request()->routeIs('business-analytics.*') || request()->routeIs('sales-insights.*') ? 'active' : '' }}"><i class="bi bi-bar-chart-line sidebar-icon"></i><span class="nav-label">Business Intelligence &amp; R&amp;D</span></a>
            @endif
BLADE;
// Stock block is either legacy two-link or already simplified one-link.
$layoutAfter=swapMenuBlock($layoutAfter,'stock_aging',$stock,"route('stock-aging.index')");
// Remove old R&D menu group if it still exists.
$salesRe = '~^[ \t]*@if\s*\(\s*\$dashboardMenuAccess\[\'sales_insights\'\]\s*\?\?\s*false\s*\)\s*\R.*?^[ \t]*@endif[ \t]*(?:\R|$)~ms';
$layoutAfter=preg_replace($salesRe,'',$layoutAfter,1,$salesCount);
if ($salesCount > 1) throw new RuntimeException('Duplicated sales insights menu.');
// Existing business analytics block, or already installed combined block.
$bizRe='~^[ \t]*@if\s*\(\s*\$dashboardMenuAccess\[\'business_analytics\'\]\s*\?\?\s*false\s*\)\s*\R.*?^[ \t]*@endif[ \t]*(?:\R|$)~ms';
$combinedRe='~^[ \t]*@if\s*\(\s*\(\$dashboardMenuAccess\[\'business_analytics\'\]\s*\?\?\s*false\).*?^[ \t]*@endif[ \t]*(?:\R|$)~ms';
$existingCount=preg_match_all($bizRe,$layoutAfter,$bm,PREG_OFFSET_CAPTURE);
$combinedCount=preg_match_all($combinedRe,$layoutAfter,$cm,PREG_OFFSET_CAPTURE);
if ($combinedCount===1 && $existingCount===0) {
    $old=$cm[0][0][0];
    $layoutAfter=substr_replace($layoutAfter,$bi."\n",$cm[0][0][1],strlen($old));
} elseif ($existingCount===1 && $combinedCount===0) {
    $old=$bm[0][0][0];
    $layoutAfter=substr_replace($layoutAfter,$bi."\n",$bm[0][0][1],strlen($old));
} else {
    throw new RuntimeException('Cannot identify unique BI menu block; no layout changes written.');
}
// Validate before writing either file.
if (substr_count($layoutAfter,"route('business-intelligence.index')")!==1 ||
    substr_count($layoutAfter,"route('stock-aging.index')")!==1 ||
    str_contains($layoutAfter,"route('stock-aging.strategy-review')")) {
    throw new RuntimeException('Post-patch sidebar validation failed; no changes written.');
}
saveIfChanged($routes,$routesBefore,$routesAfter);
saveIfChanged($layout,$layoutBefore,$layoutAfter);
echo "Navigation patch completed. Run php artisan optimize:clear.\n";
