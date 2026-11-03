<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'sqlite_targets';

    public function up(): void
    {
        DB::connection('sqlite_targets')->transaction(function (): void {
            $rows = DB::connection('sqlite_targets')
                ->table('sales_kpi_targets')
                ->get(['id', 'sales_line', 'monthly_target', 'created_at', 'updated_at'])
                ->filter(static function (object $row): bool {
                    $compact = preg_replace('/[\s\-_.]+/', '', strtoupper(trim((string) $row->sales_line))) ?? '';

                    return in_array($compact, ['LAINLAIN', 'LAINNYA'], true);
                })
                ->values();

            if ($rows->isEmpty()) {
                return;
            }

            $canonical = $rows->first(static fn (object $row): bool => strtoupper(trim((string) $row->sales_line)) === 'LAINNYA');
            $withTarget = $rows->first(static fn (object $row): bool => $row->monthly_target !== null && (float) $row->monthly_target > 0.0);
            $source = $canonical ?? $withTarget ?? $rows->first();
            $target = $canonical?->monthly_target ?? $withTarget?->monthly_target ?? null;
            $ids = $rows->pluck('id')->all();

            DB::connection('sqlite_targets')->table('sales_kpi_targets')->whereIn('id', $ids)->delete();
            DB::connection('sqlite_targets')->table('sales_kpi_targets')->insert([
                'sales_line' => 'LAINNYA',
                'monthly_target' => $target,
                'created_at' => $source->created_at ?? now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Canonicalization is intentionally irreversible.
    }
};
