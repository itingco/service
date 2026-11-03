<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\SalesKpi\SalesKpiTransaction;
use App\Services\SalesKpi\SalesKpiDetailQueryService;
use PHPUnit\Framework\TestCase;

final class SalesKpiDetailQueryServiceTest extends TestCase
{
    public function test_it_filters_sorts_and_pages_without_losing_total_count(): void
    {
        $rows = [];
        for ($index = 1; $index <= 25; $index++) {
            $rows[] = $this->tx(
                database: $index % 2 === 0 ? 'AS_SMI' : 'AS_INGCO',
                itemName: $index <= 12 ? 'Hammer '.$index : 'Drill '.$index,
                total: (float) $index,
            );
        }

        $page = (new SalesKpiDetailQueryService())->paginate(
            rows: $rows,
            detailType: 'all',
            search: 'HAMMER',
            orderColumn: 'line_total',
            orderDirection: 'desc',
            start: 0,
            length: 10,
        );

        self::assertSame(25, $page->recordsTotal);
        self::assertSame(12, $page->recordsFiltered);
        self::assertCount(10, $page->rows);
        self::assertGreaterThanOrEqual($page->rows[1]['line_total'], $page->rows[0]['line_total']);
    }

    public function test_risk_and_grade_types_apply_their_own_filters(): void
    {
        $service = new SalesKpiDetailQueryService();
        $rows = [
            $this->tx('AS_INGCO', 'Risk', 100, grade: 'C', discontinued: true),
            $this->tx('AS_SMI', 'Grade', 200, grade: 'A'),
            $this->tx('AS_SMI', 'Other', 300, grade: 'C'),
        ];

        self::assertSame(1, $service->paginate($rows, 'risk', '', 'line_total', 'asc', 0, 10)->recordsFiltered);
        self::assertSame(1, $service->paginate($rows, 'grade', '', 'line_total', 'asc', 0, 10)->recordsFiltered);
    }

    private function tx(
        string $database,
        string $itemName,
        float $total,
        string $grade = 'A',
        bool $discontinued = false,
    ): SalesKpiTransaction {
        return new SalesKpiTransaction(
            database: $database,
            line: 'JAKARTA',
            lineKey: 'JAKARTA',
            lineTotal: $total,
            discontinued: $discontinued,
            clearance: false,
            grade: $grade,
            quantity: 1,
            invoiceDate: '2026-07-20',
            invoiceNumber: 'INV-'.(int) $total,
            itemCode: 'ITEM-'.(int) $total,
            itemName: $itemName,
            itemStatus: $discontinued ? 'DISCONTINUE' : 'ACTIVE',
            clearanceStatus: 'BUKAN CLEARANCE SALE',
            clearanceDiscount: '0',
        );
    }
}
