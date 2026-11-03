<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\CustomerAnalysisExport;
use App\Http\Requests\CustomerAnalysisRequest;
use App\Models\DashboardUser;
use App\Services\CustomerAnalysisService;
use App\Services\SalesPersonDirectoryService;
use DateTimeImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

final class CustomerAnalysisController extends Controller
{
    public function index(
        CustomerAnalysisRequest $request,
        CustomerAnalysisService $service,
        SalesPersonDirectoryService $salesPeople,
    ): View {
        /** @var DashboardUser $user */
        $user = $request->attributes->get('dashboard_user');
        $filters = $request->filters();
        $error = session('customer_analysis_error');

        try {
            $report = $service->overview($filters, $user);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $error = $this->friendlyError($e);
            $startDate = $filters['end_date']->modify('first day of this month')->modify('-2 months');
            $report = [
                'filters' => $filters,
                'sales_locked' => $user->isSales(),
                'period_start' => $startDate,
                'period_end' => $filters['end_date'],
                'summary' => [
                    'total_customers' => 0,
                    'active_customers' => 0,
                    'inactive_customers' => 0,
                    'sales_3m' => 0.0,
                ],
                'rows' => [],
                'pagination' => ['page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 0, 'from' => 0, 'to' => 0],
                'history_months' => (int) config('customer_analysis.history_months', 60),
            ];
        }

        $salesPersonOptions = $user->isSales()
            ? []
            : $salesPeople->options((string) $filters['database']);

        return view('customer-analysis.index', compact(
            'report',
            'filters',
            'salesPersonOptions',
            'error',
            'user',
        ));
    }

    public function exportExcel(
        CustomerAnalysisRequest $request,
        CustomerAnalysisService $service,
    ): BinaryFileResponse|RedirectResponse {
        /** @var DashboardUser $user */
        $user = $request->attributes->get('dashboard_user');
        $filters = $request->filters();

        try {
            $data = $service->exportData($filters, $user);

            return Excel::download(
                new CustomerAnalysisExport($data),
                sprintf(
                    'Customer_Analysis_%s_%s.xlsx',
                    strtoupper((string) $filters['database']),
                    $filters['end_date']->format('Y-m-d'),
                ),
            );
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('customer-analysis.index', $this->queryFilters($filters))
                ->with('customer_analysis_error', $this->friendlyError($e));
        }
    }

    public function show(
        Request $request,
        string $source,
        int $customerId,
        CustomerAnalysisService $service,
    ): View {
        /** @var DashboardUser $user */
        $user = $request->attributes->get('dashboard_user');
        $endDateText = trim((string) $request->query('end_date', now()->format('Y-m-d')));
        $endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $endDateText) ?: new DateTimeImmutable('today');

        try {
            $detail = $service->detail($source, $customerId, $endDate, $user);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            abort(503, $this->friendlyError($e));
        }

        return view('customer-analysis.show', compact('detail'));
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    private function queryFilters(array $filters): array
    {
        return [
            'database' => (string) ($filters['database'] ?? 'BOTH'),
            'status' => (string) ($filters['status'] ?? 'all'),
            'search' => (string) ($filters['search'] ?? ''),
            'sort' => (string) ($filters['sort'] ?? 'sales_desc'),
            'end_date' => $filters['end_date'] instanceof DateTimeImmutable
                ? $filters['end_date']->format('Y-m-d')
                : now()->format('Y-m-d'),
            'sales_person_id' => $filters['sales_person_id'] ?? null,
        ];
    }

    private function friendlyError(Throwable $e): string
    {
        return 'Data Customer Analysis belum dapat dimuat. Periksa koneksi SQL Server, tabel AR_Customers / AR_SalesPersons, '
            .'dan pastikan dbo.fn_PenjualanLini mengembalikan CustomerCode, CustomerName, InvoiceDate, InvoiceNumber, ItemCode, ItemName, Quantity, dan LineTotal. '
            .'Detail: '.$e->getMessage();
    }
}
