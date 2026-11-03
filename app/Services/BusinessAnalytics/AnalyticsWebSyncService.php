<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class AnalyticsWebSyncService
{
    public function __construct(
        private readonly AnalyticsSyncProgressCalculator $progress,
        private readonly BackgroundSyncCommandBuilder $commandBuilder,
    ) {}

    /** @return array<string,mixed>|null */
    public function latestStatus(): ?array
    {
        try {
            $schema = Schema::connection('sqlite_targets');
            if (!$schema->hasTable('business_analytics_web_sync_jobs')) {
                return null;
            }

            $db = DB::connection('sqlite_targets');
            $job = $db->table('business_analytics_web_sync_jobs')->orderByDesc('id')->first();
            if (!$job) return null;

            $a = (array) $job;
            if (($a['status'] ?? '') === 'RUNNING' && !empty($a['started_at']) && Carbon::parse((string) $a['started_at'])->lt(now()->subHours(6))) {
                $db->table('business_analytics_web_sync_jobs')->where('id', $a['id'])->update([
                    'status' => 'STALE',
                    'finished_at' => now(),
                    'message' => 'Job melewati batas 6 jam; boleh dijalankan ulang.',
                    'updated_at' => now(),
                ]);
                $a['status'] = 'STALE';
                $a['finished_at'] = now()->toDateTimeString();
            }

            $success = 0;
            $failed = 0;
            $running = 0;

            // Schema lama bisa memiliki web_sync_jobs tetapi belum mempunyai sync_runs.
            // Jangan buat halaman dashboard gagal hanya karena tabel progress belum ada.
            if ($schema->hasTable('business_analytics_sync_runs')) {
                $q = $db->table('business_analytics_sync_runs')->where('started_at', '>=', (string) $a['started_at']);
                if (!empty($a['finished_at'])) {
                    $q->where('started_at', '<=', (string) $a['finished_at']);
                }
                $rows = $q->get(['status']);
                foreach ($rows as $r) {
                    $s = strtoupper((string) $r->status);
                    if ($s === 'SUCCESS') $success++;
                    elseif ($s === 'FAILED') $failed++;
                    elseif ($s === 'RUNNING') $running++;
                }
            }

            return $this->progress->summarize($a, $success + $failed, $success, $failed, $running);
        } catch (Throwable) {
            // Status sync bersifat informasi tambahan. Error schema/SQLite tidak boleh
            // membuat halaman utama Business Analytics ikut HTTP 500.
            return null;
        }
    }

    /** @return array<string,mixed> */
    public function start(?int $userId = null): array
    {
        $this->assertSchemaReady();

        $current = $this->latestStatus();
        if ($current && ($current['status'] ?? '') === 'RUNNING') {
            throw new RuntimeException('Sync 36 bulan masih berjalan. Tunggu sampai selesai sebelum menjalankan ulang.');
        }

        $db = DB::connection('sqlite_targets');
        $now = now();
        $id = (int) $db->table('business_analytics_web_sync_jobs')->insertGetId([
            'status' => 'RUNNING',
            'total_steps' => 72,
            'requested_by_user_id' => $userId,
            'command' => 'php artisan analytics:sync --months=36',
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $log = storage_path('logs/business-analytics-web-sync-'.$id.'.log');
        $db->table('business_analytics_web_sync_jobs')->where('id', $id)->update([
            'log_path' => $log,
            'updated_at' => now(),
        ]);

        try {
            $this->launch($id, $log);
        } catch (Throwable $e) {
            $db->table('business_analytics_web_sync_jobs')->where('id', $id)->update([
                'status' => 'FAILED_TO_START',
                'finished_at' => now(),
                'message' => mb_substr($e->getMessage(), 0, 2000),
                'updated_at' => now(),
            ]);
            throw $e;
        }

        return $this->latestStatus() ?? [
            'id' => $id,
            'status' => 'RUNNING',
            'total_steps' => 72,
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'percent' => 0,
        ];
    }

    private function assertSchemaReady(): void
    {
        try {
            $schema = Schema::connection('sqlite_targets');
            $required = [
                'business_analytics_monthly_sales',
                'business_analytics_sync_runs',
                'business_analytics_web_sync_jobs',
            ];

            $missing = [];
            foreach ($required as $table) {
                if (!$schema->hasTable($table)) {
                    $missing[] = $table;
                }
            }

            if ($missing !== []) {
                throw new RuntimeException(
                    'Schema Business Analytics belum lengkap ('.implode(', ', $missing).'). '
                    .'Jalankan: php artisan migrate --database=sqlite_targets --force'
                );
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Database SQLite Business Analytics tidak dapat diperiksa: '.$e->getMessage(),
                previous: $e,
            );
        }
    }

    private function launch(int $jobId, string $log): void
    {
        $php = $this->phpBinary();
        $artisan = base_path('artisan');
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = $this->commandBuilder->windows($php, $artisan, $jobId, $log);
            if (!function_exists('popen')) {
                throw new RuntimeException('Fungsi popen dinonaktifkan; background sync tidak dapat dijalankan dari web.');
            }
            $h = @popen($cmd, 'r');
            if ($h === false) {
                throw new RuntimeException('Gagal menjalankan background process Windows.');
            }
            @pclose($h);
            return;
        }

        if (!function_exists('exec')) {
            throw new RuntimeException('Fungsi exec dinonaktifkan; background sync tidak dapat dijalankan dari web.');
        }
        $cmd = $this->commandBuilder->linux($php, $artisan, $jobId, $log);
        $out = [];
        $code = 0;
        @exec($cmd, $out, $code);
        if ($code !== 0) {
            throw new RuntimeException('Gagal memulai background process Linux (exit '.$code.').');
        }
    }

    private function phpBinary(): string
    {
        $configured = trim((string) env('BUSINESS_ANALYTICS_PHP_BINARY', ''));
        $candidates = array_filter([
            $configured,
            PHP_BINDIR.DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php'),
            PHP_BINARY,
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) return $candidate;
        }

        throw new RuntimeException('PHP CLI binary tidak ditemukan. Set BUSINESS_ANALYTICS_PHP_BINARY di .env.');
    }
}
