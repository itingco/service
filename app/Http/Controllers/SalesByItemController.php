<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\SalesByItemFilter;
use App\Data\SalesByItemReport;
use App\Exceptions\SalesDataException;
use App\Exports\SalesByItemExport;
use App\Http\Requests\SalesByItemRequest;
use App\Services\SalesByItemService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class SalesByItemController extends Controller
{
    public function index(SalesByItemRequest $request, SalesByItemService $service): View
    {
        $filter = $request->filter();
        $error = session('report_error');

        try {
            $report = $service->generate($filter);
        } catch (SalesDataException $exception) {
            report($exception);
            $report = SalesByItemReport::empty($filter);
            $error = $this->friendlyError($exception);
        }

        return view('sales-by-item.index', compact('report', 'filter', 'error'));
    }

    public function exportExcel(
        SalesByItemRequest $request,
        SalesByItemService $service,
    ): BinaryFileResponse|RedirectResponse {
        $filter = $request->filter();

        try {
            $report = $service->generate($filter);
            return Excel::download(new SalesByItemExport($report), $this->filename($filter, 'xlsx'));
        } catch (SalesDataException $exception) {
            report($exception);
            return $this->redirectWithError($filter, $exception);
        }
    }

    public function exportPdf(
        SalesByItemRequest $request,
        SalesByItemService $service,
    ): Response|RedirectResponse {
        $filter = $request->filter();

        try {
            $report = $service->generate($filter);
            $pdf = Pdf::loadView('sales-by-item.pdf', compact('report'))
                ->setPaper('a4', 'landscape');

            return $pdf->download($this->filename($filter, 'pdf'));
        } catch (SalesDataException $exception) {
            report($exception);
            return $this->redirectWithError($filter, $exception);
        }
    }

    private function filename(SalesByItemFilter $filter, string $extension): string
    {
        return sprintf(
            'Sales_By_Item_%s_%s_%s.%s',
            strtoupper($filter->database),
            $filter->startDate->format('Y-m-d'),
            $filter->endDate->format('Y-m-d'),
            $extension,
        );
    }

    private function redirectWithError(
        SalesByItemFilter $filter,
        SalesDataException $exception,
    ): RedirectResponse {
        return redirect()
            ->route('sales-by-item.index', [
                'database' => $filter->database,
                'end_date' => $filter->endDate->format('Y-m-d'),
            ])
            ->with('report_error', $this->friendlyError($exception));
    }

    private function friendlyError(SalesDataException $exception): string
    {
        return sprintf(
            'Data %s belum dapat diambil. Periksa koneksi SQL Server, driver sqlsrv, '.
            'dan function dbo.fn_PenjualanLini. Detail: %s',
            $exception->source,
            $exception->getMessage(),
        );
    }
}
