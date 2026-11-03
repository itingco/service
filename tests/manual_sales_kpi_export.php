<?php

declare(strict_types=1);

namespace Maatwebsite\Excel\Concerns {
    interface WithMultipleSheets {}
    interface FromArray {}
    interface ShouldAutoSize {}
    interface WithColumnFormatting {}
    interface WithStyles {}
    interface WithTitle {}
}

namespace {
    use App\Data\SalesKpi\SalesKpiFilter;
    use App\Data\SalesKpi\SalesKpiPeriod;
    use App\Data\SalesKpi\SalesKpiReport;
    use App\Data\SalesKpi\SalesKpiSourceContribution;
    use App\Data\SalesKpi\SalesKpiTransaction;
    use App\Data\SalesKpi\SalesLineKpi;
    use App\Exports\SalesKpi\SalesKpiExport;

    spl_autoload_register(static function (string $class): void {
        if (!str_starts_with($class, 'App\\')) {
            return;
        }

        $path = __DIR__.'/../app/'.str_replace('\\', '/', substr($class, 4)).'.php';
        if (is_file($path)) {
            require $path;
        }
    });

    $filter = SalesKpiFilter::from('2026-07-20', search: 'HAMMER');
    $period = new SalesKpiPeriod(
        new DateTimeImmutable('2026-07-01'),
        new DateTimeImmutable('2026-07-20'),
        new DateTimeImmutable('2026-07-01'),
        new DateTimeImmutable('2026-07-20'),
        new DateTimeImmutable('2026-06-01'),
        new DateTimeImmutable('2026-06-20'),
    );

    $ingco = new SalesKpiSourceContribution(
        database: 'AS_INGCO', target: null, targetConfigured: false,
        currentOmzet: 300_000_000.0, growthCurrentOmzet: 300_000_000.0,
        previousOmzet: 250_000_000.0, discontinuedOmzet: 20_000_000.0,
        clearanceOmzet: 15_000_000.0, gradeAOmzet: 120_000_000.0, gradeBOmzet: 70_000_000.0,
    );
    $smi = new SalesKpiSourceContribution(
        database: 'AS_SMI', target: null, targetConfigured: false,
        currentOmzet: 200_000_000.0, growthCurrentOmzet: 200_000_000.0,
        previousOmzet: 150_000_000.0, discontinuedOmzet: 10_000_000.0,
        clearanceOmzet: 5_000_000.0, gradeAOmzet: 80_000_000.0, gradeBOmzet: 30_000_000.0,
    );

    $line = new SalesLineKpi(
        line: 'JAKARTA', lineKey: 'JAKARTA', sources: ['AS_INGCO' => $ingco, 'AS_SMI' => $smi],
        targetConfigured: true, target: 1_000_000_000.0, currentOmzet: 500_000_000.0,
        growthCurrentOmzet: 500_000_000.0, previousOmzet: 400_000_000.0, growthRate: 0.25,
        discontinuedOmzet: 30_000_000.0, clearanceOmzet: 20_000_000.0,
        riskRealization: 50_000_000.0, riskTarget: 90_000_000.0,
        gradeAOmzet: 200_000_000.0, gradeBOmzet: 100_000_000.0,
        gradeAContribution: 0.4, gradeBContribution: 0.2,
        targetScore: 28.57, growthScore: 10.0, riskScore: 11.11,
        gradeAScore: 12.0, gradeBScore: 8.0, earnedPoints: 69.68,
        availableMaximum: 90.0, normalizedScore: 69.68, status: 'Perlu Perhatian',
    );

    $details = [];
    for ($i = 1; $i <= 25; $i++) {
        $details[] = new SalesKpiTransaction(
            database: $i % 2 === 0 ? 'AS_SMI' : 'AS_INGCO',
            line: 'JAKARTA', lineKey: 'JAKARTA', lineTotal: 1_000_000.0,
            discontinued: true, clearance: true, grade: 'A', quantity: 1.0,
            invoiceDate: '2026-07-20', invoiceNumber: sprintf('INV-%03d', $i),
            itemCode: sprintf('HAMMER-%03d', $i), itemName: 'HAMMER',
            itemStatus: 'DISCONTINUE', clearanceStatus: 'CLEARANCE SALE', clearanceDiscount: '0.2',
        );
    }

    $report = new SalesKpiReport(
        filter: $filter,
        period: $period,
        rows: [$line],
        detailRows: $details,
        summary: ['overall' => 69.68, 'target' => 28.57, 'growth' => 10.0, 'risk' => 11.11, 'grade' => 20.0],
    );

    $sheets = (new SalesKpiExport($report))->sheets();
    if (count($sheets) !== 6) {
        throw new RuntimeException('Combined Excel must contain exactly six sheets.');
    }

    $summaryRows = $sheets[0]->array();
    $expectedSummaryHeadings = [
        'LiniPenjualan', 'Target Gabungan',
        'Omzet AS_INGCO', 'Omzet AS_SMI', 'Omzet Gabungan',
        'Skor Target', 'Skor Growth', 'Skor Discontinued/Clearance',
        'Skor Grade A/B', 'Skor Akhir', 'Status',
    ];
    if (($summaryRows[6] ?? null) !== $expectedSummaryHeadings) {
        throw new RuntimeException('Combined summary headings do not match the approved design.');
    }
    if (!is_float($summaryRows[7][1] ?? null) || ($summaryRows[7][1] ?? null) !== 1_000_000_000.0) {
        throw new RuntimeException('Combined target must remain a numeric Excel value.');
    }

    $detailRows = $sheets[5]->array();
    if (($detailRows[6][0] ?? null) !== 'Database') {
        throw new RuntimeException('Database must be the first detail column.');
    }
    if (count($detailRows) - 7 !== 25) {
        throw new RuntimeException('Detail export must include every filtered row, independent of page length.');
    }
    if (($detailRows[7][0] ?? null) !== 'AS_INGCO') {
        throw new RuntimeException('Detail source label is missing.');
    }

    echo "manual_sales_kpi_export: OK\n";
}
