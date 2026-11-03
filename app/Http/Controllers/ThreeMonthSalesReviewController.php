<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\ThreeMonthSalesReviewFilter;
use App\Data\ThreeMonthSalesReviewReport;
use App\Exceptions\SalesDataException;
use App\Exports\ThreeMonthSalesReviewExport;
use App\Http\Requests\ThreeMonthSalesReviewRequest;
use App\Services\ThreeMonthSalesReviewService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class ThreeMonthSalesReviewController extends Controller
{
    public function index(ThreeMonthSalesReviewRequest $request, ThreeMonthSalesReviewService $service): View
    {
        $filter = $request->filter();
        $error = session('report_error');

        try {
            $report = $service->generate($filter);
        } catch (SalesDataException $exception) {
            report($exception);
            $report = ThreeMonthSalesReviewReport::empty($filter);
            $error = $this->friendlyError($exception);
        }

        return view('sales-review-3-months.index', compact('report', 'filter', 'error'));
    }

    public function exportExcel(
        ThreeMonthSalesReviewRequest $request,
        ThreeMonthSalesReviewService $service,
    ): BinaryFileResponse|RedirectResponse {
        $filter = $request->filter();

        try {
            $report = $service->generate($filter);

            return Excel::download(
                new ThreeMonthSalesReviewExport($report),
                $this->filename($filter, 'xlsx'),
            );
        } catch (SalesDataException $exception) {
            report($exception);

            return $this->redirectWithError($filter, $exception);
        }
    }

    public function exportPdf(
        ThreeMonthSalesReviewRequest $request,
        ThreeMonthSalesReviewService $service,
    ): Response|RedirectResponse {
        $filter = $request->filter();

        try {
            $report = $service->generate($filter);
            $pdf = Pdf::loadView('sales-review-3-months.pdf', compact('report', 'filter'))
                ->setPaper('a3', 'landscape');

            return $pdf->download($this->filename($filter, 'pdf'));
        } catch (SalesDataException $exception) {
            report($exception);

            return $this->redirectWithError($filter, $exception);
        }
    }

    private function filename(ThreeMonthSalesReviewFilter $filter, string $extension): string
    {
        return sprintf(
            'Sales_Review_3_Bulan_%s_%s_%s.%s',
            strtoupper($filter->database),
            strtoupper($filter->itemStatus),
            $filter->selectedDate->format('Y-m-d'),
            $extension,
        );
    }

    private function redirectWithError(
        ThreeMonthSalesReviewFilter $filter,
        SalesDataException $exception,
    ): RedirectResponse {
        return redirect()
            ->route('sales-review-3-months.index', [
                'database' => $filter->database,
                'end_date' => $filter->selectedDate->format('Y-m-d'),
                'item_status' => $filter->itemStatus,
            ])
            ->with('report_error', $this->friendlyError($exception));
    }

    private function friendlyError(SalesDataException $exception): string
    {
        return sprintf(
            'Review 3 bulan dari %s belum dapat dimuat. Periksa koneksi SQL Server dan kolom hasil dbo.fn_PenjualanLini. Detail: %s',
            $exception->source,
            $exception->getMessage(),
        );
    }
}
