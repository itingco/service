<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MigrateProductStrategyToAppHub extends Command
{
    protected $signature = 'product-strategy:migrate-to-apphub
        {--dry-run : Hanya tampilkan jumlah data yang akan dipindahkan}
        {--clear-target : Kosongkan ItemFocus dan ItemSimilar sebelum copy}';

    protected $description = 'Copy Product Focus dan Similar Items lama dari SQLite ke DB_AppHub.';

    public function handle(): int
    {
        $sourceConnection = 'sqlite_targets';
        $targetConnection = (string) config('product_strategy.master_connection', 'sqlsrv_apphub');
        $focusTable = (string) config('product_strategy.focus_table', 'dbo.ItemFocus');
        $similarTable = (string) config('product_strategy.similar_table', 'dbo.ItemSimilar');

        $sourceSchema = Schema::connection($sourceConnection);
        $hasFocus = $sourceSchema->hasTable('product_focus_items');
        $hasSimilar = $sourceSchema->hasTable('similar_items');

        if (!$hasFocus && !$hasSimilar) {
            $this->warn('Tabel SQLite product_focus_items dan similar_items tidak ditemukan. Tidak ada data lama yang perlu dipindahkan.');
            return self::SUCCESS;
        }

        $focusCount = $hasFocus
            ? DB::connection($sourceConnection)->table('product_focus_items')->count()
            : 0;
        $similarCount = $hasSimilar
            ? DB::connection($sourceConnection)->table('similar_items')->count()
            : 0;

        $this->info("Sumber SQLite: {$focusCount} Product Focus, {$similarCount} Similar Items.");
        $this->line("Target: {$targetConnection} / {$focusTable} / {$similarTable}");

        if ((bool) $this->option('dry-run')) {
            $this->comment('Dry-run selesai. Tidak ada data yang diubah.');
            return self::SUCCESS;
        }

        $target = DB::connection($targetConnection);

        $target->transaction(function () use (
            $target,
            $sourceConnection,
            $focusTable,
            $similarTable,
            $hasFocus,
            $hasSimilar,
        ): void {
            if ((bool) $this->option('clear-target')) {
                $target->table($similarTable)->delete();
                $target->table($focusTable)->delete();
            }

            if ($hasFocus) {
                DB::connection($sourceConnection)
                    ->table('product_focus_items')
                    ->orderBy('id')
                    ->chunkById(500, function ($rows) use ($target, $focusTable): void {
                        foreach ($rows as $row) {
                            $itemCode = strtoupper(trim((string) ($row->item_code ?? '')));
                            $focusCategory = strtoupper(trim((string) ($row->focus_category ?? '')));

                            if ($itemCode === '' || $focusCategory === '') {
                                continue;
                            }

                            $target->table($focusTable)->updateOrInsert(
                                ['ItemCode' => $itemCode],
                                [
                                    'ItemFocusType' => $focusCategory,
                                    'Updated' => $row->updated_at ?? now(),
                                ],
                            );
                        }
                    });
            }

            if ($hasSimilar) {
                DB::connection($sourceConnection)
                    ->table('similar_items')
                    ->orderBy('id')
                    ->chunkById(500, function ($rows) use ($target, $similarTable): void {
                        foreach ($rows as $row) {
                            $itemCode = strtoupper(trim((string) ($row->item_code ?? '')));
                            $similarItemCode = strtoupper(trim((string) ($row->similar_item_code ?? '')));

                            if ($itemCode === '' || $similarItemCode === '' || $itemCode === $similarItemCode) {
                                continue;
                            }

                            $exists = $target->table($similarTable)
                                ->where('ItemCode', $itemCode)
                                ->where('SimilarItemCode', $similarItemCode)
                                ->exists();

                            if (!$exists) {
                                $target->table($similarTable)->insert([
                                    'ItemCode' => $itemCode,
                                    'SimilarItemCode' => $similarItemCode,
                                ]);
                            }
                        }
                    });
            }
        });

        $this->info('Migrasi Product Strategy ke DB_AppHub selesai. Data SQLite lama tidak dihapus.');
        return self::SUCCESS;
    }
}
