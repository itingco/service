<?php
require __DIR__.'/../../../app/Services/BusinessAnalytics/BackgroundSyncCommandBuilder.php';
use App\Services\BusinessAnalytics\BackgroundSyncCommandBuilder;
$b = new BackgroundSyncCommandBuilder();
$w = $b->windows('C:\\xampp82\\php\\php.exe','C:\\xampp82\\htdocs\\sales-report-web\\artisan',12,'C:\\xampp82\\htdocs\\sales-report-web\\storage\\logs\\ba sync 12.log');
if (!str_contains($w,'analytics:web-sync 12') || !str_contains($w,'start "" /B')) { fwrite(STDERR,"FAIL windows\n"); exit(1); }
$l = $b->linux('/usr/bin/php','/var/www/sales-dashboard/artisan',12,'/var/www/sales-dashboard/storage/logs/ba-sync-12.log');
if (!str_contains($l,'nohup') || !str_contains($l,'analytics:web-sync') || !str_ends_with($l,' &')) { fwrite(STDERR,"FAIL linux\n"); exit(1); }
echo "PASS BackgroundSyncCommandBuilder\n";
