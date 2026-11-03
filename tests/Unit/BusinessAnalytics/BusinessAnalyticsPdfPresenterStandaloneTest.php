<?php
require __DIR__.'/../../../app/Services/BusinessAnalytics/BusinessAnalyticsPdfPresenter.php';
use App\Services\BusinessAnalytics\BusinessAnalyticsPdfPresenter;
$p = new BusinessAnalyticsPdfPresenter();
$svg = $p->trendSvg(['2026-01'=>100.0,'2026-02'=>200.0,'2026-03'=>150.0],[['period'=>'2026-04','forecast'=>180.0],['period'=>'2026-05','forecast'=>210.0]], 760, 220);
if (!str_contains($svg, '<svg') || !str_contains($svg, 'polyline')) { fwrite(STDERR,"FAIL svg missing\n"); exit(1); }
if (preg_match('/NaN|INF|Infinity/i',$svg)) { fwrite(STDERR,"FAIL invalid numeric svg\n"); exit(1); }
if ($p->money(null) !== '-') { fwrite(STDERR,"FAIL null money\n"); exit(1); }
if ($p->percent(0.1234) !== '12,3%') { fwrite(STDERR,"FAIL percent\n"); exit(1); }
echo "PASS BusinessAnalyticsPdfPresenter\n";
