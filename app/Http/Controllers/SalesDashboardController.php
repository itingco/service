<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\SalesDashboardFilter;
use App\Data\SalesDashboardReport;
use App\Exceptions\SalesDataException;
use App\Exports\SalesDashboardExport;
use App\Http\Requests\SalesDashboardDetailRequest;
use App\Http\Requests\SalesDashboardRequest;
use App\Services\SalesDashboardDetailQueryService;
use App\Services\SalesDashboardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class SalesDashboardController extends Controller
{
    public function index(SalesDashboardRequest $request, SalesDashboardService $service): View
    {
        $filter = $request->filter();
        $error = session('report_error');

        try {
            $report = $service->generate($filter);
        } catch (SalesDataException $exception) {
            report($exception);
            $report = SalesDashboardReport::empty($filter);
            $error = $this->friendlyError($exception);
        }

        return view('sales-dashboard.index', compact('report', 'filter', 'error'));
    }

    public function details(
        SalesDashboardDetailRequest $request,
        SalesDashboardService $service,
        SalesDashboardDetailQueryService $queryService,
    ): JsonResponse {
        $filter = $request->filter();

        try {
            $detailRows = $service->details($filter);
            $page = $queryService->paginate(
                rows: $detailRows,
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
        } catch (SalesDataException $exception) {
            report($exception);

            return response()->json([
                'message' => $this->friendlyError($exception),
            ], 503);
        }
    }

    public function exportExcel(
        SalesDashboardRequest $request,
        SalesDashboardService $service,
    ): BinaryFileResponse|RedirectResponse {
        $filter = $request->filter();

        try {
            $report = $service->generate($filter);

            return Excel::download(
                new SalesDashboardExport($report),
                $this->filename($filter, 'xlsx'),
            );
        } catch (SalesDataException $exception) {
            report($exception);

            return $this->redirectWithError($filter, $exception);
        }
    }

    public function exportPdf(
        SalesDashboardRequest $request,
        SalesDashboardService $service,
    ): Response|RedirectResponse {
        $filter = $request->filter();

        try {
            $report = $service->generate($filter);
            $pdf = Pdf::loadView('sales-dashboard.pdf', compact('report'))
                ->setPaper('a3', 'landscape');

            return $pdf->download($this->filename($filter, 'pdf'));
        } catch (SalesDataException $exception) {
            report($exception);

            return $this->redirectWithError($filter, $exception);
        }
    }

    private function filename(SalesDashboardFilter $filter, string $extension): string
    {
        return sprintf(
            'Sales_Dashboard_%s_%s_%s.%s',
            strtoupper($filter->database),
            strtoupper($filter->itemStatus),
            $filter->selectedDate->format('Y-m-d'),
            $extension,
        );
    }

    private function redirectWithError(
        SalesDashboardFilter $filter,
        SalesDataException $exception,
    ): RedirectResponse {
        return redirect()
            ->route('sales-dashboard.index', [
                'database' => $filter->database,
                'end_date' => $filter->selectedDate->format('Y-m-d'),
                'item_status' => $filter->itemStatus,
            ])
            ->with('report_error', $this->friendlyError($exception));
    }

    private function friendlyError(SalesDataException $exception): string
    {
        return sprintf(
            'Data %s belum dapat dimuat. Periksa koneksi SQL Server dan kolom hasil '.
            'dbo.fn_PenjualanLini. Detail: %s',
            $exception->source,
            $exception->getMessage(),
        );
    }
}
