<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\BusinessAnalyticsRequest;
use App\Http\Requests\BusinessAnalyticsTargetRequest;
use App\Services\BusinessAnalytics\AnalyticsRepository;
use App\Services\BusinessAnalytics\BusinessAnalyticsService;
use App\Services\BusinessAnalytics\AnalyticsWebSyncService;
use App\Services\BusinessAnalytics\BusinessAnalyticsPdfPresenter;
use App\Services\BusinessAnalytics\TargetService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class BusinessAnalyticsController extends Controller
{
    public function index(
        BusinessAnalyticsRequest $request,
        AnalyticsRepository $repository,
        BusinessAnalyticsService $service,
        AnalyticsWebSyncService $syncService,
    ): View {
        $schemaReady = $this->analyticsSchemaReady();
        $latest = $schemaReady ? $repository->latestPeriod() : null;
        $filter = $request->filter($latest);
        $report = $schemaReady
            ? $service->build($filter)
            : $service->emptyReport($filter);

        // latestStatus() dibuat toleran terhadap schema lama/belum termigrasi,
        // sehingga halaman Business Analytics tetap dapat dibuka untuk repair.
        $syncStatus = $syncService->latestStatus();

        return view('business-analytics.index', compact('filter', 'report', 'syncStatus'));
    }

    public function saveTargets(
        BusinessAnalyticsTargetRequest $request,
        TargetService $service,
    ): RedirectResponse {
        if (!$this->analyticsSchemaReady()) {
            return back()->withErrors([
                'sync' => 'Schema Business Analytics belum lengkap. Jalankan migration sqlite_targets terlebih dahulu.',
            ]);
        }

        foreach ($request->normalizedTargets() as $period => $target) {
            $service->save(
                $period,
                (string) $request->validated('scope_level'),
                (string) $request->validated('source'),
                (string) $request->validated('dimension_key'),
                $target,
            );
        }

        return back()->with('status', 'Target Business Analytics berhasil disimpan.');
    }

    public function exportPdf(
        BusinessAnalyticsRequest $request,
        AnalyticsRepository $repository,
        BusinessAnalyticsService $service,
        BusinessAnalyticsPdfPresenter $presenter,
    ): Response {
        $this->abortIfAnalyticsSchemaNotReady();

        $latest = $repository->latestPeriod();
        $filter = $request->filter($latest);
        $report = $service->build($filter);
        $chartSvg = $presenter->trendSvg($report['trend'] ?? [], $report['forecast'] ?? []);
        $filename = 'Business_Analytics_'.$filter->endMonth.'_'.$filter->groupBy.'.pdf';

        return Pdf::loadView('business-analytics.pdf.business-analytics', compact('filter', 'report', 'presenter', 'chartSvg'))
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    public function exportCsv(
        BusinessAnalyticsRequest $request,
        AnalyticsRepository $repository,
        BusinessAnalyticsService $service,
    ): StreamedResponse {
        $this->abortIfAnalyticsSchemaNotReady();

        $latest = $repository->latestPeriod();
        $filter = $request->filter($latest);
        $report = $service->build($filter);
        $filename = 'Business_Analytics_'.$filter->endMonth.'_'.$filter->groupBy.'.csv';

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Level', 'Key', 'Label', 'Sales 3M', 'Quantity', 'Contribution', 'YoY Growth']);
            foreach ($report['breakdown'] as $row) {
                fputcsv($out, [
                    $report['filter']->groupBy,
                    $row['key'],
                    $row['label'],
                    $row['sales'],
                    $row['quantity'],
                    $row['contribution'],
                    $row['yoy_growth'],
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function analyticsSchemaReady(): bool
    {
        try {
            $schema = Schema::connection('sqlite_targets');

            return $schema->hasTable('business_analytics_monthly_sales')
                && $schema->hasTable('business_analytics_targets');
        } catch (Throwable) {
            return false;
        }
    }

    private function abortIfAnalyticsSchemaNotReady(): void
    {
        if ($this->analyticsSchemaReady()) {
            return;
        }

        abort(
            503,
            'Schema Business Analytics belum lengkap. Jalankan: php artisan migrate --database=sqlite_targets --force'
        );
    }
}
