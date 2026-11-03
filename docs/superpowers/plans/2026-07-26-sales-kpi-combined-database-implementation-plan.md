# Sales KPI Combined Database Mode Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend `/sales-kpi` so users can select `GABUNGAN`, `AS_INGCO`, or `AS_SMI`, merge equal `LiniPenjualan` values across both databases, inspect source contributions through hybrid DataTables, and export complete filtered results.

**Architecture:** Preserve the existing KPI formulas and 90-point normalization. Add source metadata to normalized transactions, coordinate one or both databases through an all-or-nothing loader, aggregate each source independently before producing combined line totals, and keep scoring in `SalesKpiCalculator`. Recap tables are rendered with the initial Blade response and enhanced by a local client-side table controller; transaction/item details are returned by a Laravel JSON endpoint that applies filtering, sorting, and pagination on the server.

**Tech Stack:** PHP 8.2+, Laravel 12, Blade, Bootstrap 5, SQL Server through `pdo_sqlsrv`, PHPUnit 11, vanilla JavaScript, Maatwebsite Excel 3.1, PhpSpreadsheet 1.30, barryvdh/laravel-dompdf 3.1.

## Global Constraints

- Database mode accepts exactly `combined`, `ingco`, or `smi`; labels shown to users are `GABUNGAN`, `AS_INGCO`, and `AS_SMI`.
- `LiniPenjualan` remains the only KPI grouping key and is matched with trimmed uppercase whitespace-normalized text.
- In combined mode, source values are aggregated independently and then merged by normalized `LiniPenjualan`.
- Combined sales and targets are sums of `AS_INGCO` and `AS_SMI`; a missing line in one source contributes zero.
- A target missing in one source contributes zero and produces a visible warning; target-dependent components remain calculable from the available source target. If both targets are missing, target-dependent components are unavailable.
- Combined Growth is calculated after current and previous source sales are summed; the approved equal-day period rule remains unchanged.
- Target Omzet, Growth, Discontinued/Clearance, Grade A, Grade B, normalization, status thresholds, and the 90-point ceiling remain exactly as approved.
- A transaction carrying both discontinued and clearance statuses contributes its `LineTotal` once to each component.
- Overall KPI weighting uses the combined positive target in combined mode.
- Combined mode is all-or-nothing: if either required database query fails, no partial combined report is shown.
- User-facing errors name each failed database but never include server names, credentials, SQL text, stack traces, or driver details.
- Summary and recap tables load with the initial response and support search, sorting, pagination, and backend export of all filtered rows.
- Detail transaction/item tables use a Laravel JSON endpoint for filtering, sorting, and pagination; the browser must not receive every detail row in the initial page HTML.
- The detail `Database` column is visible in combined mode, hidden in single-source mode, and always present in Excel detail exports.
- Summary tab has no large chart. Small supporting charts remain on detail tabs.
- Exports include all rows matching active filters, not only the current pagination page.
- Existing `/sales-dashboard` behavior and tests remain unchanged.
- Do not add React, Vue, a target-maintenance database table, or changes to `dbo.fn_PenjualanLini`.

---

## File Structure Map

### Files to create

- `app/Data/SalesKpi/SalesKpiDataset.php` — normalized current and comparison rows with source metadata.
- `app/Data/SalesKpi/SalesKpiLineAggregate.php` — one normalized line with per-source raw aggregate metrics.
- `app/Data/SalesKpi/SalesKpiSourceContribution.php` — per-source target and sales contribution used by web and exports.
- `app/Data/SalesKpi/SalesKpiDetailPage.php` — immutable server-side detail page response.
- `app/Http/Requests/SalesKpiDetailRequest.php` — validates DataTable search, sorting, paging, and detail type.
- `app/Services/SalesKpi/SalesKpiCombinedDataService.php` — coordinates selected sources and enforces all-or-nothing combined loading.
- `app/Services/SalesKpi/SalesKpiAggregator.php` — aggregates normalized transactions by source and `LiniPenjualan`.
- `app/Services/SalesKpi/SalesKpiTargetService.php` — source and combined target lookup with missing-target flags.
- `app/Services/SalesKpi/SalesKpiDetailQueryService.php` — server-side detail filtering, sorting, and pagination.
- `public/js/sales-kpi-datatable.js` — local recap table search/sort/page controls and detail AJAX rendering.
- `tests/Unit/SalesKpiFilterCombinedTest.php`
- `tests/Unit/SalesKpiTargetServiceTest.php`
- `tests/Unit/SalesKpiAggregatorTest.php`
- `tests/Unit/SalesKpiCombinedDataServiceTest.php`
- `tests/Unit/SalesKpiDetailQueryServiceTest.php`
- `tests/Feature/SalesKpiDetailEndpointTest.php`

### Files to modify

- `app/Data/SalesKpi/SalesKpiFilter.php`
- `app/Data/SalesKpi/SalesKpiTransaction.php`
- `app/Data/SalesKpi/SalesLineKpi.php`
- `app/Data/SalesKpi/SalesKpiReport.php`
- `app/Exceptions/SalesKpiDataException.php`
- `app/Support/SalesKpiRowNormalizer.php`
- `app/Services/SalesKpi/SalesKpiDataService.php`
- `app/Services/SalesKpi/SalesKpiCalculator.php`
- `app/Services/SalesKpi/SalesKpiService.php`
- `app/Http/Requests/SalesKpiRequest.php`
- `app/Http/Controllers/SalesKpiController.php`
- `app/Exports/SalesKpi/SalesKpiExport.php`
- `app/Providers/AppServiceProvider.php`
- `resources/views/sales-kpi/index.blade.php`
- `resources/views/sales-kpi/pdf.blade.php`
- `public/js/sales-kpi-charts.js`
- `routes/web.php`
- `tests/Fakes/FakeSalesDataSource.php`
- `tests/Unit/SalesKpiRowNormalizerTest.php`
- `tests/Unit/SalesKpiCalculatorTest.php`
- `tests/Unit/SalesKpiDataServiceTest.php`
- `tests/Unit/SalesKpiServiceTest.php`
- `tests/Feature/SalesKpiPageTest.php`
- `tests/Feature/SalesKpiExportTest.php`
- `docs/SALES-KPI-CALCULATION.md`
- `README.md`

---

### Task 1: Add Combined Mode to the Validated Filter

**Files:**
- Modify: `app/Data/SalesKpi/SalesKpiFilter.php`
- Modify: `app/Http/Requests/SalesKpiRequest.php`
- Create: `tests/Unit/SalesKpiFilterCombinedTest.php`
- Modify: `tests/Feature/SalesKpiPageTest.php`

**Interfaces:**
- Produces: `SalesKpiFilter::isCombined(): bool`.
- Produces: `SalesKpiFilter::sources(): array` returning `['INGCO', 'SMI']` for combined mode or one source for single mode.
- Produces: `SalesKpiFilter::databaseKeys(): array` returning `['AS_INGCO', 'AS_SMI']` or one config key.
- Produces: `SalesKpiFilter::databaseLabel(): string` returning `GABUNGAN`, `AS_INGCO`, or `AS_SMI`.
- Preserves: `SalesKpiFilter::query(): array` and detail filter behavior.

- [ ] **Step 1: Write failing unit tests for the three database modes**

Create `tests/Unit/SalesKpiFilterCombinedTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\SalesKpi\SalesKpiFilter;
use PHPUnit\Framework\TestCase;

final class SalesKpiFilterCombinedTest extends TestCase
{
    public function test_combined_mode_exposes_both_sources_and_config_keys(): void
    {
        $filter = SalesKpiFilter::from('combined', '2026-07-20');

        self::assertTrue($filter->isCombined());
        self::assertSame(['INGCO', 'SMI'], $filter->sources());
        self::assertSame(['AS_INGCO', 'AS_SMI'], $filter->databaseKeys());
        self::assertSame('GABUNGAN', $filter->databaseLabel());
        self::assertSame('combined', $filter->query()['database']);
    }

    public function test_single_mode_exposes_only_the_selected_source(): void
    {
        $filter = SalesKpiFilter::from('ingco', '2026-07-20');

        self::assertFalse($filter->isCombined());
        self::assertSame(['INGCO'], $filter->sources());
        self::assertSame(['AS_INGCO'], $filter->databaseKeys());
        self::assertSame('AS_INGCO', $filter->databaseLabel());
    }
}
```

- [ ] **Step 2: Run the unit test and verify the expected failure**

Run:

```bash
php artisan test tests/Unit/SalesKpiFilterCombinedTest.php
```

Expected: FAIL because `combined`, `isCombined()`, `sources()`, and `databaseKeys()` are not implemented.

- [ ] **Step 3: Implement the database-mode API**

Update the relevant parts of `app/Data/SalesKpi/SalesKpiFilter.php`:

```php
private const DATABASES = ['combined', 'ingco', 'smi'];

public function isCombined(): bool
{
    return $this->database === 'combined';
}

/** @return list<string> */
public function sources(): array
{
    return match ($this->database) {
        'combined' => ['INGCO', 'SMI'],
        'ingco' => ['INGCO'],
        'smi' => ['SMI'],
    };
}

/** @return list<string> */
public function databaseKeys(): array
{
    return array_map(
        static fn (string $source): string => 'AS_'.$source,
        $this->sources(),
    );
}

public function databaseKey(): string
{
    return $this->isCombined() ? 'GABUNGAN' : $this->databaseKeys()[0];
}

public function databaseLabel(): string
{
    return $this->databaseKey();
}
```

Change the invalid database exception text to:

```php
throw new InvalidArgumentException('Mode database KPI harus combined, ingco, atau smi.');
```

Update `SalesKpiRequest::rules()`:

```php
'database' => ['required', Rule::in(['combined', 'ingco', 'smi'])],
```

- [ ] **Step 4: Add a feature validation assertion for combined mode**

Append to `tests/Feature/SalesKpiPageTest.php`:

```php
public function test_combined_database_value_passes_request_validation(): void
{
    $this->app->bind(\App\Contracts\SalesDataSource::class, fn () => new \Tests\Fakes\FakeSalesDataSource([
        'INGCO' => [],
        'SMI' => [],
    ]));

    $response = $this->get('/sales-kpi?database=combined&end_date=2026-07-20&discontinue=all&clearance=all&grade=ALL');

    $response->assertOk();
    $response->assertSessionDoesntHaveErrors('database');
}
```

- [ ] **Step 5: Run focused tests**

Run:

```bash
php artisan test tests/Unit/SalesKpiFilterCombinedTest.php tests/Feature/SalesKpiPageTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Data/SalesKpi/SalesKpiFilter.php app/Http/Requests/SalesKpiRequest.php tests/Unit/SalesKpiFilterCombinedTest.php tests/Feature/SalesKpiPageTest.php
git commit -m "feat: add combined KPI database mode"
```

---

### Task 2: Attach Database Source and Centralize Target Lookup

**Files:**
- Modify: `app/Data/SalesKpi/SalesKpiTransaction.php`
- Create: `app/Data/SalesKpi/SalesKpiSourceContribution.php`
- Modify: `app/Support/SalesKpiRowNormalizer.php`
- Create: `app/Services/SalesKpi/SalesKpiTargetService.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `tests/Unit/SalesKpiRowNormalizerTest.php`
- Create: `tests/Unit/SalesKpiTargetServiceTest.php`

**Interfaces:**
- Produces: `SalesKpiRowNormalizer::normalize(object|array $row, string $databaseKey): SalesKpiTransaction`.
- Produces: `SalesKpiTransaction::$database` containing `AS_INGCO` or `AS_SMI`.
- Produces: `SalesKpiTargetService::sourceTarget(string $databaseKey, string $lineKey): ?float`.
- Produces: `SalesKpiTargetService::forLine(SalesKpiFilter $filter, string $lineKey): array` keyed by `AS_INGCO` and `AS_SMI` where applicable.
- Produces: `SalesKpiSourceContribution` for view/export source columns.

- [ ] **Step 1: Write failing source-normalization and target tests**

Add to `tests/Unit/SalesKpiRowNormalizerTest.php`:

```php
public function test_it_attaches_the_normalized_database_source(): void
{
    $row = $this->validRow();

    $transaction = (new SalesKpiRowNormalizer())->normalize($row, 'AS_INGCO');

    self::assertSame('AS_INGCO', $transaction->database);
}
```

Create `tests/Unit/SalesKpiTargetServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\SalesKpi\SalesKpiFilter;
use App\Services\SalesKpi\SalesKpiTargetService;
use PHPUnit\Framework\TestCase;

final class SalesKpiTargetServiceTest extends TestCase
{
    public function test_combined_target_uses_available_targets_and_marks_missing_source(): void
    {
        $service = new SalesKpiTargetService([
            'AS_INGCO' => ['JAKARTA' => 500_000_000],
            'AS_SMI' => [],
        ]);

        $targets = $service->forLine(
            SalesKpiFilter::from('combined', '2026-07-20'),
            ' jakarta ',
        );

        self::assertSame(500_000_000.0, $targets['AS_INGCO']);
        self::assertNull($targets['AS_SMI']);
        self::assertSame(500_000_000.0, $service->combined($targets));
        self::assertSame(['AS_SMI'], $service->missingSources($targets));
    }

    public function test_both_missing_targets_produce_a_null_combined_target(): void
    {
        $service = new SalesKpiTargetService(['AS_INGCO' => [], 'AS_SMI' => []]);
        $targets = $service->forLine(SalesKpiFilter::from('combined', '2026-07-20'), 'JAKARTA');

        self::assertNull($service->combined($targets));
        self::assertSame(['AS_INGCO', 'AS_SMI'], $service->missingSources($targets));
    }
}
```

- [ ] **Step 2: Run the tests and verify RED**

```bash
php artisan test tests/Unit/SalesKpiRowNormalizerTest.php tests/Unit/SalesKpiTargetServiceTest.php
```

Expected: FAIL because source-aware normalization and target service do not exist.

- [ ] **Step 3: Add source metadata to normalized transactions**

Add the first constructor property to `SalesKpiTransaction`:

```php
public readonly string $database,
```

Change the normalizer signature and constructor call:

```php
public function normalize(object|array $row, string $databaseKey): SalesKpiTransaction
{
    $databaseKey = strtoupper(trim($databaseKey));
    if (!in_array($databaseKey, ['AS_INGCO', 'AS_SMI'], true)) {
        throw new InvalidArgumentException('Sumber database transaksi KPI tidak valid.');
    }

    // existing column validation and conversion remain unchanged

    return new SalesKpiTransaction(
        database: $databaseKey,
        line: $lineDisplay,
        lineKey: $lineKey,
        // retain all existing named arguments
    );
}
```

- [ ] **Step 4: Create the immutable source contribution DTO**

Create `app/Data/SalesKpi/SalesKpiSourceContribution.php`:

```php
<?php

declare(strict_types=1);

namespace App\Data\SalesKpi;

final class SalesKpiSourceContribution
{
    public function __construct(
        public readonly string $database,
        public readonly ?float $target,
        public readonly bool $targetConfigured,
        public readonly float $currentOmzet,
        public readonly float $growthCurrentOmzet,
        public readonly float $previousOmzet,
        public readonly float $discontinuedOmzet,
        public readonly float $clearanceOmzet,
        public readonly float $gradeAOmzet,
        public readonly float $gradeBOmzet,
    ) {
    }
}
```

- [ ] **Step 5: Implement target normalization and lookup**

Create `app/Services/SalesKpi/SalesKpiTargetService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\SalesKpi;

use App\Data\SalesKpi\SalesKpiFilter;

final class SalesKpiTargetService
{
    /** @var array<string,array<string,float>> */
    private array $targets;

    /** @param array<string,array<string,int|float>> $targets */
    public function __construct(array $targets)
    {
        $this->targets = [];
        foreach ($targets as $database => $lines) {
            foreach ($lines as $line => $target) {
                $this->targets[strtoupper(trim((string) $database))][$this->normalizeLine((string) $line)] = (float) $target;
            }
        }
    }

    public function sourceTarget(string $databaseKey, string $lineKey): ?float
    {
        $value = $this->targets[strtoupper(trim($databaseKey))][$this->normalizeLine($lineKey)] ?? null;

        return $value !== null && $value > 0.0 ? $value : null;
    }

    /** @return array<string,?float> */
    public function forLine(SalesKpiFilter $filter, string $lineKey): array
    {
        $result = [];
        foreach ($filter->databaseKeys() as $databaseKey) {
            $result[$databaseKey] = $this->sourceTarget($databaseKey, $lineKey);
        }

        return $result;
    }

    /** @param array<string,?float> $targets */
    public function combined(array $targets): ?float
    {
        $configured = array_filter($targets, static fn (?float $value): bool => $value !== null);

        return $configured === [] ? null : array_sum($configured);
    }

    /** @param array<string,?float> $targets @return list<string> */
    public function missingSources(array $targets): array
    {
        return array_keys(array_filter($targets, static fn (?float $value): bool => $value === null));
    }

    private function normalizeLine(string $line): string
    {
        $line = trim($line);

        return strtoupper(preg_replace('/\s+/', ' ', $line) ?? $line);
    }
}
```

- [ ] **Step 6: Update service-provider bindings**

Replace the direct config injection into `SalesKpiCalculator` with:

```php
$this->app->singleton(
    \App\Services\SalesKpi\SalesKpiTargetService::class,
    fn (): \App\Services\SalesKpi\SalesKpiTargetService => new \App\Services\SalesKpi\SalesKpiTargetService(
        (array) config('sales_kpi_targets', []),
    ),
);
```

Do not remove the existing `SalesDataSource` and sales-dashboard bindings.

- [ ] **Step 7: Run focused tests**

```bash
php artisan test tests/Unit/SalesKpiRowNormalizerTest.php tests/Unit/SalesKpiTargetServiceTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Data/SalesKpi/SalesKpiTransaction.php app/Data/SalesKpi/SalesKpiSourceContribution.php app/Support/SalesKpiRowNormalizer.php app/Services/SalesKpi/SalesKpiTargetService.php app/Providers/AppServiceProvider.php tests/Unit/SalesKpiRowNormalizerTest.php tests/Unit/SalesKpiTargetServiceTest.php
git commit -m "feat: add source-aware KPI targets and rows"
```

---

### Task 3: Load One or Both Databases with All-or-Nothing Combined Failure

**Files:**
- Create: `app/Data/SalesKpi/SalesKpiDataset.php`
- Modify: `app/Exceptions/SalesKpiDataException.php`
- Modify: `app/Services/SalesKpi/SalesKpiDataService.php`
- Create: `app/Services/SalesKpi/SalesKpiCombinedDataService.php`
- Modify: `tests/Fakes/FakeSalesDataSource.php`
- Modify: `tests/Unit/SalesKpiDataServiceTest.php`
- Create: `tests/Unit/SalesKpiCombinedDataServiceTest.php`

**Interfaces:**
- Produces: `SalesKpiDataService::loadSource(string $source, SalesKpiPeriod $period): SalesKpiDataset`.
- Produces: `SalesKpiCombinedDataService::load(SalesKpiFilter $filter, SalesKpiPeriod $period): SalesKpiDataset`.
- Produces: `SalesKpiDataException::failedSources(): array` with source names `INGCO` and/or `SMI`.
- Guarantees: combined loading attempts both sources, collects failures, and returns no partial dataset when any source fails.

- [ ] **Step 1: Write failing combined-loading tests**

Create `tests/Unit/SalesKpiCombinedDataServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SalesDataSource;
use App\Data\SalesKpi\SalesKpiFilter;
use App\Exceptions\SalesDataException;
use App\Exceptions\SalesKpiDataException;
use App\Services\SalesKpi\SalesKpiCombinedDataService;
use App\Services\SalesKpi\SalesKpiDataService;
use App\Services\SalesKpi\SalesKpiPeriodService;
use App\Support\SalesKpiRowNormalizer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SalesKpiCombinedDataServiceTest extends TestCase
{
    public function test_combined_mode_loads_and_tags_both_sources(): void
    {
        $source = new class implements SalesDataSource {
            public function fetch(string $source, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
            {
                return [[
                    'LiniPenjualan' => 'JAKARTA', 'LineTotal' => 100,
                    'Discontinue' => 0, 'StatusClearanceSale' => 'BUKAN CLEARANCE SALE',
                    'Grade' => 'A',
                ]];
            }
        };
        $service = new SalesKpiCombinedDataService(new SalesKpiDataService($source, new SalesKpiRowNormalizer()));
        $filter = SalesKpiFilter::from('combined', '2026-07-20');
        $period = (new SalesKpiPeriodService())->make($filter->selectedDate);

        $dataset = $service->load($filter, $period);

        self::assertSame(['AS_INGCO', 'AS_SMI'], array_values(array_unique(array_map(
            static fn ($row): string => $row->database,
            $dataset->current,
        ))));
    }

    public function test_failure_of_one_source_aborts_combined_mode_and_names_it(): void
    {
        $source = new class implements SalesDataSource {
            public function fetch(string $source, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
            {
                if ($source === 'SMI') {
                    throw new SalesDataException('SMI', 'connection failed');
                }

                return [];
            }
        };
        $service = new SalesKpiCombinedDataService(new SalesKpiDataService($source, new SalesKpiRowNormalizer()));
        $filter = SalesKpiFilter::from('combined', '2026-07-20');
        $period = (new SalesKpiPeriodService())->make($filter->selectedDate);

        try {
            $service->load($filter, $period);
            self::fail('Expected SalesKpiDataException.');
        } catch (SalesKpiDataException $exception) {
            self::assertSame(['SMI'], $exception->failedSources());
        }
    }
}
```

- [ ] **Step 2: Run the test and verify RED**

```bash
php artisan test tests/Unit/SalesKpiCombinedDataServiceTest.php
```

Expected: FAIL because the dataset and combined coordinator do not exist.

- [ ] **Step 3: Create the immutable dataset**

Create `app/Data/SalesKpi/SalesKpiDataset.php`:

```php
<?php

declare(strict_types=1);

namespace App\Data\SalesKpi;

final class SalesKpiDataset
{
    /**
     * @param list<SalesKpiTransaction> $current
     * @param list<SalesKpiTransaction> $growthCurrent
     * @param list<SalesKpiTransaction> $growthPrevious
     */
    public function __construct(
        public readonly array $current,
        public readonly array $growthCurrent,
        public readonly array $growthPrevious,
    ) {
    }

    /** @param list<self> $datasets */
    public static function merge(array $datasets): self
    {
        return new self(
            current: array_merge(...array_map(static fn (self $item): array => $item->current, $datasets)),
            growthCurrent: array_merge(...array_map(static fn (self $item): array => $item->growthCurrent, $datasets)),
            growthPrevious: array_merge(...array_map(static fn (self $item): array => $item->growthPrevious, $datasets)),
        );
    }
}
```

- [ ] **Step 4: Make KPI data exceptions carry all failed sources**

Update `SalesKpiDataException`:

```php
final class SalesKpiDataException extends RuntimeException
{
    /** @param list<string> $sources */
    public function __construct(
        public readonly array $sources,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function forSource(string $source, string $message, ?Throwable $previous = null): self
    {
        return new self([strtoupper(trim($source))], $message, $previous);
    }

    /** @param list<string> $sources */
    public static function forSources(array $sources, string $message): self
    {
        return new self(array_values(array_unique(array_map('strtoupper', $sources))), $message);
    }

    /** @return list<string> */
    public function failedSources(): array
    {
        return $this->sources;
    }
}
```

Replace existing `new SalesKpiDataException($source, ...)` calls with `SalesKpiDataException::forSource($source, ...)`.

- [ ] **Step 5: Refactor single-source loading**

Change `SalesKpiDataService::load()` to `loadSource()`:

```php
public function loadSource(string $source, SalesKpiPeriod $period): SalesKpiDataset
{
    $databaseKey = 'AS_'.strtoupper(trim($source));

    try {
        $current = $this->fetchAndNormalize($source, $databaseKey, $period->currentStart, $period->currentEnd);
        $growthCurrent = $period->requiresSeparateGrowthCurrentQuery()
            ? $this->fetchAndNormalize($source, $databaseKey, $period->growthCurrentStart, $period->growthCurrentEnd)
            : $current;
        $growthPrevious = $this->fetchAndNormalize($source, $databaseKey, $period->growthPreviousStart, $period->growthPreviousEnd);

        return new SalesKpiDataset($current, $growthCurrent, $growthPrevious);
    } catch (SalesKpiDataException $exception) {
        throw $exception;
    } catch (\Throwable $exception) {
        throw SalesKpiDataException::forSource(
            $source,
            'Function dbo.fn_PenjualanLini tidak dapat dijalankan untuk periode yang dipilih.',
            $exception,
        );
    }
}
```

Update `fetchAndNormalize()` to pass `$databaseKey` into `SalesKpiRowNormalizer::normalize()`.

- [ ] **Step 6: Implement combined coordination and failure collection**

Create `SalesKpiCombinedDataService`:

```php
<?php

declare(strict_types=1);

namespace App\Services\SalesKpi;

use App\Data\SalesKpi\SalesKpiDataset;
use App\Data\SalesKpi\SalesKpiFilter;
use App\Data\SalesKpi\SalesKpiPeriod;
use App\Exceptions\SalesKpiDataException;

final class SalesKpiCombinedDataService
{
    public function __construct(private readonly SalesKpiDataService $singleSource)
    {
    }

    public function load(SalesKpiFilter $filter, SalesKpiPeriod $period): SalesKpiDataset
    {
        $datasets = [];
        $failed = [];

        foreach ($filter->sources() as $source) {
            try {
                $datasets[] = $this->singleSource->loadSource($source, $period);
            } catch (SalesKpiDataException $exception) {
                $failed = array_merge($failed, $exception->failedSources());
            }
        }

        if ($failed !== []) {
            throw SalesKpiDataException::forSources(
                $failed,
                'Satu atau lebih database KPI gagal dimuat.',
            );
        }

        return SalesKpiDataset::merge($datasets);
    }
}
```

- [ ] **Step 7: Update data-service tests and fake source fixtures**

Change test expectations from associative arrays to `SalesKpiDataset` properties and ensure fake rows include source tagging through the normalizer. Preserve the existing assertion that July 20 performs two fetches per selected source and March 31 performs three.

- [ ] **Step 8: Run focused tests**

```bash
php artisan test tests/Unit/SalesKpiDataServiceTest.php tests/Unit/SalesKpiCombinedDataServiceTest.php
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Data/SalesKpi/SalesKpiDataset.php app/Exceptions/SalesKpiDataException.php app/Services/SalesKpi/SalesKpiDataService.php app/Services/SalesKpi/SalesKpiCombinedDataService.php tests/Fakes/FakeSalesDataSource.php tests/Unit/SalesKpiDataServiceTest.php tests/Unit/SalesKpiCombinedDataServiceTest.php
git commit -m "feat: load combined KPI data atomically"
```

---

### Task 4: Aggregate Per Source and Calculate Combined KPI Scores

**Files:**
- Create: `app/Data/SalesKpi/SalesKpiLineAggregate.php`
- Create: `app/Services/SalesKpi/SalesKpiAggregator.php`
- Modify: `app/Data/SalesKpi/SalesLineKpi.php`
- Modify: `app/Data/SalesKpi/SalesKpiReport.php`
- Modify: `app/Services/SalesKpi/SalesKpiCalculator.php`
- Modify: `app/Services/SalesKpi/SalesKpiService.php`
- Create: `tests/Unit/SalesKpiAggregatorTest.php`
- Modify: `tests/Unit/SalesKpiCalculatorTest.php`
- Modify: `tests/Unit/SalesKpiServiceTest.php`

**Interfaces:**
- Produces: `SalesKpiAggregator::aggregate(SalesKpiDataset $dataset): array` of `SalesKpiLineAggregate`.
- Produces: `SalesKpiCalculator::calculate(SalesKpiFilter $filter, SalesKpiPeriod $period, array $aggregates, array $detailRows): SalesKpiReport`.
- Produces: `SalesLineKpi::$sources` keyed by `AS_INGCO` and/or `AS_SMI` with `SalesKpiSourceContribution` values.
- Preserves every approved scoring formula and weighted-summary rule.

- [ ] **Step 1: Write failing aggregation and combined-score tests**

Create `tests/Unit/SalesKpiAggregatorTest.php` with transactions from both databases:

```php
public function test_same_line_from_both_databases_merges_and_keeps_source_totals(): void
{
    $dataset = new SalesKpiDataset(
        current: [
            $this->tx('AS_INGCO', 'JAKARTA', 300.0),
            $this->tx('AS_SMI', ' jakarta ', 200.0),
            $this->tx('AS_SMI', 'GERAI KATAMSO', 50.0),
        ],
        growthCurrent: [],
        growthPrevious: [],
    );

    $rows = (new SalesKpiAggregator())->aggregate($dataset);

    self::assertCount(2, $rows);
    self::assertSame(300.0, $rows['JAKARTA']->source('AS_INGCO')->currentOmzet);
    self::assertSame(200.0, $rows['JAKARTA']->source('AS_SMI')->currentOmzet);
    self::assertSame(500.0, $rows['JAKARTA']->currentOmzet());
}
```

Add combined calculator cases to `SalesKpiCalculatorTest.php`:

```php
public function test_combined_growth_is_calculated_after_summing_sources(): void
{
    // INGCO: 300 current / 250 previous, SMI: 200 current / 150 previous.
    // Combined growth = (500 - 400) / 400 = 25%, capped at 10 points.
    $report = $this->calculateCombinedReport(/* fixtures with the values above */);

    self::assertSame(0.25, $report->line('JAKARTA')->growthRate);
    self::assertSame(10.0, $report->line('JAKARTA')->growthScore);
}

public function test_combined_target_sums_available_source_targets_and_warns_for_missing_source(): void
{
    $report = $this->calculateCombinedReport(
        targets: ['AS_INGCO' => ['JAKARTA' => 500.0], 'AS_SMI' => []],
    );

    $line = $report->line('JAKARTA');
    self::assertSame(500.0, $line->target);
    self::assertSame(['AS_SMI'], $line->missingTargetSources);
    self::assertNotNull($line->targetScore);
}
```

Use concrete helper fixtures rather than mocks; the helper must construct `SalesKpiDataset`, call the real aggregator, and then call the real calculator.

- [ ] **Step 2: Run the focused tests and verify RED**

```bash
php artisan test tests/Unit/SalesKpiAggregatorTest.php tests/Unit/SalesKpiCalculatorTest.php
```

Expected: FAIL because source aggregates and combined calculator inputs do not exist.

- [ ] **Step 3: Create per-line source aggregate data**

Create `SalesKpiLineAggregate` with:

```php
public function __construct(
    public readonly string $line,
    public readonly string $lineKey,
    /** @var array<string,SalesKpiSourceContribution> */
    public readonly array $sources,
) {
}

public function source(string $database): SalesKpiSourceContribution
{
    return $this->sources[$database] ?? new SalesKpiSourceContribution(
        database: $database,
        target: null,
        targetConfigured: false,
        currentOmzet: 0.0,
        growthCurrentOmzet: 0.0,
        previousOmzet: 0.0,
        discontinuedOmzet: 0.0,
        clearanceOmzet: 0.0,
        gradeAOmzet: 0.0,
        gradeBOmzet: 0.0,
    );
}

public function currentOmzet(): float
{
    return array_sum(array_map(static fn (SalesKpiSourceContribution $source): float => $source->currentOmzet, $this->sources));
}
```

Add equivalent sum methods for Growth current, previous, discontinued, clearance, Grade A, and Grade B.

- [ ] **Step 4: Implement aggregation by line and database**

`SalesKpiAggregator::aggregate()` must:

1. Collect labels from current, Growth-current, and previous datasets.
2. Group by normalized `lineKey` and transaction `database`.
3. Sum each metric independently.
4. Include a line that exists only in previous or one database.
5. Return an associative array keyed by `lineKey` for deterministic lookup.

Core accumulation pattern:

```php
$bucket[$row->lineKey][$row->database]['current'] += $row->lineTotal;
$bucket[$row->lineKey][$row->database]['discontinued'] += $row->discontinued ? $row->lineTotal : 0.0;
$bucket[$row->lineKey][$row->database]['clearance'] += $row->clearance ? $row->lineTotal : 0.0;
$bucket[$row->lineKey][$row->database]['grade_a'] += $row->grade === 'A' ? $row->lineTotal : 0.0;
$bucket[$row->lineKey][$row->database]['grade_b'] += $row->grade === 'B' ? $row->lineTotal : 0.0;
```

Do not combine discontinued and clearance in the aggregator; keep them separate so double counting remains explicit in the calculator.

- [ ] **Step 5: Extend `SalesLineKpi` with source contributions and warnings**

Add constructor properties:

```php
/** @var array<string,SalesKpiSourceContribution> */
public readonly array $sources,
/** @var list<string> */
public readonly array $missingTargetSources,
```

Add:

```php
public function source(string $database): SalesKpiSourceContribution
{
    return $this->sources[$database] ?? new SalesKpiSourceContribution(/* zero values as above */);
}

public function hasPartialTarget(): bool
{
    return $this->target !== null && $this->missingTargetSources !== [];
}
```

- [ ] **Step 6: Refactor calculator inputs without changing formulas**

Inject `SalesKpiTargetService` into `SalesKpiCalculator` and change `calculate()` to receive aggregates. For each aggregate:

```php
$sourceTargets = $this->targetService->forLine($filter, $aggregate->lineKey);
$combinedTarget = $this->targetService->combined($sourceTargets);
$missingSources = $this->targetService->missingSources($sourceTargets);

$sources = [];
foreach ($filter->databaseKeys() as $databaseKey) {
    $raw = $aggregate->source($databaseKey);
    $target = $sourceTargets[$databaseKey] ?? null;
    $sources[$databaseKey] = new SalesKpiSourceContribution(
        database: $databaseKey,
        target: $target,
        targetConfigured: $target !== null,
        currentOmzet: $raw->currentOmzet,
        growthCurrentOmzet: $raw->growthCurrentOmzet,
        previousOmzet: $raw->previousOmzet,
        discontinuedOmzet: $raw->discontinuedOmzet,
        clearanceOmzet: $raw->clearanceOmzet,
        gradeAOmzet: $raw->gradeAOmzet,
        gradeBOmzet: $raw->gradeBOmzet,
    );
}
```

Then calculate totals from the aggregate and reuse the existing methods `targetScore`, `growthScore`, `riskScore`, `gradeScores`, `normalizeScore`, `status`, and `weightedScore` unchanged except for their inputs.

- [ ] **Step 7: Update service orchestration**

`SalesKpiService::generate()` becomes:

```php
$period = $this->periodService->make($filter->selectedDate);
$dataset = $this->dataService->load($filter, $period);
$aggregates = $this->aggregator->aggregate($dataset);

return $this->calculator->calculate(
    filter: $filter,
    period: $period,
    aggregates: $aggregates,
    detailRows: $dataset->current,
);
```

Constructor dependencies are `SalesKpiPeriodService`, `SalesKpiCombinedDataService`, `SalesKpiAggregator`, and `SalesKpiCalculator`.

- [ ] **Step 8: Run calculator, aggregator, and service tests**

```bash
php artisan test tests/Unit/SalesKpiAggregatorTest.php tests/Unit/SalesKpiCalculatorTest.php tests/Unit/SalesKpiServiceTest.php
```

Expected: PASS, including all previous single-database formula tests.

- [ ] **Step 9: Commit**

```bash
git add app/Data/SalesKpi/SalesKpiLineAggregate.php app/Data/SalesKpi/SalesLineKpi.php app/Data/SalesKpi/SalesKpiReport.php app/Services/SalesKpi/SalesKpiAggregator.php app/Services/SalesKpi/SalesKpiCalculator.php app/Services/SalesKpi/SalesKpiService.php tests/Unit/SalesKpiAggregatorTest.php tests/Unit/SalesKpiCalculatorTest.php tests/Unit/SalesKpiServiceTest.php
git commit -m "feat: calculate KPI from combined source aggregates"
```

---

### Task 5: Add Server-Side Detail DataTable Endpoint

**Files:**
- Create: `app/Data/SalesKpi/SalesKpiDetailPage.php`
- Create: `app/Http/Requests/SalesKpiDetailRequest.php`
- Create: `app/Services/SalesKpi/SalesKpiDetailQueryService.php`
- Modify: `app/Http/Controllers/SalesKpiController.php`
- Modify: `routes/web.php`
- Create: `tests/Unit/SalesKpiDetailQueryServiceTest.php`
- Create: `tests/Feature/SalesKpiDetailEndpointTest.php`

**Interfaces:**
- Route: `GET /sales-kpi/details`, name `sales-kpi.details`.
- Produces JSON: `draw`, `recordsTotal`, `recordsFiltered`, and `data`.
- Supports detail types: `all`, `risk`, `grade`.
- Supports sort columns: `database`, `invoice_date`, `invoice_number`, `line`, `item_code`, `item_name`, `grade`, `quantity`, `line_total`.
- Guarantees that invalid sort columns are rejected before reaching the query service.

- [ ] **Step 1: Write failing pagination and endpoint tests**

Create `SalesKpiDetailQueryServiceTest` with 25 real transaction DTOs and assert:

```php
$page = $service->paginate(
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
```

Create `SalesKpiDetailEndpointTest` that binds fake INGCO and SMI data, calls:

```text
/sales-kpi/details?database=combined&end_date=2026-07-20&discontinue=all&clearance=all&grade=ALL&detail_type=all&draw=3&start=0&length=10&order_column=database&order_dir=asc
```

and asserts:

```php
$response->assertOk()->assertJsonPath('draw', 3);
$response->assertJsonPath('data.0.database', 'AS_INGCO');
$response->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
```

- [ ] **Step 2: Run tests and verify RED**

```bash
php artisan test tests/Unit/SalesKpiDetailQueryServiceTest.php tests/Feature/SalesKpiDetailEndpointTest.php
```

Expected: FAIL because request, service, DTO, controller action, and route do not exist.

- [ ] **Step 3: Implement the immutable detail page**

```php
final class SalesKpiDetailPage
{
    /** @param list<array<string,string|float|bool>> $rows */
    public function __construct(
        public readonly int $recordsTotal,
        public readonly int $recordsFiltered,
        public readonly array $rows,
    ) {
    }
}
```

- [ ] **Step 4: Implement strict detail request validation**

`SalesKpiDetailRequest` extends `SalesKpiRequest` and adds:

```php
'detail_type' => ['required', Rule::in(['all', 'risk', 'grade'])],
'draw' => ['required', 'integer', 'min:0'],
'start' => ['required', 'integer', 'min:0'],
'length' => ['required', 'integer', 'min:10', 'max:100'],
'order_column' => ['required', Rule::in([
    'database', 'invoice_date', 'invoice_number', 'line',
    'item_code', 'item_name', 'grade', 'quantity', 'line_total',
])],
'order_dir' => ['required', Rule::in(['asc', 'desc'])],
```

Normalize `detail_type`, `order_column`, and `order_dir` to lowercase in `prepareForValidation()` while retaining the parent normalization.

- [ ] **Step 5: Implement filtering, sorting, and pagination**

`SalesKpiDetailQueryService::paginate()` must:

1. Count all supplied rows as `recordsTotal`.
2. Apply `risk` as `discontinued || clearance`.
3. Apply `grade` as Grade A or B only.
4. Apply case-insensitive search across database, invoice, line, item code, and item name.
5. Sort using a fixed property map; never use user input as a PHP property directly.
6. Slice with `array_slice($filtered, $start, $length)`.
7. Map rows to JSON keys including `database`.

Property map:

```php
private const ORDER_ACCESSORS = [
    'database' => 'database',
    'invoice_date' => 'invoiceDate',
    'invoice_number' => 'invoiceNumber',
    'line' => 'line',
    'item_code' => 'itemCode',
    'item_name' => 'itemName',
    'grade' => 'grade',
    'quantity' => 'quantity',
    'line_total' => 'lineTotal',
];
```

- [ ] **Step 6: Add controller JSON action and route**

Controller action:

```php
public function details(
    SalesKpiDetailRequest $request,
    SalesKpiService $service,
    SalesKpiDetailQueryService $queryService,
): \Illuminate\Http\JsonResponse {
    $report = $service->generate($request->filter());
    $page = $queryService->paginate(
        rows: $report->detailRows,
        detailType: (string) $request->validated('detail_type'),
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
}
```

Catch `SalesKpiDataException`, log it, and return HTTP 503 JSON with safe `message` and `failed_databases` labels.

Route:

```php
Route::get('/sales-kpi/details', [SalesKpiController::class, 'details'])
    ->name('sales-kpi.details');
```

- [ ] **Step 7: Run focused tests**

```bash
php artisan test tests/Unit/SalesKpiDetailQueryServiceTest.php tests/Feature/SalesKpiDetailEndpointTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Data/SalesKpi/SalesKpiDetailPage.php app/Http/Requests/SalesKpiDetailRequest.php app/Services/SalesKpi/SalesKpiDetailQueryService.php app/Http/Controllers/SalesKpiController.php routes/web.php tests/Unit/SalesKpiDetailQueryServiceTest.php tests/Feature/SalesKpiDetailEndpointTest.php
git commit -m "feat: add server-side KPI detail datatable"
```

---

### Task 6: Replace Summary Chart with Recap DataTable and Show Source Contributions

**Files:**
- Modify: `resources/views/sales-kpi/index.blade.php`
- Create: `public/js/sales-kpi-datatable.js`
- Modify: `public/js/sales-kpi-charts.js`
- Modify: `tests/Feature/SalesKpiPageTest.php`
- Modify: `tests/manual_sales_kpi_assets.php`

**Interfaces:**
- Initial recap tables use `data-kpi-recap-table`.
- Detail table containers use `data-kpi-detail-table` and carry the endpoint URL and `detail_type`.
- JavaScript reads the active page query and calls `sales-kpi.details` without changing score filters.
- `Database` detail column is rendered only when `$filter->isCombined()`.

- [ ] **Step 1: Add failing page assertions**

Add to `SalesKpiPageTest`:

```php
$response->assertSee('value="combined"', false);
$response->assertSee('Target AS_INGCO');
$response->assertSee('Target AS_SMI');
$response->assertSee('Target Gabungan');
$response->assertSee('Omzet AS_INGCO');
$response->assertSee('Omzet AS_SMI');
$response->assertSee('Omzet Gabungan');
$response->assertSee('data-kpi-recap-table', false);
$response->assertDontSee('data-kpi-chart="overall"', false);
$response->assertSee(route('sales-kpi.details'), false);
```

Add a single-mode assertion that the detail column header `Database` is absent from rendered table headers.

- [ ] **Step 2: Run page tests and verify RED**

```bash
php artisan test tests/Feature/SalesKpiPageTest.php
```

Expected: FAIL because the combined UI and recap DataTable markup are absent.

- [ ] **Step 3: Update the database filter and summary columns**

Database options:

```blade
<option value="combined" @selected($filter->database === 'combined')>GABUNGAN</option>
<option value="ingco" @selected($filter->database === 'ingco')>AS_INGCO</option>
<option value="smi" @selected($filter->database === 'smi')>AS_SMI</option>
```

For combined mode, render the exact summary columns approved in the design. Read source values only through:

```blade
@php
    $ingco = $row->source('AS_INGCO');
    $smi = $row->source('AS_SMI');
@endphp
```

Show `Target belum diatur: AS_SMI` when `$row->missingTargetSources` contains that source.

For single mode, retain the compact single-source columns instead of showing zero columns for the unselected database.

- [ ] **Step 4: Remove only the large Summary chart**

Delete the Summary canvas and its `overall` chart configuration. Keep small canvases on Target, Growth, Discontinued/Clearance, and Grade tabs. Update `sales-kpi-charts.js` so missing `overall` canvas is normal and does not log an error.

- [ ] **Step 5: Add recap table controls**

Above each recap table render:

```blade
<div class="d-flex flex-wrap gap-2 justify-content-between mb-2" data-kpi-table-controls>
    <input type="search" class="form-control form-control-sm w-auto" data-kpi-table-search placeholder="Cari LiniPenjualan">
    <select class="form-select form-select-sm w-auto" data-kpi-table-length>
        <option value="10">10 baris</option>
        <option value="25">25 baris</option>
        <option value="50">50 baris</option>
    </select>
    <div class="small text-secondary" data-kpi-table-info></div>
</div>
```

Mark sortable headers with `data-sort-key` and numeric cells with `data-sort-value="{{ $rawNumber }}"`.

- [ ] **Step 6: Implement local recap search, sorting, and pagination**

`public/js/sales-kpi-datatable.js` must:

- initialize every `[data-kpi-recap-table]` independently;
- keep all rows in the DOM but hide nonmatching and non-page rows;
- search using normalized row text;
- sort numeric columns by `data-sort-value` and text columns with `localeCompare('id')`;
- show Previous/Next buttons and `Menampilkan X–Y dari Z baris`;
- reset to page 1 after search or page-length changes;
- use no external JavaScript dependency.

- [ ] **Step 7: Replace initial detail rows with AJAX containers**

Do not loop through `$report->detailRows` or `$report->gradeDetailRows()` in Blade. Render one reusable detail table shell per relevant tab, with loading, empty, and error states. Include the source column only when combined:

```blade
@if ($filter->isCombined())
    <th data-column="database">Database</th>
@endif
```

The AJAX URL must be built from the current filter query plus DataTable parameters. The returned `database` value remains present in the row object even when the column is hidden.

- [ ] **Step 8: Load the new asset**

```blade
<script src="{{ asset('js/sales-kpi-datatable.js') }}"></script>
```

Pass configuration through a JSON script element using `Illuminate\Support\Js::from()`; do not concatenate unescaped query strings in JavaScript.

- [ ] **Step 9: Run feature and asset checks**

```bash
php artisan test tests/Feature/SalesKpiPageTest.php
php tests/manual_sales_kpi_assets.php
node --check public/js/sales-kpi-datatable.js
node --check public/js/sales-kpi-charts.js
```

Expected: all commands exit 0.

- [ ] **Step 10: Commit**

```bash
git add resources/views/sales-kpi/index.blade.php public/js/sales-kpi-datatable.js public/js/sales-kpi-charts.js tests/Feature/SalesKpiPageTest.php tests/manual_sales_kpi_assets.php
git commit -m "feat: add hybrid KPI datatable interface"
```

---

### Task 7: Extend Excel and PDF Exports for Combined Source Contributions

**Files:**
- Modify: `app/Exports/SalesKpi/SalesKpiExport.php`
- Modify: `resources/views/sales-kpi/pdf.blade.php`
- Modify: `app/Http/Controllers/SalesKpiController.php`
- Modify: `tests/Feature/SalesKpiExportTest.php`

**Interfaces:**
- Excel still contains exactly six sheets.
- Combined recap sheets include source and combined columns specified in the approved design.
- Detail Excel always includes `Database` as the first data-source column.
- Export filenames use `GABUNGAN`, `AS_INGCO`, or `AS_SMI`.
- PDF includes source contributions in combined mode and remains management-focused.

- [ ] **Step 1: Write failing export assertions**

Extend `SalesKpiExportTest`:

```php
public function test_combined_excel_contains_source_columns_and_all_filtered_details(): void
{
    $this->bindCombinedKpiFakeWithTwentyFiveDetails();

    $response = $this->get('/sales-kpi/export/excel?database=combined&end_date=2026-07-20&discontinue=all&clearance=all&grade=ALL&search=HAMMER');

    $response->assertOk();
    $workbook = $this->openDownloadedWorkbook($response);
    self::assertSame('Target AS_INGCO', $workbook->getSheetByName('Ringkasan KPI')->getCell('B6')->getValue());
    self::assertSame('Database', $workbook->getSheetByName('Detail Item')->getCell('A6')->getValue());
    self::assertSame(25, $this->countDataRows($workbook->getSheetByName('Detail Item')));
}

public function test_combined_pdf_identifies_mode_and_source_contributions(): void
{
    $this->bindCombinedKpiFake();

    $response = $this->get('/sales-kpi/export/pdf?database=combined&end_date=2026-07-20&discontinue=all&clearance=all&grade=ALL');

    $response->assertOk();
    self::assertStringContainsString('Sales_KPI_GABUNGAN_2026-07-20.pdf', (string) $response->headers->get('content-disposition'));
}
```

Use the existing workbook helper patterns in this test file; do not parse XLSX as plain text.

- [ ] **Step 2: Run export tests and verify RED**

```bash
php artisan test tests/Feature/SalesKpiExportTest.php
```

Expected: FAIL because source columns and combined filenames are absent.

- [ ] **Step 3: Build mode-specific Excel headings and rows**

In each sheet method, branch on `$this->report->filter->isCombined()`.

Combined Summary headings must be:

```php
[
    'LiniPenjualan', 'Target AS_INGCO', 'Target AS_SMI', 'Target Gabungan',
    'Omzet AS_INGCO', 'Omzet AS_SMI', 'Omzet Gabungan',
    'Skor Target', 'Skor Growth', 'Skor Discontinued/Clearance',
    'Skor Grade A/B', 'Skor Akhir', 'Status',
]
```

Implement the other four recap sheet headings exactly as listed in sections 10.1–10.4 of the approved design. Numeric values must remain floats; missing values must be `null`, not formatted strings.

- [ ] **Step 4: Export all filtered details independently of page length**

Keep export generation based on `SalesKpiService::generate($filter)` and `$report->detailRows`. Do not call the detail endpoint or apply `start`/`length` parameters. Detail headings begin with:

```php
['Database', 'InvoiceDate', 'InvoiceNumber', 'LiniPenjualan', 'ItemCode', 'ItemName', 'Grade', 'Discontinue', 'StatusClearanceSale', 'Quantity', 'LineTotal']
```

- [ ] **Step 5: Update metadata and filenames**

Excel and PDF metadata use `$filter->databaseLabel()`. Controller filenames continue using `$filter->databaseKey()`, which now returns `GABUNGAN` for combined mode.

- [ ] **Step 6: Update PDF tables**

Combined PDF Summary includes Target and Omzet columns for both sources plus combined values. Detail transaction lists remain excluded. Add a notes section that lists partial target configuration:

```blade
@foreach ($report->rows as $row)
    @if ($row->missingTargetSources !== [])
        <li>{{ $row->line }}: target belum diatur untuk {{ implode(', ', $row->missingTargetSources) }}.</li>
    @endif
@endforeach
```

- [ ] **Step 7: Run export tests**

```bash
php artisan test tests/Feature/SalesKpiExportTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Exports/SalesKpi/SalesKpiExport.php resources/views/sales-kpi/pdf.blade.php app/Http/Controllers/SalesKpiController.php tests/Feature/SalesKpiExportTest.php
git commit -m "feat: export combined KPI source contributions"
```

---

### Task 8: Provide Safe Source-Specific Failure Messages

**Files:**
- Modify: `app/Http/Controllers/SalesKpiController.php`
- Modify: `resources/views/sales-kpi/index.blade.php`
- Modify: `tests/Feature/SalesKpiPageTest.php`
- Modify: `tests/Feature/SalesKpiDetailEndpointTest.php`

**Interfaces:**
- Produces a combined-mode page message naming all failed databases.
- Produces HTTP 503 detail JSON with a safe message and `failed_databases` labels.
- Preserves exact missing-column messages without exposing stack traces.

- [ ] **Step 1: Write failing source-specific error tests**

Add page tests:

```php
public function test_combined_failure_names_smi_and_hides_partial_ingco_data(): void
{
    $this->bindSourceThatFailsOnly('SMI');

    $response = $this->get('/sales-kpi?database=combined&end_date=2026-07-20&discontinue=all&clearance=all&grade=ALL');

    $response->assertOk();
    $response->assertSee('Koneksi ke database AS_SMI gagal.');
    $response->assertSee('Data AS_INGCO tidak ditampilkan agar hasil gabungan tidak menyesatkan.');
    $response->assertDontSee('300.000.000');
    $response->assertDontSee('password');
    $response->assertDontSee('SQLSTATE');
}
```

Add a two-source failure test expecting `AS_INGCO` and `AS_SMI` in the message.

- [ ] **Step 2: Run feature tests and verify RED**

```bash
php artisan test tests/Feature/SalesKpiPageTest.php tests/Feature/SalesKpiDetailEndpointTest.php
```

Expected: FAIL because the current friendly error is generic.

- [ ] **Step 3: Implement deterministic safe message formatting**

Controller helper:

```php
private function friendlyError(SalesKpiFilter $filter, SalesKpiDataException $exception): string
{
    if (str_starts_with($exception->getMessage(), 'Kolom ')) {
        return $exception->getMessage();
    }

    $labels = array_map(static fn (string $source): string => 'AS_'.$source, $exception->failedSources());

    if ($filter->isCombined()) {
        $failedText = implode(' dan ', $labels);
        $successful = array_values(array_diff(['AS_INGCO', 'AS_SMI'], $labels));
        $message = "Laporan KPI gabungan tidak dapat dimuat.\n\nKoneksi ke database {$failedText} gagal.";
        if ($successful !== []) {
            $message .= "\nData ".implode(' dan ', $successful).' tidak ditampilkan agar hasil gabungan tidak menyesatkan.';
        }

        return $message;
    }

    return 'Laporan KPI belum dapat dimuat. Koneksi ke database '.$filter->databaseLabel().' gagal.';
}
```

Update all callers to pass `$filter`. Render line breaks with escaped text and CSS `white-space: pre-line`; do not use `{!! !!}`.

- [ ] **Step 4: Return safe JSON failures**

Detail endpoint catch block:

```php
return response()->json([
    'message' => $this->friendlyError($filter, $exception),
    'failed_databases' => array_map(
        static fn (string $source): string => 'AS_'.$source,
        $exception->failedSources(),
    ),
], 503);
```

- [ ] **Step 5: Run feature tests**

```bash
php artisan test tests/Feature/SalesKpiPageTest.php tests/Feature/SalesKpiDetailEndpointTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/SalesKpiController.php resources/views/sales-kpi/index.blade.php tests/Feature/SalesKpiPageTest.php tests/Feature/SalesKpiDetailEndpointTest.php
git commit -m "fix: report failed KPI database sources safely"
```

---

### Task 9: Full Regression, Documentation, and Distribution Package

**Files:**
- Modify: `docs/SALES-KPI-CALCULATION.md`
- Modify: `README.md`
- Add: `docs/superpowers/specs/2026-07-26-sales-kpi-combined-database-design.md`
- Add: `docs/superpowers/plans/2026-07-26-sales-kpi-combined-database-implementation-plan.md`
- Modify only if needed by verification: `tests/manual_sales_kpi.php`, `tests/manual_sales_kpi_assets.php`, `UPDATE_AFTER_OVERWRITE.bat`

**Interfaces:**
- Documents mode names, combined formulas, target behavior, DataTable endpoint, export rules, and failure behavior.
- Produces a final ZIP that can overwrite the existing Laravel project while preserving the user’s `.env` separately.

- [ ] **Step 1: Update calculation documentation with exact combined formulas**

Add sections showing:

```text
Combined Sales = AS_INGCO Sales + AS_SMI Sales
Combined Target = configured AS_INGCO Target + configured AS_SMI Target
Combined Growth = (Combined Current - Combined Previous) / Combined Previous
Overall KPI = Σ(Line Score × Combined Target) / Σ(Combined Target)
```

Document that one missing source target contributes zero with a warning, while both missing targets make target-dependent components unavailable.

- [ ] **Step 2: Update installation and usage documentation**

README must include:

```text
/sales-kpi?database=combined
/sales-kpi?database=ingco
/sales-kpi?database=smi
```

Document that combined mode requires both SQL Server connections to work and that detail exports include the source database.

- [ ] **Step 3: Copy approved spec and plan into the repository**

Copy the exact reviewed files into:

```text
docs/superpowers/specs/2026-07-26-sales-kpi-combined-database-design.md
docs/superpowers/plans/2026-07-26-sales-kpi-combined-database-implementation-plan.md
```

- [ ] **Step 4: Run the complete automated suite**

```bash
php artisan optimize:clear
php artisan test
```

Expected: exit 0 with zero failures.

- [ ] **Step 5: Run syntax and manual asset checks**

```bash
find app config routes tests -name '*.php' -print0 | xargs -0 -n1 php -l
node --check public/js/sales-kpi-datatable.js
node --check public/js/sales-kpi-charts.js
php tests/manual_dashboard.php
php tests/manual_web_assets.php
php tests/manual_sales_kpi.php
php tests/manual_sales_kpi_assets.php
```

Expected: every command exits 0.

- [ ] **Step 6: Run targeted acceptance checks**

Use fake or test connections and verify:

```bash
php artisan test --filter='combined|Combined|datatable|DataTable|export|failure'
```

Expected: all combined aggregation, source failure, detail endpoint, and export tests pass.

- [ ] **Step 7: Inspect routes and configuration**

```bash
php artisan route:list --name=sales-kpi
php artisan config:show sales_kpi_targets
```

Expected routes include `sales-kpi.index`, `sales-kpi.details`, `sales-kpi.export.excel`, and `sales-kpi.export.pdf`; target config shows separate `AS_INGCO` and `AS_SMI` sections.

- [ ] **Step 8: Commit documentation and verification updates**

```bash
git add README.md docs tests/manual_sales_kpi.php tests/manual_sales_kpi_assets.php UPDATE_AFTER_OVERWRITE.bat
git commit -m "docs: document combined sales KPI mode"
```

- [ ] **Step 9: Create the distribution ZIP outside the repository**

From the repository parent directory:

```bash
zip -r /mnt/data/Sales_Report_Dashboard_Laravel12_KPI_Combined.zip Sales_Report_Dashboard_Laravel12 \
    -x 'Sales_Report_Dashboard_Laravel12/.git/*' \
       'Sales_Report_Dashboard_Laravel12/.env' \
       'Sales_Report_Dashboard_Laravel12/vendor/*' \
       'Sales_Report_Dashboard_Laravel12/node_modules/*' \
       'Sales_Report_Dashboard_Laravel12/storage/logs/*'
```

- [ ] **Step 10: Verify the ZIP contents and integrity**

```bash
unzip -t /mnt/data/Sales_Report_Dashboard_Laravel12_KPI_Combined.zip
unzip -l /mnt/data/Sales_Report_Dashboard_Laravel12_KPI_Combined.zip | grep -E 'sales-kpi-datatable.js|SalesKpiCombinedDataService.php|SalesKpiDetailQueryService.php|2026-07-26-sales-kpi-combined-database'
```

Expected: integrity check reports no errors and all four required feature artifacts are listed.

---

## Final Self-Review Checklist

Before execution is declared complete, verify each approved requirement maps to evidence:

- `GABUNGAN`, `AS_INGCO`, and `AS_SMI` validation: Task 1 tests.
- Database source tagging and target breakdowns: Task 2 tests.
- All-or-nothing loading and failed-source collection: Task 3 tests.
- Merge by normalized `LiniPenjualan`, source totals, combined Growth, combined target, and weighted scoring: Task 4 tests.
- Server-side detail filtering, sorting, pagination, and `Database` field: Task 5 tests.
- Summary DataTable, no large summary chart, small detail charts, and source columns on all tabs: Task 6 feature tests.
- All-filtered-row Excel/PDF exports and combined headers: Task 7 tests.
- User-safe database-specific failures with no partial data: Task 8 tests.
- Full suite, syntax checks, routes, docs, and ZIP integrity: Task 9 commands.

The plan contains no changes to SQL Server data, `dbo.fn_PenjualanLini`, the existing Sales Dashboard formulas, or target storage.
