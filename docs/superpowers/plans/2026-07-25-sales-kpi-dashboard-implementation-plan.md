# Sales KPI Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a new `/sales-kpi` management dashboard that scores every `LiniPenjualan` from `dbo.fn_PenjualanLini` against the approved 90-point KPI model, with consistent web, Excel, and PDF outputs.

**Architecture:** Keep the existing `/sales-dashboard` feature unchanged. Add a separate KPI vertical slice with its own request, immutable data objects, exact-column row normalizer, period service, data service, calculator, controller, Blade page, local canvas charts, and exports. Reuse the existing `SalesDataSource` contract and SQL Server connection implementation so the new feature follows the current project pattern without duplicating credentials or connection logic.

**Tech Stack:** PHP 8.2+, Laravel 12, Blade, Bootstrap 5, SQL Server through `pdo_sqlsrv`, PHPUnit 11, Maatwebsite Excel 3.1, PhpSpreadsheet 1.30, barryvdh/laravel-dompdf 3.1, vanilla JavaScript canvas charts.

## Global Constraints

- The KPI system score ceiling is exactly 90 points; the external 10% daily-monitoring KPI is not stored, displayed, or exported.
- Group and score by the exact business field `LiniPenjualan`; `JAKARTA` and `GERAI KATAMSO` are sales-line values, not item categories.
- The KPI page accepts exactly one database at a time: `ingco` or `smi`; combined cross-database scoring remains out of scope.
- Targets are fixed per database and `LiniPenjualan` in `config/sales_kpi_targets.php`; no target-maintenance UI or monthly target table is added.
- Target Omzet score: `clamp((current omzet / target) / 0.70 * 40, 0, 40)`.
- Growth score: compare equal day counts with the prior month; `clamp(max(growth, 0) / 0.10 * 10, 0, 10)`; prior comparable omzet zero makes Growth unavailable.
- Discontinued/Clearance score: special target is 9% of line target; realization is discontinued omzet plus clearance omzet; a row with both statuses is intentionally counted twice; score is capped at 20.
- Grade A score uses 35% of current line omzet for 12 points; Grade B uses 20% for 8 points; current omzet zero makes both Grade components unavailable.
- Unavailable components are removed from the available maximum, then earned points are normalized back to the 90-point scale.
- Overall and component summary scores are weighted by configured positive line targets, not by a simple average.
- Every function-returned line remains visible. Missing-target lines show `Target belum diatur`, are normalized from available components, and are excluded from target-weighted totals.
- Detail-only filters never change KPI summary calculations.
- Existing `/sales-dashboard` routes, calculations, exports, and tests must continue to pass.
- Charts are rendered by a local `public/js/sales-kpi-charts.js` asset; do not add a Chart.js CDN dependency.

---

## File Structure Map

### Files to create

- `config/sales_kpi_targets.php` — fixed targets keyed by `AS_INGCO` or `AS_SMI`, then normalized `LiniPenjualan`.
- `app/Http/Requests/SalesKpiRequest.php` — validates score filters and detail-only filters.
- `app/Data/SalesKpi/SalesKpiFilter.php` — immutable validated filter values.
- `app/Data/SalesKpi/SalesKpiPeriod.php` — immutable full-current and equal-day Growth ranges.
- `app/Data/SalesKpi/SalesKpiTransaction.php` — exact normalized function row.
- `app/Data/SalesKpi/SalesLineKpi.php` — one fully calculated `LiniPenjualan` result.
- `app/Data/SalesKpi/SalesKpiReport.php` — page/export result, weighted summary, details, and chart payload.
- `app/Support/SalesKpiRowNormalizer.php` — exact verified field mapping and safe type conversion.
- `app/Services/SalesKpi/SalesKpiPeriodService.php` — date range construction.
- `app/Services/SalesKpi/SalesKpiDataService.php` — fetches current, comparable-current, and prior-comparable datasets and validates columns.
- `app/Services/SalesKpi/SalesKpiCalculator.php` — per-line formulas, unavailable handling, normalization, weighted totals, and detail filtering.
- `app/Services/SalesKpi/SalesKpiService.php` — orchestration boundary used by controllers and exports.
- `app/Exceptions/SalesKpiDataException.php` — safe source-aware KPI data failure.
- `app/Http/Controllers/SalesKpiController.php` — web, Excel, and PDF endpoints.
- `app/Exports/SalesKpi/SalesKpiExport.php` — six-sheet workbook coordinator.
- `app/Exports/SalesKpi/SalesKpiSheet.php` — reusable typed sheet implementation.
- `resources/views/sales-kpi/index.blade.php` — filters, five cards, five tabs, charts, summary tables, and detail tables.
- `resources/views/sales-kpi/pdf.blade.php` — management-focused PDF.
- `public/js/sales-kpi-charts.js` — local responsive canvas charts.
- `tests/Unit/SalesKpiPeriodServiceTest.php`
- `tests/Unit/SalesKpiRowNormalizerTest.php`
- `tests/Unit/SalesKpiCalculatorTest.php`
- `tests/Unit/SalesKpiDataServiceTest.php`
- `tests/Unit/SalesKpiServiceTest.php`
- `tests/Feature/SalesKpiPageTest.php`
- `tests/Feature/SalesKpiExportTest.php`

### Files to modify

- `app/Providers/AppServiceProvider.php` — bind the KPI calculator with configured targets while retaining existing bindings.
- `routes/web.php` — add index, Excel, and PDF routes without changing existing routes.
- `resources/views/layouts/app.blade.php` — add navigation links for Sales Dashboard and KPI Dashboard plus reusable KPI status styles.
- `README.md` — document KPI URL, target configuration, formulas, and local run commands.
- `docs/COLUMN-MAPPING.md` — record the exact KPI columns from the verified workbook sample.

### Files explicitly left unchanged

- `app/Services/SalesDashboardAggregator.php`
- `app/Support/SalesDashboardRowNormalizer.php`
- `app/Http/Controllers/SalesDashboardController.php`
- `resources/views/sales-dashboard/*`

This isolation prevents the new exact KPI mapping from changing the behavior of the current fuzzy sales-dashboard mapping.

---

### Task 1: KPI Filter, Period Model, and Target Configuration

**Files:**
- Create: `config/sales_kpi_targets.php`
- Create: `app/Data/SalesKpi/SalesKpiFilter.php`
- Create: `app/Data/SalesKpi/SalesKpiPeriod.php`
- Create: `app/Http/Requests/SalesKpiRequest.php`
- Create: `app/Services/SalesKpi/SalesKpiPeriodService.php`
- Test: `tests/Unit/SalesKpiPeriodServiceTest.php`
- Test: `tests/Feature/SalesKpiPageTest.php`

**Interfaces:**
- Produces: `SalesKpiFilter::from(string $database, string $endDate, ?string $line, string $discontinue, string $clearance, string $grade, string $search): SalesKpiFilter`
- Produces: `SalesKpiFilter::source(): string` returning `INGCO` or `SMI`.
- Produces: `SalesKpiFilter::databaseKey(): string` returning `AS_INGCO` or `AS_SMI`.
- Produces: `SalesKpiPeriodService::make(DateTimeImmutable $selectedDate): SalesKpiPeriod`.
- Produces: `SalesKpiRequest::filter(): SalesKpiFilter`.
- Consumes later: controller, data service, calculator, view, Excel, and PDF.

- [ ] **Step 1: Add period tests that encode the approved equal-day rule**

Create `tests/Unit/SalesKpiPeriodServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SalesKpi\SalesKpiPeriodService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SalesKpiPeriodServiceTest extends TestCase
{
    public function test_march_31_uses_full_month_for_non_growth_and_equal_days_for_growth(): void
    {
        $period = (new SalesKpiPeriodService())->make(new DateTimeImmutable('2026-03-31'));

        self::assertSame('2026-03-01', $period->currentStart->format('Y-m-d'));
        self::assertSame('2026-03-31', $period->currentEnd->format('Y-m-d'));
        self::assertSame('2026-03-01', $period->growthCurrentStart->format('Y-m-d'));
        self::assertSame('2026-03-28', $period->growthCurrentEnd->format('Y-m-d'));
        self::assertSame('2026-02-01', $period->growthPreviousStart->format('Y-m-d'));
        self::assertSame('2026-02-28', $period->growthPreviousEnd->format('Y-m-d'));
    }

    public function test_equal_length_month_reuses_selected_day(): void
    {
        $period = (new SalesKpiPeriodService())->make(new DateTimeImmutable('2026-07-20'));

        self::assertSame('2026-07-20', $period->growthCurrentEnd->format('Y-m-d'));
        self::assertSame('2026-06-20', $period->growthPreviousEnd->format('Y-m-d'));
        self::assertFalse($period->requiresSeparateGrowthCurrentQuery());
    }
}
```

- [ ] **Step 2: Run the period test and confirm the classes do not exist yet**

Run:

```bash
php artisan test tests/Unit/SalesKpiPeriodServiceTest.php
```

Expected: FAIL because `SalesKpiPeriodService` and `SalesKpiPeriod` are not defined.

- [ ] **Step 3: Implement immutable period data and exact date construction**

Create `app/Data/SalesKpi/SalesKpiPeriod.php`:

```php
<?php

declare(strict_types=1);

namespace App\Data\SalesKpi;

use DateTimeImmutable;

final class SalesKpiPeriod
{
    public function __construct(
        public readonly DateTimeImmutable $currentStart,
        public readonly DateTimeImmutable $currentEnd,
        public readonly DateTimeImmutable $growthCurrentStart,
        public readonly DateTimeImmutable $growthCurrentEnd,
        public readonly DateTimeImmutable $growthPreviousStart,
        public readonly DateTimeImmutable $growthPreviousEnd,
    ) {
    }

    public function requiresSeparateGrowthCurrentQuery(): bool
    {
        return $this->growthCurrentEnd != $this->currentEnd;
    }
}
```

Create `app/Services/SalesKpi/SalesKpiPeriodService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\SalesKpi;

use App\Data\SalesKpi\SalesKpiPeriod;
use DateTimeImmutable;

final class SalesKpiPeriodService
{
    public function make(DateTimeImmutable $selectedDate): SalesKpiPeriod
    {
        $currentStart = $selectedDate->modify('first day of this month');
        $previousStart = $currentStart->modify('-1 month');
        $previousEndOfMonth = $previousStart->modify('last day of this month');
        $comparableDay = min(
            (int) $selectedDate->format('j'),
            (int) $previousEndOfMonth->format('j'),
        );

        $growthCurrentEnd = $currentStart->setDate(
            (int) $currentStart->format('Y'),
            (int) $currentStart->format('n'),
            $comparableDay,
        );
        $growthPreviousEnd = $previousStart->setDate(
            (int) $previousStart->format('Y'),
            (int) $previousStart->format('n'),
            $comparableDay,
        );

        return new SalesKpiPeriod(
            currentStart: $currentStart,
            currentEnd: $selectedDate,
            growthCurrentStart: $currentStart,
            growthCurrentEnd: $growthCurrentEnd,
            growthPreviousStart: $previousStart,
            growthPreviousEnd: $growthPreviousEnd,
        );
    }
}
```

- [ ] **Step 4: Run the period tests**

Run:

```bash
php artisan test tests/Unit/SalesKpiPeriodServiceTest.php
```

Expected: PASS, 2 tests.

- [ ] **Step 5: Add filter tests to the KPI feature test file**

Create `tests/Feature/SalesKpiPageTest.php` with the validation-only tests first:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class SalesKpiPageTest extends TestCase
{
    public function test_invalid_database_is_rejected(): void
    {
        $response = $this->get('/sales-kpi?database=all&end_date=2026-07-20');

        $response->assertSessionHasErrors('database');
    }

    public function test_invalid_date_is_rejected(): void
    {
        $response = $this->get('/sales-kpi?database=ingco&end_date=20-07-2026');

        $response->assertSessionHasErrors('end_date');
    }
}
```

The route does not exist yet, so these tests will initially fail with 404; they will become green in Task 6 after the controller and route are added.

- [ ] **Step 6: Implement `SalesKpiFilter` and `SalesKpiRequest`**

Create `app/Data/SalesKpi/SalesKpiFilter.php` with these public fields and methods:

```php
public function __construct(
    public readonly string $database,
    public readonly DateTimeImmutable $selectedDate,
    public readonly ?string $line,
    public readonly string $discontinue,
    public readonly string $clearance,
    public readonly string $grade,
    public readonly string $search,
) {}

public function source(): string
{
    return $this->database === 'ingco' ? 'INGCO' : 'SMI';
}

public function databaseKey(): string
{
    return $this->database === 'ingco' ? 'AS_INGCO' : 'AS_SMI';
}

public function databaseLabel(): string
{
    return $this->databaseKey();
}
```

`SalesKpiFilter::from()` must:

- accept only `ingco` and `smi`;
- parse `Y-m-d` strictly with `DateTimeImmutable::createFromFormat('!Y-m-d', ...)`;
- normalize `line` with `trim`, converting empty to `null` but preserving display text;
- accept `discontinue` values `all`, `yes`, `no`;
- accept `clearance` values `all`, `yes`, `no`;
- normalize grade to uppercase and accept `ALL`, `A`, `B`, `C`, `D`, `E`, `N`, `BLANK`;
- trim search text.

Create `app/Http/Requests/SalesKpiRequest.php` with these rules:

```php
return [
    'database' => ['required', Rule::in(['ingco', 'smi'])],
    'end_date' => ['required', 'date_format:Y-m-d'],
    'line' => ['nullable', 'string', 'max:150'],
    'discontinue' => ['required', Rule::in(['all', 'yes', 'no'])],
    'clearance' => ['required', Rule::in(['all', 'yes', 'no'])],
    'grade' => ['required', Rule::in(['ALL', 'A', 'B', 'C', 'D', 'E', 'N', 'BLANK'])],
    'search' => ['nullable', 'string', 'max:150'],
];
```

Defaults in `prepareForValidation()`:

```php
'database' => strtolower((string) $this->input('database', 'ingco')),
'end_date' => (string) $this->input('end_date', now()->format('Y-m-d')),
'line' => trim((string) $this->input('line', '')),
'discontinue' => strtolower((string) $this->input('discontinue', 'all')),
'clearance' => strtolower((string) $this->input('clearance', 'all')),
'grade' => strtoupper((string) $this->input('grade', 'all')),
'search' => trim((string) $this->input('search', '')),
```

- [ ] **Step 7: Add the approved target configuration**

Create `config/sales_kpi_targets.php`:

```php
<?php

return [
    'AS_INGCO' => [
        'JAKARTA' => 500_000_000,
        'GERAI KATAMSO' => 300_000_000,
    ],
    'AS_SMI' => [
        'JAKARTA' => 500_000_000,
        'GERAI KATAMSO' => 300_000_000,
    ],
];
```

- [ ] **Step 8: Format and commit Task 1**

Run:

```bash
vendor/bin/pint app/Data/SalesKpi app/Http/Requests/SalesKpiRequest.php app/Services/SalesKpi config/sales_kpi_targets.php tests/Unit/SalesKpiPeriodServiceTest.php tests/Feature/SalesKpiPageTest.php
php artisan test tests/Unit/SalesKpiPeriodServiceTest.php
```

Expected: period tests PASS. The feature file is intentionally not run until Task 6 adds `/sales-kpi`.

Commit:

```bash
git add config/sales_kpi_targets.php app/Data/SalesKpi app/Http/Requests/SalesKpiRequest.php app/Services/SalesKpi/SalesKpiPeriodService.php tests/Unit/SalesKpiPeriodServiceTest.php tests/Feature/SalesKpiPageTest.php
git commit -m "feat: add sales KPI filters and periods"
```

---

### Task 2: Exact KPI Row Normalization and Period Data Loading

**Files:**
- Create: `app/Data/SalesKpi/SalesKpiTransaction.php`
- Create: `app/Support/SalesKpiRowNormalizer.php`
- Create: `app/Exceptions/SalesKpiDataException.php`
- Create: `app/Services/SalesKpi/SalesKpiDataService.php`
- Test: `tests/Unit/SalesKpiRowNormalizerTest.php`
- Modify: `tests/Fakes/FakeSalesDataSource.php` only if call capture is required.

**Interfaces:**
- Consumes: `SalesDataSource::fetch(string $source, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array`.
- Consumes: `SalesKpiFilter::source()` and `SalesKpiPeriod` from Task 1.
- Produces: `SalesKpiRowNormalizer::missingRequiredColumns(object|array $row): list<string>`.
- Produces: `SalesKpiRowNormalizer::normalize(object|array $row): SalesKpiTransaction`.
- Produces: `SalesKpiDataService::load(SalesKpiFilter $filter, SalesKpiPeriod $period): array{current:list<SalesKpiTransaction>,growth_current:list<SalesKpiTransaction>,growth_previous:list<SalesKpiTransaction>}`.

- [ ] **Step 1: Write normalization tests using the verified workbook columns**

Create `tests/Unit/SalesKpiRowNormalizerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SalesKpiRowNormalizer;
use PHPUnit\Framework\TestCase;

final class SalesKpiRowNormalizerTest extends TestCase
{
    public function test_it_normalizes_the_verified_function_columns(): void
    {
        $row = (new SalesKpiRowNormalizer())->normalize((object) [
            'InvoiceDate' => '2026-07-20',
            'InvoiceNumber' => 'INV-001',
            'LiniPenjualan' => ' Gerai Katamso ',
            'ItemCode' => 'ABC01',
            'ItemName' => 'Hammer',
            'Quantity' => '2',
            'LineTotal' => '1.250.000',
            'Discontinue' => '1',
            'StatusItem' => 'DISCONTINUE',
            'Grade' => ' a ',
            'StatusClearanceSale' => ' clearance sale ',
            'ClearanceSaleDiscount' => '0.2',
        ]);

        self::assertSame('GERAI KATAMSO', $row->lineKey);
        self::assertSame('Gerai Katamso', $row->line);
        self::assertSame(1_250_000.0, $row->lineTotal);
        self::assertTrue($row->discontinued);
        self::assertTrue($row->clearance);
        self::assertSame('A', $row->grade);
        self::assertSame(2.0, $row->quantity);
    }

    public function test_clearance_discount_alone_does_not_mark_clearance(): void
    {
        $row = (new SalesKpiRowNormalizer())->normalize((object) [
            'LiniPenjualan' => 'JAKARTA',
            'LineTotal' => 100,
            'Discontinue' => 0,
            'Grade' => 'B',
            'StatusClearanceSale' => 'BUKAN CLEARANCE SALE',
            'ClearanceSaleDiscount' => 0.5,
        ]);

        self::assertFalse($row->clearance);
    }

    public function test_it_reports_each_missing_scoring_column_by_real_name(): void
    {
        $missing = (new SalesKpiRowNormalizer())->missingRequiredColumns((object) [
            'LiniPenjualan' => 'JAKARTA',
            'LineTotal' => 100,
        ]);

        self::assertSame(['Discontinue', 'StatusClearanceSale', 'Grade'], $missing);
    }
}
```

- [ ] **Step 2: Run the normalizer tests and verify failure**

Run:

```bash
php artisan test tests/Unit/SalesKpiRowNormalizerTest.php
```

Expected: FAIL because the KPI normalizer and transaction object do not exist.

- [ ] **Step 3: Implement `SalesKpiTransaction`**

Create `app/Data/SalesKpi/SalesKpiTransaction.php` with this constructor:

```php
public function __construct(
    public readonly string $line,
    public readonly string $lineKey,
    public readonly float $lineTotal,
    public readonly bool $discontinued,
    public readonly bool $clearance,
    public readonly string $grade,
    public readonly float $quantity,
    public readonly string $invoiceDate,
    public readonly string $invoiceNumber,
    public readonly string $itemCode,
    public readonly string $itemName,
    public readonly string $itemStatus,
    public readonly string $clearanceStatus,
    public readonly string $clearanceDiscount,
) {}
```

- [ ] **Step 4: Implement exact-column normalization**

Create `app/Support/SalesKpiRowNormalizer.php` with these exact required columns:

```php
private const REQUIRED = [
    'LiniPenjualan',
    'LineTotal',
    'Discontinue',
    'StatusClearanceSale',
    'Grade',
];
```

Implementation rules:

```php
$lineDisplay = trim((string) ($values['LiniPenjualan'] ?? ''));
$lineKey = strtoupper(preg_replace('/\s+/', ' ', $lineDisplay) ?? $lineDisplay);
$clearanceStatus = strtoupper(trim((string) ($values['StatusClearanceSale'] ?? '')));
$grade = strtoupper(trim((string) ($values['Grade'] ?? '')));
```

Use a private `toFloat(mixed $value): float` that converts:

- native numeric values directly;
- `1.250.000` to `1250000`;
- `1,250,000.50` to `1250000.50`;
- empty or `NULL` text to `0.0`.

Set:

```php
$discontinued = $this->toFloat($values['Discontinue'] ?? 0) === 1.0;
$clearance = $clearanceStatus === 'CLEARANCE SALE';
```

Do not infer clearance from `ClearanceSaleDiscount`.

- [ ] **Step 5: Run the normalizer tests**

Run:

```bash
php artisan test tests/Unit/SalesKpiRowNormalizerTest.php
```

Expected: PASS, 3 tests.

- [ ] **Step 6: Implement the KPI data exception and data service**

Create `app/Exceptions/SalesKpiDataException.php`:

```php
final class SalesKpiDataException extends RuntimeException
{
    public function __construct(
        public readonly string $source,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
```

Create `app/Services/SalesKpi/SalesKpiDataService.php` with constructor:

```php
public function __construct(
    private readonly SalesDataSource $dataSource,
    private readonly SalesKpiRowNormalizer $normalizer,
) {}
```

`load()` must:

1. fetch full current period;
2. reuse full current rows for Growth when `requiresSeparateGrowthCurrentQuery()` is false;
3. otherwise fetch the truncated Growth current period;
4. fetch the prior comparable period;
5. validate the first row of every non-empty dataset with `missingRequiredColumns()`;
6. normalize every row;
7. wrap SQL and normalization failures in `SalesKpiDataException` with source `INGCO` or `SMI`.

The return shape is exact:

```php
return [
    'current' => $current,
    'growth_current' => $growthCurrent,
    'growth_previous' => $growthPrevious,
];
```

- [ ] **Step 7: Add a data-service query-count test**

Create `tests/Unit/SalesKpiDataServiceTest.php`. Use an anonymous `SalesDataSource` that records calls and returns one valid row. Assert:

- July 20 makes two SQL fetches: current July 1–20 and previous June 1–20, reusing current for Growth.
- March 31 makes three SQL fetches: current March 1–31, Growth current March 1–28, and Growth previous February 1–28.

Use exact assertions on formatted start and end dates.

- [ ] **Step 8: Run Task 2 tests, format, and commit**

Run:

```bash
vendor/bin/pint app/Data/SalesKpi/SalesKpiTransaction.php app/Support/SalesKpiRowNormalizer.php app/Exceptions/SalesKpiDataException.php app/Services/SalesKpi/SalesKpiDataService.php tests/Unit/SalesKpiRowNormalizerTest.php tests/Unit/SalesKpiDataServiceTest.php
php artisan test tests/Unit/SalesKpiRowNormalizerTest.php tests/Unit/SalesKpiDataServiceTest.php
```

Expected: PASS.

Commit:

```bash
git add app/Data/SalesKpi/SalesKpiTransaction.php app/Support/SalesKpiRowNormalizer.php app/Exceptions/SalesKpiDataException.php app/Services/SalesKpi/SalesKpiDataService.php tests/Unit/SalesKpiRowNormalizerTest.php tests/Unit/SalesKpiDataServiceTest.php
git commit -m "feat: load and normalize sales KPI data"
```

---

### Task 3: Per-Line KPI Calculation and Normalization

**Files:**
- Create: `app/Data/SalesKpi/SalesLineKpi.php`
- Create: `app/Data/SalesKpi/SalesKpiReport.php`
- Create: `app/Services/SalesKpi/SalesKpiCalculator.php`
- Test: `tests/Unit/SalesKpiCalculatorTest.php`

**Interfaces:**
- Consumes: normalized transaction lists from `SalesKpiDataService`.
- Consumes: target array for the selected database.
- Produces: `SalesKpiCalculator::calculate(SalesKpiFilter $filter, SalesKpiPeriod $period, array $datasets): SalesKpiReport`.
- Produces: `SalesLineKpi` objects with all raw amounts, component scores, available maximum, normalized score, and status.
- Produces: `SalesKpiReport` with rows, weighted cards, unfiltered summary data, and detail rows filtered separately.

- [ ] **Step 1: Write formula tests for all approved component rules**

Create `tests/Unit/SalesKpiCalculatorTest.php`. Add helper methods that build `SalesKpiTransaction` objects and a filter/period. Include these exact assertions:

```php
public function test_target_achievement_35_percent_scores_20_of_40(): void
{
    $line = $this->calculateLine(
        target: 1000,
        current: [$this->tx('JAKARTA', 350)],
    );

    self::assertEqualsWithDelta(20.0, $line->targetScore, 0.0001);
}

public function test_target_achievement_70_percent_or_more_is_capped_at_40(): void
{
    $line = $this->calculateLine(
        target: 1000,
        current: [$this->tx('JAKARTA', 900)],
    );

    self::assertSame(40.0, $line->targetScore);
}

public function test_growth_5_percent_scores_5_and_negative_growth_scores_zero(): void
{
    $positive = $this->calculateLine(
        target: 1000,
        current: [$this->tx('JAKARTA', 105)],
        growthCurrent: [$this->tx('JAKARTA', 105)],
        previous: [$this->tx('JAKARTA', 100)],
    );
    $negative = $this->calculateLine(
        target: 1000,
        current: [$this->tx('JAKARTA', 90)],
        growthCurrent: [$this->tx('JAKARTA', 90)],
        previous: [$this->tx('JAKARTA', 100)],
    );

    self::assertEqualsWithDelta(5.0, $positive->growthScore, 0.0001);
    self::assertSame(0.0, $negative->growthScore);
}

public function test_previous_zero_makes_growth_unavailable_and_normalizes_available_points(): void
{
    $line = $this->calculateLine(
        target: 1000,
        current: [$this->tx('JAKARTA', 700, grade: 'A')],
        growthCurrent: [$this->tx('JAKARTA', 700, grade: 'A')],
        previous: [],
    );

    self::assertNull($line->growthScore);
    self::assertSame(80.0, $line->availableMaximum);
    self::assertEqualsWithDelta(
        $line->earnedPoints / 80.0 * 90.0,
        $line->normalizedScore,
        0.0001,
    );
}

public function test_discontinued_and_clearance_row_is_counted_twice(): void
{
    $line = $this->calculateLine(
        target: 10_000,
        current: [$this->tx('JAKARTA', 500, discontinued: true, clearance: true)],
    );

    self::assertSame(500.0, $line->discontinuedOmzet);
    self::assertSame(500.0, $line->clearanceOmzet);
    self::assertSame(1000.0, $line->riskRealization);
    self::assertSame(900.0, $line->riskTarget);
    self::assertSame(20.0, $line->riskScore);
}

public function test_grade_a_35_percent_scores_12_and_grade_b_20_percent_scores_8(): void
{
    $line = $this->calculateLine(
        target: 1000,
        current: [
            $this->tx('JAKARTA', 350, grade: 'A'),
            $this->tx('JAKARTA', 200, grade: 'B'),
            $this->tx('JAKARTA', 450, grade: 'C'),
        ],
    );

    self::assertSame(12.0, $line->gradeAScore);
    self::assertSame(8.0, $line->gradeBScore);
    self::assertSame(20.0, $line->gradeScore());
}

public function test_zero_current_omzet_makes_both_grade_components_unavailable(): void
{
    $line = $this->calculateLine(target: 1000, current: []);

    self::assertNull($line->gradeAScore);
    self::assertNull($line->gradeBScore);
}
```

Also include tests that negative scores clamp to zero and Growth at or above 10% caps at 10.

- [ ] **Step 2: Run calculator tests and confirm failure**

Run:

```bash
php artisan test tests/Unit/SalesKpiCalculatorTest.php
```

Expected: FAIL because calculator and result objects do not exist.

- [ ] **Step 3: Implement `SalesLineKpi` with explicit fields**

Create `app/Data/SalesKpi/SalesLineKpi.php` with this constructor:

```php
public function __construct(
    public readonly string $line,
    public readonly string $lineKey,
    public readonly ?float $target,
    public readonly float $currentOmzet,
    public readonly float $growthCurrentOmzet,
    public readonly float $previousOmzet,
    public readonly ?float $growthRate,
    public readonly float $discontinuedOmzet,
    public readonly float $clearanceOmzet,
    public readonly float $riskRealization,
    public readonly ?float $riskTarget,
    public readonly float $gradeAOmzet,
    public readonly float $gradeBOmzet,
    public readonly ?float $gradeAContribution,
    public readonly ?float $gradeBContribution,
    public readonly ?float $targetScore,
    public readonly ?float $growthScore,
    public readonly ?float $riskScore,
    public readonly ?float $gradeAScore,
    public readonly ?float $gradeBScore,
    public readonly float $earnedPoints,
    public readonly float $availableMaximum,
    public readonly ?float $normalizedScore,
    public readonly string $status,
) {}

public function gradeScore(): ?float
{
    return $this->gradeAScore === null || $this->gradeBScore === null
        ? null
        : $this->gradeAScore + $this->gradeBScore;
}

public function hasTarget(): bool
{
    return $this->target !== null && $this->target > 0.0;
}
```

- [ ] **Step 4: Implement `SalesKpiReport`**

Create `app/Data/SalesKpi/SalesKpiReport.php` with:

```php
/**
 * @param list<SalesLineKpi> $rows
 * @param list<SalesKpiTransaction> $detailRows
 * @param array{overall:?float,target:?float,growth:?float,risk:?float,grade:?float} $summary
 */
public function __construct(
    public readonly SalesKpiFilter $filter,
    public readonly SalesKpiPeriod $period,
    public readonly array $rows,
    public readonly array $detailRows,
    public readonly array $summary,
) {}

public function isEmpty(): bool
{
    return $this->rows === [];
}

public static function empty(SalesKpiFilter $filter, SalesKpiPeriod $period): self
{
    return new self(
        filter: $filter,
        period: $period,
        rows: [],
        detailRows: [],
        summary: ['overall' => null, 'target' => null, 'growth' => null, 'risk' => null, 'grade' => null],
    );
}
```

Add `chartData(): array` returning numeric arrays only, using these keys:

```php
[
    'labels',
    'overall_scores',
    'target_scores',
    'growth_scores',
    'risk_scores',
    'grade_scores',
    'targets',
    'threshold_70',
    'current_omzet',
    'growth_current',
    'growth_previous',
    'discontinued',
    'clearance',
    'risk_targets',
    'grade_a_contribution',
    'grade_b_contribution',
]
```

Unavailable numeric values must be returned as `null`, never as strings such as `N/A`. Add `gradeDetailRows(): array`, which returns only Grade A/B detail rows and includes a numeric `contribution` computed from the matching `SalesLineKpi::$currentOmzet`; Blade consumes that projection without performing division.

- [ ] **Step 5: Implement the calculator formulas**

Create `app/Services/SalesKpi/SalesKpiCalculator.php` with constructor:

```php
/** @param array<string,array<string,int|float>> $targets */
public function __construct(private readonly array $targets) {}
```

`calculate()` must:

1. return `SalesKpiReport::empty($filter, $period)` immediately when the full current dataset is empty;
2. select targets from `$this->targets[$filter->databaseKey()] ?? []`;
3. build the union of line keys from all three datasets;
4. retain the first non-empty original display label for each key;
5. normalize target config keys with `strtoupper(trim())` and collapsed whitespace;
6. calculate every raw amount by line;
7. use these private helpers:

```php
private function clamp(float $value, float $minimum, float $maximum): float;
private function targetScore(float $omzet, ?float $target): ?float;
private function growthScore(float $current, float $previous): array{rate:?float,score:?float};
private function riskScore(float $realization, ?float $target): array{target:?float,score:?float};
private function gradeScores(float $total, float $gradeA, float $gradeB): array{a_contribution:?float,b_contribution:?float,a_score:?float,b_score:?float};
private function normalizeScore(float $earned, float $availableMaximum): ?float;
private function status(?float $normalizedScore): string;
```

Exact status method:

```php
return match (true) {
    $normalizedScore === null => 'Tidak dapat dihitung',
    $normalizedScore >= 80.0 => 'Baik',
    $normalizedScore >= 60.0 => 'Perlu Perhatian',
    default => 'Kurang',
};
```

Available maximum is the sum of native component maxima only when the component is available:

```php
$availableMaximum =
    ($targetScore !== null ? 40.0 : 0.0) +
    ($growthScore !== null ? 10.0 : 0.0) +
    ($riskScore !== null ? 20.0 : 0.0) +
    ($gradeAScore !== null ? 12.0 : 0.0) +
    ($gradeBScore !== null ? 8.0 : 0.0);
```

- [ ] **Step 6: Run formula tests**

Run:

```bash
php artisan test tests/Unit/SalesKpiCalculatorTest.php
```

Expected: all formula and normalization tests PASS.

- [ ] **Step 7: Format and commit Task 3**

Run:

```bash
vendor/bin/pint app/Data/SalesKpi/SalesLineKpi.php app/Data/SalesKpi/SalesKpiReport.php app/Services/SalesKpi/SalesKpiCalculator.php tests/Unit/SalesKpiCalculatorTest.php
php artisan test tests/Unit/SalesKpiCalculatorTest.php
```

Commit:

```bash
git add app/Data/SalesKpi/SalesLineKpi.php app/Data/SalesKpi/SalesKpiReport.php app/Services/SalesKpi/SalesKpiCalculator.php tests/Unit/SalesKpiCalculatorTest.php
git commit -m "feat: calculate per-line sales KPI scores"
```

---

### Task 4: Weighted Summaries, Dynamic Lines, and Detail-Only Filters

**Files:**
- Modify: `app/Services/SalesKpi/SalesKpiCalculator.php`
- Modify: `app/Data/SalesKpi/SalesKpiReport.php`
- Modify: `tests/Unit/SalesKpiCalculatorTest.php`

**Interfaces:**
- Consumes: line results from Task 3.
- Produces: weighted summary cards in `SalesKpiReport::$summary`.
- Produces: all lines in `SalesKpiReport::$rows`, including unconfigured lines.
- Produces: detail-filtered current rows in `SalesKpiReport::$detailRows` without changing summary rows or scores.

- [ ] **Step 1: Add weighted-total and dynamic-line tests**

Add these tests to `tests/Unit/SalesKpiCalculatorTest.php`:

```php
public function test_overall_score_is_weighted_by_target_not_simple_average(): void
{
    $report = $this->calculateReport(
        targets: ['JAKARTA' => 500, 'GERAI KATAMSO' => 300],
        current: [
            $this->tx('JAKARTA', 350),
            $this->tx('GERAI KATAMSO', 105),
        ],
        previous: [
            $this->tx('JAKARTA', 350),
            $this->tx('GERAI KATAMSO', 105),
        ],
    );

    $jakarta = $this->line($report, 'JAKARTA');
    $katamso = $this->line($report, 'GERAI KATAMSO');
    $expected = (
        $jakarta->normalizedScore * 500 +
        $katamso->normalizedScore * 300
    ) / 800;

    self::assertEqualsWithDelta($expected, $report->summary['overall'], 0.0001);
}

public function test_unconfigured_line_remains_visible_but_is_excluded_from_weighted_total(): void
{
    $report = $this->calculateReport(
        targets: ['JAKARTA' => 500],
        current: [
            $this->tx('JAKARTA', 350),
            $this->tx('NEW LINE', 100, grade: 'A'),
        ],
        previous: [
            $this->tx('JAKARTA', 350),
            $this->tx('NEW LINE', 100, grade: 'A'),
        ],
    );

    self::assertCount(2, $report->rows);
    self::assertNull($this->line($report, 'NEW LINE')->target);
    self::assertEqualsWithDelta(
        $this->line($report, 'JAKARTA')->normalizedScore,
        $report->summary['overall'],
        0.0001,
    );
}
```

- [ ] **Step 2: Add a test proving detail filters do not alter scores**

Create two reports from identical datasets: one with all detail filters, one filtering line, grade, discontinued, clearance, and item search. Assert:

```php
self::assertSame($unfiltered->summary, $filtered->summary);
self::assertEquals($unfiltered->rows, $filtered->rows);
self::assertCount(1, $filtered->detailRows);
self::assertSame('ABC01', $filtered->detailRows[0]->itemCode);
```

The matching detail row must satisfy every selected filter; summary rows remain calculated from the unfiltered current dataset.

- [ ] **Step 3: Run the new tests and verify failure**

Run:

```bash
php artisan test tests/Unit/SalesKpiCalculatorTest.php --filter='weighted|unconfigured|detail'
```

Expected: FAIL until summary and detail filtering are implemented.

- [ ] **Step 4: Implement target-weighted summary helpers**

Add to `SalesKpiCalculator`:

```php
/** @param list<SalesLineKpi> $rows */
private function weightedScore(array $rows, callable $score): ?float
{
    $weighted = 0.0;
    $weight = 0.0;

    foreach ($rows as $row) {
        if (!$row->hasTarget()) {
            continue;
        }

        $value = $score($row);
        if ($value === null) {
            continue;
        }

        $weighted += $value * $row->target;
        $weight += $row->target;
    }

    return $weight > 0.0 ? $weighted / $weight : null;
}
```

Build summary exactly as:

```php
$summary = [
    'overall' => $this->weightedScore($rows, fn (SalesLineKpi $row) => $row->normalizedScore),
    'target' => $this->weightedScore($rows, fn (SalesLineKpi $row) => $row->targetScore),
    'growth' => $this->weightedScore($rows, fn (SalesLineKpi $row) => $row->growthScore),
    'risk' => $this->weightedScore($rows, fn (SalesLineKpi $row) => $row->riskScore),
    'grade' => $this->weightedScore($rows, fn (SalesLineKpi $row) => $row->gradeScore()),
];
```

- [ ] **Step 5: Implement detail-only filtering**

Add:

```php
/**
 * @param list<SalesKpiTransaction> $rows
 * @return list<SalesKpiTransaction>
 */
private function filterDetails(array $rows, SalesKpiFilter $filter): array
```

Match all active filters:

- `line`: normalized equality against `lineKey`;
- `discontinue=yes`: `true`; `no`: `false`;
- `clearance=yes`: `true`; `no`: `false`;
- `grade=BLANK`: transaction grade is empty;
- other grade: exact uppercase equality;
- search: case-insensitive substring of `itemCode` or `itemName`.

Sort detail rows by `InvoiceDate` descending, then `InvoiceNumber`, then `ItemCode`.

- [ ] **Step 6: Run all calculator tests**

Run:

```bash
php artisan test tests/Unit/SalesKpiCalculatorTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit Task 4**

```bash
git add app/Services/SalesKpi/SalesKpiCalculator.php app/Data/SalesKpi/SalesKpiReport.php tests/Unit/SalesKpiCalculatorTest.php
git commit -m "feat: add weighted KPI summaries and detail filters"
```

---

### Task 5: KPI Service Orchestration and Dependency Registration

**Files:**
- Create: `app/Services/SalesKpi/SalesKpiService.php`
- Modify: `app/Providers/AppServiceProvider.php:1-31`
- Test: `tests/Unit/SalesKpiServiceTest.php`

**Interfaces:**
- Consumes: filter, period service, data service, and calculator.
- Produces: `SalesKpiService::generate(SalesKpiFilter $filter): SalesKpiReport`.
- Produces: one stable application service for web, Excel, and PDF.

- [ ] **Step 1: Write an orchestration test with fake data**

Create `tests/Unit/SalesKpiServiceTest.php`. Bind `FakeSalesDataSource` data for `INGCO`, then instantiate:

```php
$service = new SalesKpiService(
    new SalesKpiPeriodService(),
    new SalesKpiDataService($fake, new SalesKpiRowNormalizer()),
    new SalesKpiCalculator([
        'AS_INGCO' => ['JAKARTA' => 500_000_000],
        'AS_SMI' => [],
    ]),
);
```

Call `generate(SalesKpiFilter::from('ingco', '2026-07-20', null, 'all', 'all', 'all', ''))` and assert:

```php
self::assertCount(1, $report->rows);
self::assertSame('JAKARTA', $report->rows[0]->lineKey);
self::assertSame('2026-07-01', $report->period->currentStart->format('Y-m-d'));
```

- [ ] **Step 2: Run the orchestration test and confirm failure**

Run:

```bash
php artisan test tests/Unit/SalesKpiServiceTest.php
```

Expected: FAIL because `SalesKpiService` does not exist.

- [ ] **Step 3: Implement the orchestration service**

Create `app/Services/SalesKpi/SalesKpiService.php`:

```php
final class SalesKpiService
{
    public function __construct(
        private readonly SalesKpiPeriodService $periodService,
        private readonly SalesKpiDataService $dataService,
        private readonly SalesKpiCalculator $calculator,
    ) {
    }

    public function generate(SalesKpiFilter $filter): SalesKpiReport
    {
        $period = $this->periodService->make($filter->selectedDate);
        $datasets = $this->dataService->load($filter, $period);

        return $this->calculator->calculate($filter, $period, $datasets);
    }
}
```

- [ ] **Step 4: Register the KPI calculator with configuration**

Modify `app/Providers/AppServiceProvider.php` without changing the existing `SalesDataSource` and `SalesDashboardAggregator` registrations. Add imports for `SalesKpiCalculator` and register:

```php
$this->app->singleton(
    SalesKpiCalculator::class,
    fn (): SalesKpiCalculator => new SalesKpiCalculator(
        targets: (array) config('sales_kpi_targets', []),
    ),
);
```

- [ ] **Step 5: Run unit tests and verify container resolution**

Run:

```bash
php artisan test tests/Unit/SalesKpiServiceTest.php
php artisan about
```

Expected: test PASS and Laravel boots without container errors.

- [ ] **Step 6: Commit Task 5**

```bash
git add app/Services/SalesKpi/SalesKpiService.php app/Providers/AppServiceProvider.php tests/Unit/SalesKpiServiceTest.php
git commit -m "feat: orchestrate sales KPI report generation"
```

---

### Task 6: Controller, Routes, Safe Errors, and Page Skeleton

**Files:**
- Create: `app/Http/Controllers/SalesKpiController.php`
- Create: `resources/views/sales-kpi/index.blade.php`
- Modify: `routes/web.php:1-15`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `tests/Feature/SalesKpiPageTest.php`

**Interfaces:**
- Consumes: `SalesKpiRequest` and `SalesKpiService`.
- Produces route: `sales-kpi.index`.
- Produces page view variables: `report`, `filter`, `error`.
- Excel and PDF routes are added only when their implementations are complete in Tasks 8 and 9.

- [ ] **Step 1: Add a successful page feature test using the fake data source**

Extend `tests/Feature/SalesKpiPageTest.php`:

```php
public function test_valid_request_renders_all_five_tabs_and_dynamic_lines(): void
{
    $this->app->instance(SalesDataSource::class, new FakeSalesDataSource([
        'INGCO' => [
            (object) [
                'InvoiceDate' => '2026-07-20',
                'InvoiceNumber' => 'INV-001',
                'LiniPenjualan' => 'JAKARTA',
                'ItemCode' => 'ABC01',
                'ItemName' => 'Hammer',
                'Quantity' => 1,
                'LineTotal' => 350_000_000,
                'Discontinue' => 0,
                'StatusItem' => 'ACTIVE',
                'Grade' => 'A',
                'StatusClearanceSale' => 'BUKAN CLEARANCE SALE',
                'ClearanceSaleDiscount' => 0,
            ],
            (object) [
                'InvoiceDate' => '2026-07-20',
                'InvoiceNumber' => 'INV-002',
                'LiniPenjualan' => 'NEW LINE',
                'ItemCode' => 'XYZ01',
                'ItemName' => 'Drill',
                'Quantity' => 1,
                'LineTotal' => 100_000,
                'Discontinue' => 0,
                'StatusItem' => 'ACTIVE',
                'Grade' => 'B',
                'StatusClearanceSale' => 'BUKAN CLEARANCE SALE',
                'ClearanceSaleDiscount' => 0,
            ],
        ],
    ]));

    $response = $this->get('/sales-kpi?database=ingco&end_date=2026-07-20');

    $response->assertOk();
    $response->assertSee('Ringkasan KPI');
    $response->assertSee('Target Omzet');
    $response->assertSee('Growth vs Last Month');
    $response->assertSee('Discontinued & Clearance');
    $response->assertSee('Grade A & Grade B');
    $response->assertSee('JAKARTA');
    $response->assertSee('NEW LINE');
    $response->assertSee('Target belum diatur');
    $response->assertSee('Maksimum skor sistem: 90 poin');
}
```

- [ ] **Step 2: Add safe error and empty-state tests**

Add tests that bind a `SalesDataSource` throwing `SalesDataException` and a fake returning empty arrays. Assert the page shows:

```text
Laporan KPI belum dapat dimuat.
```

and:

```text
Tidak ada data penjualan pada periode yang dipilih.
```

The exception message in the page must not contain SQL credentials or stack traces.

- [ ] **Step 3: Run page tests and confirm route failure**

Run:

```bash
php artisan test tests/Feature/SalesKpiPageTest.php
```

Expected: FAIL because routes/controller/view do not exist.

- [ ] **Step 4: Add the final routes**

Modify `routes/web.php`:

```php
use App\Http\Controllers\SalesKpiController;

Route::get('/sales-kpi', [SalesKpiController::class, 'index'])
    ->name('sales-kpi.index');
```

Do not alter the existing root redirect or sales-dashboard routes.

- [ ] **Step 5: Implement the controller index action and safe error mapping**

Create `app/Http/Controllers/SalesKpiController.php`. The `index()` action must mirror the existing dashboard pattern:

```php
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
        $error = $this->friendlyError($exception);
    }

    return view('sales-kpi.index', compact('report', 'filter', 'error'));
}
```

Use user-safe messages:

- connection/function failure: `Laporan KPI belum dapat dimuat. Koneksi atau function database yang dipilih gagal.`
- missing column: pass through the safe message naming the missing column.

The controller contains only `index()` in this task. Add export methods together with their routes in Tasks 8 and 9 so every registered route is executable.

- [ ] **Step 6: Create a minimal page skeleton with all required stable IDs**

Create `resources/views/sales-kpi/index.blade.php` extending `layouts.app`. Include:

- GET form to `sales-kpi.index`;
- database select with `ingco` and `smi` only;
- end-date input;
- period labels;
- error block;
- empty state;
- five Bootstrap tab buttons with IDs:
  - `summary-tab`
  - `target-tab`
  - `growth-tab`
  - `risk-tab`
  - `grade-tab`
- matching tab panes;
- `window.salesKpiData = {{ Illuminate\Support\Js::from($report->chartData()) }};`;
- local script include `asset('js/sales-kpi-charts.js')`.

Render every final table heading and iterate the report rows with the already-calculated DTO fields. Leave chart canvases present but visually empty until Task 7 adds the local renderer. Formula calculations must not be written in Blade.

- [ ] **Step 7: Add header navigation without changing existing branding**

Modify `resources/views/layouts/app.blade.php` header to add two links:

```blade
<a href="{{ route('sales-dashboard.index') }}" class="btn btn-sm btn-outline-light">Sales Dashboard</a>
<a href="{{ route('sales-kpi.index') }}" class="btn btn-sm btn-warning fw-bold">KPI Dashboard</a>
```

Keep the existing title and subtitle. Add reusable classes for `.kpi-status-good`, `.kpi-status-warning`, `.kpi-status-bad`, and `.kpi-status-na`.

- [ ] **Step 8: Run page tests and the existing sales dashboard test**

Run:

```bash
php artisan test tests/Feature/SalesKpiPageTest.php tests/Feature/SalesDashboardPageTest.php
```

Expected: both feature test files PASS.

- [ ] **Step 9: Commit Task 6**

```bash
git add app/Http/Controllers/SalesKpiController.php routes/web.php resources/views/sales-kpi/index.blade.php resources/views/layouts/app.blade.php tests/Feature/SalesKpiPageTest.php
git commit -m "feat: add sales KPI page and routes"
```

---

### Task 7: Complete Five-Tab UI and Local Charts

**Files:**
- Modify: `resources/views/sales-kpi/index.blade.php`
- Create: `public/js/sales-kpi-charts.js`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `tests/Feature/SalesKpiPageTest.php`

**Interfaces:**
- Consumes: `SalesKpiReport`, `SalesLineKpi`, detail rows, and `chartData()`.
- Produces: five management cards, five complete tab panes, stable chart canvas IDs, and client-side detail table filtering UX.

- [ ] **Step 1: Add feature assertions for cards, tables, filters, and chart payload**

Extend the valid-page test with:

```php
$response->assertSee('Skor KPI Keseluruhan');
$response->assertSee('Target Omzet / 40');
$response->assertSee('Growth / 10');
$response->assertSee('Discontinued/Clearance / 20');
$response->assertSee('Grade A/B / 20');
$response->assertSee('id="overallKpiChart"', false);
$response->assertSee('id="componentKpiChart"', false);
$response->assertSee('id="targetKpiChart"', false);
$response->assertSee('id="growthKpiChart"', false);
$response->assertSee('id="riskKpiChart"', false);
$response->assertSee('id="gradeKpiChart"', false);
$response->assertSee('window.salesKpiData =', false);
$response->assertSee('/js/sales-kpi-charts.js', false);
```

- [ ] **Step 2: Run the page test and confirm missing UI elements**

Run:

```bash
php artisan test tests/Feature/SalesKpiPageTest.php --filter=valid_request
```

Expected: FAIL on missing completed UI elements.

- [ ] **Step 3: Build the five score cards**

In `index.blade.php`, display numeric scores using Indonesian formatting and native maxima:

- overall `summary['overall']` / 90;
- target / 40;
- growth / 10;
- risk / 20;
- grade / 20.

For `null`, display `N/A`. Overall card status uses the line-status thresholds; component cards use:

- full native maximum: `Tercapai`;
- below maximum: `Belum Tercapai`;
- null: `Tidak dapat dihitung`.

Add the note exactly:

```text
Maksimum skor sistem: 90 poin. Monitoring harian 10% berada di luar sistem.
```

- [ ] **Step 4: Build the Ringkasan KPI tab**

Add two chart canvases and the final table columns:

```text
LiniPenjualan | Target | Omzet Aktual | Target / 40 | Growth / 10 | Disc./Clearance / 20 | Grade A/B / 20 | Maks. Tersedia | Skor Akhir / 90 | Status
```

Use a sticky first column, horizontal scroll, and a final target-weighted total row. Missing targets display the badge `Target belum diatur`.

- [ ] **Step 5: Build the Target Omzet and Growth tabs**

Target tab columns:

```text
LiniPenjualan | Target Bulanan | Batas 70% | Omzet Aktual | Achievement | Gap ke 70% | Gap ke 100% | Skor / 40 | Status
```

Growth tab columns:

```text
LiniPenjualan | Periode Sekarang | Periode Sebelumnya | Selisih | Growth % | Target Growth | Skor / 10 | Status
```

Render previous-zero values as `Tidak dapat dihitung` and `N/A`, not infinity.

- [ ] **Step 6: Build the Discontinued/Clearance and Grade tabs**

Risk summary columns:

```text
LiniPenjualan | Target Omzet | Target 9% | Omzet Discontinued | Omzet Clearance | Total Realisasi KPI | Achievement | Skor / 20
```

Risk detail columns:

```text
Invoice Date | Invoice Number | LiniPenjualan | ItemCode | ItemName | Discontinue | StatusItem | Status Clearance | Clearance Discount | Quantity | LineTotal
```

Grade summary columns:

```text
LiniPenjualan | Total Omzet | Omzet Grade A | Kontribusi A | Skor A / 12 | Omzet Grade B | Kontribusi B | Skor B / 8 | Total Skor / 20
```

Grade detail columns:

```text
LiniPenjualan | Grade | ItemCode | ItemName | Quantity | LineTotal | Kontribusi dalam Lini
```

The Grade detail table must iterate `SalesKpiReport::gradeDetailRows()`, which returns only Grade A/B rows and the precomputed `contribution`; Blade must not divide `LineTotal` by line omzet.

- [ ] **Step 7: Add detail-only filter controls**

Add GET fields preserving database and end date:

- `line`
- `discontinue`
- `clearance`
- `grade`
- `search`

Label the section `Filter Detail — tidak mengubah skor KPI`. Populate the line dropdown dynamically from report rows.

- [ ] **Step 8: Implement local responsive canvas charts**

Create `public/js/sales-kpi-charts.js`. Follow the existing dashboard's dependency-free canvas pattern:

- read `window.salesKpiData`;
- use `Intl.NumberFormat('id-ID', { notation: 'compact' })`;
- resize canvases for device pixel ratio;
- render horizontal grouped bars;
- never coerce `null` to zero for unavailable scores; draw an `N/A` marker instead;
- use these canvas IDs:
  - `overallKpiChart`
  - `componentKpiChart`
  - `targetKpiChart`
  - `growthKpiChart`
  - `riskKpiChart`
  - `gradeKpiChart`;
- redraw on window resize with a debounced handler;
- do not add remote scripts.

Use a fixed scale of 0–90 for overall scores and native maxima for component charts.

- [ ] **Step 9: Run feature tests and manually inspect mobile behavior**

Run:

```bash
php artisan test tests/Feature/SalesKpiPageTest.php
php artisan serve --host=127.0.0.1 --port=8000
```

Open:

```text
http://127.0.0.1:8000/sales-kpi?database=ingco&end_date=2026-07-20
```

Verify:

- every tab opens;
- tables scroll horizontally on a narrow viewport;
- first line column remains readable;
- charts resize after switching tabs and resizing the browser;
- detail filters preserve score cards;
- `Target belum diatur` is visible for unconfigured lines.

- [ ] **Step 10: Commit Task 7**

```bash
git add resources/views/sales-kpi/index.blade.php public/js/sales-kpi-charts.js resources/views/layouts/app.blade.php tests/Feature/SalesKpiPageTest.php
git commit -m "feat: complete sales KPI dashboard UI"
```

---

### Task 8: Six-Sheet Excel Export

**Files:**
- Create: `app/Exports/SalesKpi/SalesKpiExport.php`
- Create: `app/Exports/SalesKpi/SalesKpiSheet.php`
- Modify: `app/Http/Controllers/SalesKpiController.php`
- Test: `tests/Feature/SalesKpiExportTest.php`

**Interfaces:**
- Consumes: one `SalesKpiReport` generated by `SalesKpiService`.
- Produces: `SalesKpiExport implements WithMultipleSheets`.
- Produces: six worksheets with numeric monetary and percentage cells.

- [ ] **Step 1: Write an Excel export feature test**

Create `tests/Feature/SalesKpiExportTest.php`. Bind fake data and call:

```php
$response = $this->get('/sales-kpi/export/excel?database=ingco&end_date=2026-07-20');
$response->assertOk();
$response->assertHeader('content-disposition');
```

Also instantiate the export directly and assert sheet titles:

```php
$export = new SalesKpiExport($report);
$titles = array_map(
    static fn (SalesKpiSheet $sheet): string => $sheet->title(),
    $export->sheets(),
);

self::assertSame([
    'Ringkasan KPI',
    'Target Omzet',
    'Growth',
    'Discontinued Clearance',
    'Grade A B',
    'Detail Item',
], $titles);
```

Save the downloaded workbook to `$path = sys_get_temp_dir().'/sales-kpi-test.xlsx'`, load it with `IOFactory::load($path)`, and assert:

```php
self::assertCount(6, $spreadsheet->getSheetNames());
self::assertSame('Ringkasan KPI', $spreadsheet->getSheet(0)->getTitle());
self::assertIsNumeric($spreadsheet->getSheet(0)->getCell('B8')->getValue());
```

- [ ] **Step 2: Run the export test and confirm failure**

Run:

```bash
php artisan test tests/Feature/SalesKpiExportTest.php --filter=excel
```

Expected: FAIL because export classes and controller body are not implemented.

- [ ] **Step 3: Implement reusable `SalesKpiSheet`**

`SalesKpiSheet` must implement:

```php
FromArray,
ShouldAutoSize,
WithColumnFormatting,
WithStyles,
WithTitle
```

Constructor:

```php
/**
 * @param list<list<mixed>> $rows
 * @param array<string,string> $formats
 */
public function __construct(
    private readonly string $sheetTitle,
    private readonly array $rows,
    private readonly int $headerRow,
    private readonly array $formats,
) {}
```

Styles must:

- merge title cells across the width of the header;
- use bold yellow title and header rows;
- freeze below the header row and after column A;
- apply thin borders from the header through the last data row;
- enable autofilter on the table header;
- set landscape orientation and fit to one page wide.

- [ ] **Step 4: Implement `SalesKpiExport` with six exact sheets**

`SalesKpiExport implements WithMultipleSheets`. Every sheet starts with:

```text
Sales KPI Dashboard
Database: AS_INGCO or AS_SMI
Periode KPI: dd-mm-yyyy s.d. dd-mm-yyyy
Periode Growth: dd-mm-yyyy s.d. dd-mm-yyyy dibanding dd-mm-yyyy s.d. dd-mm-yyyy
Maksimum skor sistem: 90 poin; monitoring harian 10% berada di luar sistem
Tanggal ekspor: dd-mm-yyyy HH:ii
```

Then a blank row, headings, and numeric rows.

Use exact sheet names from the test. Keep amounts as floats and percentages as decimal fractions, with column formats such as `#,##0`, `0.00%`, and `0.00`.

The `Detail Item` sheet uses `report->detailRows`, so it honors detail-only filters. All other sheets use full `report->rows` and never honor detail filters in their calculations.

- [ ] **Step 5: Add the Excel route, button, and controller response**

Add to `routes/web.php`:

```php
Route::get('/sales-kpi/export/excel', [SalesKpiController::class, 'exportExcel'])
    ->name('sales-kpi.export.excel');
```

Add an `Export Excel` link in `resources/views/sales-kpi/index.blade.php` that preserves every current query filter. In `SalesKpiController::exportExcel()`:

```php
return Excel::download(
    new SalesKpiExport($report),
    sprintf(
        'Sales_KPI_%s_%s.xlsx',
        $filter->databaseKey(),
        $filter->selectedDate->format('Y-m-d'),
    ),
);
```

On `SalesKpiDataException`, redirect to `sales-kpi.index` with all current filter query values and `report_error`.

- [ ] **Step 6: Run Excel tests and manually open the workbook**

Run:

```bash
php artisan test tests/Feature/SalesKpiExportTest.php --filter=excel
```

Then export from the browser and verify:

- six sheet names are exact;
- amount cells are numeric;
- percentage cells are numeric decimals with percentage formatting;
- filters and frozen panes work;
- missing targets and unavailable values are human-readable.

- [ ] **Step 7: Commit Task 8**

```bash
git add app/Exports/SalesKpi app/Http/Controllers/SalesKpiController.php routes/web.php resources/views/sales-kpi/index.blade.php tests/Feature/SalesKpiExportTest.php
git commit -m "feat: export sales KPI workbook"
```

---

### Task 9: Management PDF Export

**Files:**
- Create: `resources/views/sales-kpi/pdf.blade.php`
- Modify: `app/Http/Controllers/SalesKpiController.php`
- Modify: `tests/Feature/SalesKpiExportTest.php`

**Interfaces:**
- Consumes: full `SalesKpiReport`.
- Produces: landscape PDF without transaction-level detail.

- [ ] **Step 1: Add PDF response tests**

Extend `tests/Feature/SalesKpiExportTest.php`:

```php
public function test_pdf_export_returns_a_pdf_download(): void
{
    $this->bindKpiFake();

    $response = $this->get('/sales-kpi/export/pdf?database=ingco&end_date=2026-07-20');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    self::assertStringStartsWith('%PDF', (string) $response->getContent());
}
```

- [ ] **Step 2: Run the PDF test and confirm failure**

Run:

```bash
php artisan test tests/Feature/SalesKpiExportTest.php --filter=pdf
```

Expected: FAIL until the view and controller response exist.

- [ ] **Step 3: Build the PDF view**

Create `resources/views/sales-kpi/pdf.blade.php` with inline print-safe CSS. Include:

1. title, database, current period, and Growth periods;
2. note that the system maximum is 90;
3. five summary cards as compact boxes;
4. per-line summary table;
5. Target Omzet table;
6. Growth table;
7. Discontinued/Clearance summary table;
8. Grade A/B summary table;
9. notes for missing targets and unavailable calculations.

Do not include the transaction detail tables. Use page breaks before large component sections and avoid JavaScript or external CSS.

- [ ] **Step 4: Add the PDF route, button, and controller response**

Add to `routes/web.php`:

```php
Route::get('/sales-kpi/export/pdf', [SalesKpiController::class, 'exportPdf'])
    ->name('sales-kpi.export.pdf');
```

Add an `Export PDF` link in `resources/views/sales-kpi/index.blade.php` that preserves every current query filter. Then implement:

```php
$pdf = Pdf::loadView('sales-kpi.pdf', compact('report'))
    ->setPaper('a3', 'landscape');

return $pdf->download(sprintf(
    'Sales_KPI_%s_%s.pdf',
    $filter->databaseKey(),
    $filter->selectedDate->format('Y-m-d'),
));
```

Use the same safe redirect behavior as Excel.

- [ ] **Step 5: Run PDF tests and inspect the output**

Run:

```bash
php artisan test tests/Feature/SalesKpiExportTest.php --filter=pdf
```

Open the generated PDF and verify no table is cut off horizontally and the status colors remain legible when printed.

- [ ] **Step 6: Commit Task 9**

```bash
git add resources/views/sales-kpi/pdf.blade.php resources/views/sales-kpi/index.blade.php app/Http/Controllers/SalesKpiController.php routes/web.php tests/Feature/SalesKpiExportTest.php
git commit -m "feat: export sales KPI management PDF"
```

---

### Task 10: Regression Tests, Sample Verification, Documentation, and Delivery Package

**Files:**
- Modify: `README.md`
- Modify: `docs/COLUMN-MAPPING.md`
- Create: `docs/SALES-KPI-CALCULATION.md`
- Modify: `tests/Feature/SalesKpiPageTest.php`
- Modify: `tests/Feature/SalesKpiExportTest.php`
- Preserve: all current dashboard files and tests.

**Interfaces:**
- Produces: verified project that can replace the user's current project folder.
- Produces: documented target-editing instructions and calculation references.

- [ ] **Step 1: Add a regression test for the old dashboard route**

Add to `SalesKpiPageTest` or retain the existing `SalesDashboardPageTest` and run it in the final gate. Assert both routes return 200 with fakes:

```php
$this->get('/sales-dashboard?database=all&end_date=2026-07-20&item_status=all')->assertOk();
$this->get('/sales-kpi?database=ingco&end_date=2026-07-20')->assertOk();
```

- [ ] **Step 2: Add an explicit missing-column safe-error test**

Bind a fake row missing `StatusClearanceSale`. Assert the page contains:

```text
Kolom StatusClearanceSale tidak ditemukan pada hasil fn_PenjualanLini.
```

and does not expose a stack trace.

- [ ] **Step 3: Document exact configuration and formulas**

Update `README.md` with:

- URL `/sales-kpi`;
- target file path `config/sales_kpi_targets.php`;
- example `AS_INGCO` and `AS_SMI` target blocks;
- command `php artisan optimize:clear` after target edits;
- Excel and PDF URLs;
- note that score maximum is 90.

Update `docs/COLUMN-MAPPING.md` with exact verified fields:

```text
LiniPenjualan
LineTotal
Discontinue
StatusItem
Grade
Quantity
StatusClearanceSale
ClearanceSaleDiscount
InvoiceDate
InvoiceNumber
ItemCode
ItemName
```

Create `docs/SALES-KPI-CALCULATION.md` containing the four approved formulas, normalization formula, status thresholds, double-count rule, equal-day Growth rule, and weighted total formula.

- [ ] **Step 4: Run formatting and the complete automated test suite**

Run:

```bash
vendor/bin/pint
php artisan optimize:clear
php artisan test
```

Expected:

- all KPI unit and feature tests PASS;
- all existing sales-dashboard tests PASS;
- no parse errors;
- no container binding errors.

- [ ] **Step 5: Run direct application checks**

Run:

```bash
php artisan route:list --name=sales-kpi
php artisan about
php artisan serve --host=127.0.0.1 --port=8000
```

Verify the route list contains exactly index, Excel, and PDF KPI routes.

- [ ] **Step 6: Compare several lines against `Book1(3).xlsx`**

For at least `JAKARTA`, `GERAI KATAMSO`, and one unconfigured line:

1. sum `LineTotal` by `LiniPenjualan`;
2. sum discontinued rows where `Discontinue = 1`;
3. sum clearance rows where normalized `StatusClearanceSale = CLEARANCE SALE`;
4. confirm a row with both statuses contributes to both subtotals;
5. calculate Grade A and B shares against total line omzet;
6. compare the manual results to web, Excel, and PDF.

Record the checked values and date in `docs/SALES-KPI-CALCULATION.md` under `Sample verification`.

- [ ] **Step 7: Verify Windows/XAMPP deployment behavior**

In the user's environment or an equivalent Windows test:

```bat
cd C:\xampp82\htdocs\sales-report-web
C:\xampp82\php\php.exe artisan optimize:clear
C:\xampp82\php\php.exe artisan test
```

Then verify through both:

```text
http://127.0.0.1:8000/sales-kpi
http://localhost/sales-report-web/public/sales-kpi
```

Confirm SQL Server Driver 17/18 connections still use existing `.env` settings and no credentials were added to source files.

- [ ] **Step 8: Build the delivery ZIP**

From the parent directory, exclude runtime and secret files:

```bash
zip -r Sales_Report_Dashboard_Laravel12_KPI.zip Sales_Report_Dashboard_Laravel12 \
  -x 'Sales_Report_Dashboard_Laravel12/.env' \
     'Sales_Report_Dashboard_Laravel12/vendor/*' \
     'Sales_Report_Dashboard_Laravel12/storage/logs/*' \
     'Sales_Report_Dashboard_Laravel12/storage/framework/sessions/*' \
     'Sales_Report_Dashboard_Laravel12/storage/framework/views/*'
```

Include `.env.example`, configuration, tests, documentation, and batch helper files.

- [ ] **Step 9: Final commit**

```bash
git add README.md docs tests
git commit -m "docs: finalize sales KPI dashboard delivery"
```

- [ ] **Step 10: Final evidence checklist**

Before claiming completion, capture and report:

```text
php artisan test               -> PASS with test count
php artisan route:list         -> three sales-kpi routes present
Web index                      -> HTTP 200
Excel export                   -> six sheets
PDF export                     -> valid %PDF response
Existing sales dashboard test  -> PASS
Delivery ZIP                   -> created and non-empty
```

Do not claim the feature is complete unless each item has direct command or file evidence.
