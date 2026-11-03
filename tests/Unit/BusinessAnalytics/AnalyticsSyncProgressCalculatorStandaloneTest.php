<?php
require __DIR__.'/../../../app/Services/BusinessAnalytics/AnalyticsSyncProgressCalculator.php';
use App\Services\BusinessAnalytics\AnalyticsSyncProgressCalculator;
$c = new AnalyticsSyncProgressCalculator();
$s = $c->summarize(['status'=>'RUNNING','total_steps'=>72], 31, 30, 1, 0);
if ($s['status'] !== 'RUNNING' || $s['processed'] !== 31 || $s['percent'] !== 43) { fwrite(STDERR,"FAIL running\n"); exit(1); }
$s = $c->summarize(['status'=>'COMPLETED','total_steps'=>72],72,72,0,0);
if ($s['status'] !== 'COMPLETED' || $s['percent'] !== 100) { fwrite(STDERR,"FAIL completed\n"); exit(1); }
$s = $c->summarize(['status'=>'COMPLETED_WITH_ERRORS','total_steps'=>72],72,70,2,0);
if ($s['status'] !== 'COMPLETED_WITH_ERRORS' || $s['failed'] !== 2) { fwrite(STDERR,"FAIL errors\n"); exit(1); }
echo "PASS AnalyticsSyncProgressCalculator\n";
