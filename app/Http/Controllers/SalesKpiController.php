<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\SalesKpi\SalesKpiFilter;
use App\Data\SalesKpi\SalesKpiReport;
use App\Exceptions\SalesKpiDataException;
use App\Exports\SalesKpi\SalesKpiDetailExport;
use App\Exports\SalesKpi\SalesKpiExport;
use App\Http\Requests\SalesKpiDetailExportRequest;
use App\Http\Requests\SalesKpiDetailRequest;
use App\Http\Requests\SalesKpiRequest;
use App\Services\SalesKpi\SalesKpiDetailQueryService;
use App\Services\SalesKpi\SalesKpiPeriodService;
use App\Services\SalesKpi\SalesKpiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class SalesKpiController extends Controller
{
    public function index(
        SalesKpiRequest $request,
        SalesKpiService $service,
        SalesKpiPeriodService $periodService,
    ): View {
        $filter = $request->filter();
        $error = session('report_error');

        try {
            $report = $service->generate($filter);
        } catch (SalesKpiDataException $exception) {
            report($exception);
            $report = SalesKpiReport::empty(
                $filter,
                $periodService->make($filter->selectedDate),
            );
            $error = $this->friendlyError($filter, $exception);
        }

        return view('sales-kpi.index', compact('report', 'filter', 'error'));
    }

    public function details(
        SalesKpiDetailRequest $request,
        SalesKpiService $service,
        SalesKpiDetailQueryService $queryService,
    ): JsonResponse {
        $filter = $request->filter();

        try {
            $report = $service->generate($filter);
            $page = $queryService->paginate(
                rows: $report->detailRows,
                detailType: (string) $request->validated('detail_type'),
                search: (string) $request->validated('search', ''),
                orderColumn: (string) $request->validated('order_column'),
                orderDirection: (string) $request->validated('order_dir'),
                start: (int) $request->validated('start'),
                length: (int) $request->validated('length'),
            );

            return response()->json([
                'draw' => (int) $request->validated('draw'),
                'recordsTotal' => $page->recordsTotal,
                'recordsFiltered' => $page->recordsFiltered,
                'data' => $page->rows,
            ]);
        } catch (SalesKpiDataException $exception) {
            report($exception);

            return response()->json([
                'message' => $this->friendlyError($filter, $exception),
                'failed_databases' => array_map(
                    static fn (string $source): string => 'AS_'.$source,
                    $exception->failedSources(),
                ),
            ], 503);
        }
    }

    public function exportExcel(
        SalesKpiRequest $request,
        SalesKpiService $service,
    ): BinaryFileResponse|RedirectResponse {
        $filter = $request->filter();

        try {
            $report = $service->generate($filter);

            return Excel::download(
                new SalesKpiExport($report),
                sprintf(
                    'Sales_KPI_GABUNGAN_%s.xlsx',
                    $filter->selectedDate->format('Y-m-d'),
                ),
            );
        } catch (SalesKpiDataException $exception) {
            report($exception);

            return $this->redirectWithError($filter, $exception);
        }
    }


    public function exportDetailsExcel(
        SalesKpiDetailExportRequest $request,
        SalesKpiService $service,
        SalesKpiDetailQueryService $queryService,
    ): BinaryFileResponse|RedirectResponse {
        $filter = $request->filter();
        $detailType = (string) $request->validated('detail_type');
        $search = (string) $request->validated('detail_search', '');

        try {
            $report = $service->generate($filter);
            $rows = $queryService->filterAll($report->detailRows, $detailType, $search);

            return Excel::download(
                new SalesKpiDetailExport($report, $detailType, $rows, $search),
                sprintf(
                    'Sales_KPI_Detail_%s_%s.xlsx',
                    strtoupper($detailType),
                    $filter->selectedDate->format('Y-m-d'),
                ),
            );
        } catch (SalesKpiDataException $exception) {
            report($exception);

            return $this->redirectWithError($filter, $exception);
        }
    }

    public function exportPdf(
        SalesKpiRequest $request,
        SalesKpiService $service,
    ): Response|RedirectResponse {
        $filter = $request->filter();

        try {
            $report = $service->generate($filter);
            $pdf = Pdf::loadView('sales-kpi.pdf', compact('report'))
                ->setPaper('a3', 'landscape');

            return $pdf->download(sprintf(
                'Sales_KPI_GABUNGAN_%s.pdf',
                $filter->selectedDate->format('Y-m-d'),
            ));
        } catch (SalesKpiDataException $exception) {
            report($exception);

            return $this->redirectWithError($filter, $exception);
        }
    }

    private function redirectWithError(
        SalesKpiFilter $filter,
        SalesKpiDataException $exception,
    ): RedirectResponse {
        return redirect()
            ->route('sales-kpi.index', $filter->query())
            ->with('report_error', $this->friendlyError($filter, $exception));
    }

    private function friendlyError(
        SalesKpiFilter $filter,
        SalesKpiDataException $exception,
    ): string {
        if (str_starts_with($exception->getMessage(), 'Kolom ')) {
            return $exception->getMessage();
        }

        $labels = array_values(array_unique(array_map(
            static fn (string $source): string => 'AS_'.strtoupper(trim($source)),
            $exception->failedSources(),
        )));

        return "Laporan KPI gabungan tidak dapat dimuat.\n\n"
            .'Koneksi ke database '.implode(' dan ', $labels)." gagal.\n"
            .'Data database lainnya tidak ditampilkan agar hasil gabungan tidak menyesatkan.';
    }
}
