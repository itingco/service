<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

final class AnalyticsSyncProgressCalculator
{
    /** @param array<string,mixed> $job @return array<string,mixed> */
    public function summarize(array $job, int $processed, int $success, int $failed, int $running): array
    {
        $total = max(1, (int) ($job['total_steps'] ?? 72));
        $processed = max(0, min($total, $processed));
        return [
            ...$job,
            'total_steps' => $total,
            'processed' => $processed,
            'success' => max(0, $success),
            'failed' => max(0, $failed),
            'running_steps' => max(0, $running),
            'percent' => (int) round(($processed / $total) * 100),
        ];
    }
}
