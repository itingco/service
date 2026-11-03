<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\SalesDashboardReport;
use App\Exceptions\SalesDataException;
use App\Exports\SalesInsightsExport;
use App\Http\Requests\SalesDashboardRequest;
use App\Services\SalesDashboardInsightService;
use App\Services\SalesDashboardService;
use App\Services\SalesResearchInsightService;
use Illuminate\Http\RedirectResponse;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class SalesInsightsController extends Controller
{
    public function index(
        SalesDashboardRequest $request,
        SalesDashboardService $service,
        SalesDashboardInsightService $managementService,
        SalesResearchInsightService $researchService,
    ): View {
        $filter = $request->filter();
        $error = session('report_error');
        $previousDetails = [];

        try {
            $report = $service->generate($filter);
            $previousDetails = $service->detailsForPeriod(
                $filter,
                $filter->previousStartDate,
                $filter->previousEndDate,
            );
        } catch (SalesDataException $exception) {
            report($exception);
            $report = SalesDashboardReport::empty($filter);
            $error = $this->friendlyError($exception);
        }

        $insights = $managementService->build($report);
        $research = $researchService->build($report->detailRows, $previousDetails);

        return view('sales-insights.index', compact(
            'report',
            'filter',
            'error',
            'insights',
            'research',
        ));
    }

    public function exportExcel(
        SalesDashboardRequest $request,
        SalesDashboardService $service,
        SalesDashboardInsightService $managementService,
        SalesResearchInsightService $researchService,
    ): BinaryFileResponse|RedirectResponse {
        $filter = $request->filter();

        try {
            $report = $service->generate($filter);
            $previousDetails = $service->detailsForPeriod(
                $filter,
                $filter->previousStartDate,
                $filter->previousEndDate,
            );
            $management = $managementService->build($report);
            $research = $researchService->build($report->detailRows, $previousDetails);

            return Excel::download(
                new SalesInsightsExport($report, $management, $research),
                sprintf(
                    'Sales_Insights_RD_%s_%s_%s.xlsx',
                    strtoupper($filter->database),
                    strtoupper($filter->itemStatus),
                    $filter->selectedDate->format('Y-m-d'),
                ),
            );
        } catch (SalesDataException $exception) {
            report($exception);

            return redirect()
                ->route('sales-insights.index', [
                    'database' => $filter->database,
                    'end_date' => $filter->selectedDate->format('Y-m-d'),
                    'item_status' => $filter->itemStatus,
                ])
                ->with('report_error', $this->friendlyError($exception));
        }
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
