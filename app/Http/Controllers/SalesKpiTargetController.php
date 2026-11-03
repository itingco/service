<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\SalesKpiDataException;
use App\Http\Requests\SalesKpiTargetUpdateRequest;
use App\Services\SalesKpi\SalesKpiTargetCatalogService;
use App\Services\SalesKpi\SalesKpiTargetService;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

final class SalesKpiTargetController extends Controller
{
    public function index(SalesKpiTargetCatalogService $catalogService): View
    {
        try {
            $rows = $catalogService->currentMonth(new DateTimeImmutable('today'));

            return view('sales-kpi.targets.index', compact('rows'));
        } catch (SalesKpiDataException $exception) {
            report($exception);

            return view('sales-kpi.targets.index', [
                'rows' => [],
                'error' => $this->combinedConnectionMessage($exception),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return view('sales-kpi.targets.index', [
                'rows' => [],
                'error' => "Pengaturan target belum dapat dibuka.\nDatabase konfigurasi lokal tidak tersedia.",
            ]);
        }
    }

    public function update(
        SalesKpiTargetUpdateRequest $request,
        SalesKpiTargetCatalogService $catalogService,
        SalesKpiTargetService $targetService,
    ): RedirectResponse {
        try {
            $rows = $catalogService->currentMonth(new DateTimeImmutable('today'));
            $allowed = array_fill_keys(array_map(static fn ($row): string => $row->lineKey, $rows), true);
            $targets = $request->normalizedTargets();

            foreach (array_keys($targets) as $lineKey) {
                if (!isset($allowed[$lineKey])) {
                    throw ValidationException::withMessages([
                        'targets' => "LiniPenjualan {$lineKey} tidak dikenal atau sudah tidak tersedia.",
                    ]);
                }
            }

            $targetService->saveBatch($targets);

            return redirect()
                ->route('sales-kpi.targets.index')
                ->with('status', 'Semua perubahan target berhasil disimpan.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (SalesKpiDataException $exception) {
            report($exception);

            return redirect()
                ->route('sales-kpi.targets.index')
                ->with('report_error', $this->combinedConnectionMessage($exception));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('sales-kpi.targets.index')
                ->with('report_error', "Pengaturan target belum dapat dibuka.\nDatabase konfigurasi lokal tidak tersedia.");
        }
    }

    private function combinedConnectionMessage(SalesKpiDataException $exception): string
    {
        $labels = array_values(array_unique(array_map(
            static fn (string $source): string => 'AS_'.strtoupper(trim($source)),
            $exception->failedSources(),
        )));

        return "Laporan KPI gabungan tidak dapat dimuat.\n\n"
            .'Koneksi ke database '.implode(' dan ', $labels)." gagal.\n"
            .'Data database lainnya tidak ditampilkan agar hasil gabungan tidak menyesatkan.';
    }
}
