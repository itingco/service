<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SalesDataSource;
use Tests\Fakes\FakeSalesDataSource;
use Tests\TestCase;

final class ProtectedReportRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);
        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource([
            'INGCO' => [],
            'SMI' => [],
        ]));
    }

    /** @dataProvider protectedRoutes */
    public function test_report_surface_redirects_unauthenticated_users(string $method, string $uri): void
    {
        $response = match ($method) {
            'PUT' => $this->put($uri),
            'POST' => $this->post($uri),
            default => $this->get($uri),
        };

        $response->assertRedirect('/login');
    }

    /** @return array<string,array{string,string}> */
    public static function protectedRoutes(): array
    {
        return [
            'root' => ['GET', '/'],
            'sales dashboard' => ['GET', '/sales-dashboard'],
            'sales dashboard details' => ['GET', '/sales-dashboard/details'],
            'sales dashboard excel' => ['GET', '/sales-dashboard/export/excel'],
            'sales dashboard pdf' => ['GET', '/sales-dashboard/export/pdf'],
            'sales insights' => ['GET', '/sales-insights'],
            'sales insights excel' => ['GET', '/sales-insights/export/excel'],
            'kpi dashboard' => ['GET', '/sales-kpi'],
            'kpi details' => ['GET', '/sales-kpi/details'],
            'kpi detail excel' => ['GET', '/sales-kpi/details/export/excel'],
            'kpi excel' => ['GET', '/sales-kpi/export/excel'],
            'kpi pdf' => ['GET', '/sales-kpi/export/pdf'],
            'target settings' => ['GET', '/sales-kpi/targets'],
            'target update' => ['PUT', '/sales-kpi/targets'],
            'security users' => ['GET', '/security/users'],
            'security devices' => ['GET', '/security/devices'],
            'security audits' => ['GET', '/security/audits'],
        ];
    }

    public function test_authenticated_navigation_contains_reports_targets_and_logout(): void
    {
        $this->actingAsDashboardAdmin()
            ->withViewErrors([])
            ->get('/sales-kpi/targets')
            ->assertSee('Dashboard Penjualan')
            ->assertSee('Dashboard KPI')
            ->assertSee('Pengaturan Target KPI')
            ->assertSee('Keamanan')
            ->assertSee('Keluar');
    }
}
