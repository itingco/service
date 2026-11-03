<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

final class BackgroundSyncCommandBuilder
{
    public function windows(string $php, string $artisan, int $jobId, string $log): string
    {
        return 'cmd /C start "" /B '.self::winQuote($php).' '.self::winQuote($artisan).' analytics:web-sync '.max(1,$jobId).' > '.self::winQuote($log).' 2>&1';
    }

    public function linux(string $php, string $artisan, int $jobId, string $log): string
    {
        return 'nohup '.escapeshellarg($php).' '.escapeshellarg($artisan).' analytics:web-sync '.max(1,$jobId).' > '.escapeshellarg($log).' 2>&1 &';
    }

    private static function winQuote(string $value): string
    {
        return '"'.str_replace('"', '\\"', $value).'"';
    }
}
