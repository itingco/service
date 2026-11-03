# Sales KPI Combined Target Settings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add environment-based administrator login, make the KPI dashboard permanently combine `AS_INGCO + AS_SMI`, and provide an authenticated SQLite-backed page for editing one combined monthly target per `LiniPenjualan`.

**Architecture:** SQL Server remains read-only and continues to supply transaction data through `dbo.fn_PenjualanLini`. A dedicated SQLite connection stores application-owned KPI targets. The existing KPI services are refactored so the public dashboard always loads both SQL Server sources, merges equal normalized `LiniPenjualan` values, and looks up one target from SQLite. Authentication uses a small session guard backed by environment credentials rather than a users table.

**Tech Stack:** PHP 8.2, Laravel 12.64, SQL Server (`sqlsrv_ingco`, `sqlsrv_smi`), SQLite, Blade, PHPUnit 11, Maatwebsite Excel, DomPDF.

## Global Constraints

- The KPI dashboard always represents `AS_INGCO + AS_SMI`; no database selector is rendered or accepted for KPI routes.
- The same normalized `LiniPenjualan` from both databases is merged into one KPI row.
- Target storage is one nullable target per normalized `LiniPenjualan`; targets are not separated by database or month.
- Target key normalization is `strtoupper(trim())` plus collapse of repeated whitespace to one space.
- Empty input and numeric zero are persisted as `NULL` and displayed as **Target belum diatur**.
- Users cannot manually add or rename a line; submitted keys must belong to the server-generated known-line catalog.
- The settings catalog is the union of current-month lines from both SQL Server databases and all existing SQLite target records.
- Existing target records remain visible when there is no current-month transaction.
- Batch target updates are all-or-nothing in one SQLite transaction.
- If either SQL Server database fails, combined KPI and target-catalog loading fail; partial data is never shown.
- All report pages, KPI pages, exports, DataTable endpoints, and target settings routes require a valid dashboard session.
- Login credentials come only from `DASHBOARD_ADMIN_USER` and `DASHBOARD_ADMIN_PASSWORD`.
- Invalid login messages are generic and never reveal which credential failed.
- Existing KPI formulas and the 90-point normalization scale remain unchanged.
- `Database` remains available on transaction detail rows for traceability, but it does not influence the combined target.
- Implement every behavior test-first: create a failing test, observe the expected failure, implement the minimum production change, then rerun the focused and regression tests.

---

## File Structure Map

### New files

- `config/dashboard_auth.php` — environment credential mapping and session key.
- `database/database.sqlite` — local target database file, ignored as runtime data but created by install/update scripts.
- `database/migrations/2026_07_28_000000_create_sales_kpi_targets_table.php` — SQLite target table.
- `app/Models/SalesKpiTarget.php` — Eloquent model pinned to the SQLite target connection.
- `app/Data/SalesKpi/SalesKpiTargetCatalogRow.php` — display DTO for the target settings table.
- `app/Services/SalesKpi/SalesKpiTargetCatalogService.php` — combines current-month lines and stored targets.
- `app/Http/Controllers/DashboardAuthController.php` — login form, login action, and logout action.
- `app/Http/Middleware/EnsureDashboardAuthenticated.php` — session-based route protection.
- `app/Http/Requests/DashboardLoginRequest.php` — login validation.
- `app/Http/Requests/SalesKpiTargetUpdateRequest.php` — normalizes submitted currency values and validates the batch shape.
- `app/Http/Controllers/SalesKpiTargetController.php` — target settings index and transactional update.
- `resources/views/auth/login.blade.php` — initial login page.
- `resources/views/sales-kpi/targets/index.blade.php` — inline target settings table.
- `app/Console/Commands/ImportLegacySalesKpiTargets.php` — one-time import from the old config.
- Focused feature/unit tests listed in each task.

### Modified files

- `config/database.php` — add `sqlite_targets` without changing the SQL Server connections.
- `.env.example` — add SQLite path and dashboard admin variables.
- `.gitignore` — ignore runtime SQLite database and SQLite journal/WAL files while retaining a `.gitkeep` if needed.
- `phpunit.xml` — use isolated SQLite target storage during tests.
- `app/Providers/AppServiceProvider.php` — remove config-array target construction and bind the SQLite-backed target service.
- `app/Services/SalesKpi/SalesKpiTargetService.php` — replace per-database config lookup with one combined target lookup and batch persistence.
- `app/Services/SalesKpi/SalesKpiDataService.php` — expose a reusable normalized range loader.
- `app/Data/SalesKpi/SalesKpiFilter.php` — make combined mode intrinsic to KPI filters.
- `app/Http/Requests/SalesKpiRequest.php` — stop validating/accepting a database selector.
- `app/Services/SalesKpi/SalesKpiCalculator.php` — use one target and one missing-target state per line.
- `app/Data/SalesKpi/SalesLineKpi.php` — replace source-specific missing-target fields with a single target-configured flag.
- `app/Http/Controllers/SalesKpiController.php` — combined-only filenames, redirects, and error messages.
- `resources/views/sales-kpi/index.blade.php` — remove database selector and source-target columns; add settings navigation.
- `resources/views/sales-kpi/pdf.blade.php` and Excel sheets — display one combined target consistently.
- `routes/web.php` and `app/Http/Kernel.php` — login routes, middleware alias, and protected route group.
- `resources/views/layouts/app.blade.php` — authenticated navigation and logout.
- `INSTALL_WINDOWS.bat`, `UPDATE_AFTER_OVERWRITE.bat`, `README.md`, and Windows install documentation — create SQLite file, migrate, import targets, and document credentials.

---

### Task 1: Add the Dedicated SQLite Target Store

**Files:**
- Modify: `config/database.php`
- Modify: `.env.example`
- Modify: `.gitignore`
- Modify: `phpunit.xml`
- Create: `database/.gitkeep`
- Create: `database/migrations/2026_07_28_000000_create_sales_kpi_targets_table.php`
- Create: `app/Models/SalesKpiTarget.php`
- Test: `tests/Feature/SalesKpiTargetMigrationTest.php`

**Interfaces:**
- Produces connection name: `sqlite_targets`.
- Produces model: `App\Models\SalesKpiTarget` with fields `sales_line` and `monthly_target`.
- The normalized `sales_line` column is unique and `monthly_target` is nullable.

- [ ] **Step 1: Write the failing migration/model test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SalesKpiTarget;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SalesKpiTargetMigrationTest extends TestCase
{
    public function test_target_table_exists_on_dedicated_sqlite_connection(): void
    {
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);

        self::assertTrue(
            Schema::connection('sqlite_targets')->hasColumns('sales_kpi_targets', [
                'id', 'sales_line', 'monthly_target', 'created_at', 'updated_at',
            ]),
        );

        $target = SalesKpiTarget::query()->create([
            'sales_line' => 'JAKARTA',
            'monthly_target' => 500_000_000,
        ]);

        self::assertSame('sqlite_targets', $target->getConnectionName());
        self::assertSame('JAKARTA', $target->sales_line);
        self::assertSame(500_000_000.0, (float) $target->monthly_target);
    }
}
```

- [ ] **Step 2: Run the focused test and verify the expected failure**

Run:

```bash
php artisan test tests/Feature/SalesKpiTargetMigrationTest.php
```

Expected: FAIL because `sqlite_targets`, the migration, and `SalesKpiTarget` do not exist.

- [ ] **Step 3: Add the dedicated SQLite connection**

Add to `config/database.php` under `connections`:

```php
'sqlite_targets' => [
    'driver' => 'sqlite',
    'url' => env('TARGET_DATABASE_URL'),
    'database' => env('TARGET_DB_DATABASE', database_path('database.sqlite')),
    'prefix' => '',
    'foreign_key_constraints' => true,
    'busy_timeout' => 5000,
    'journal_mode' => 'WAL',
    'synchronous' => 'NORMAL',
],
```

Do not change the existing default connection or either SQL Server connection.

- [ ] **Step 4: Add the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_targets')->create('sales_kpi_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('sales_line', 150)->unique();
            $table->decimal('monthly_target', 18, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_targets')->dropIfExists('sales_kpi_targets');
    }
};
```

- [ ] **Step 5: Add the model**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SalesKpiTarget extends Model
{
    protected $connection = 'sqlite_targets';

    protected $fillable = ['sales_line', 'monthly_target'];

    protected function casts(): array
    {
        return ['monthly_target' => 'float'];
    }
}
```

- [ ] **Step 6: Configure environment and test isolation**

Add to `.env.example`:

```env
TARGET_DB_DATABASE=database/database.sqlite
DASHBOARD_ADMIN_USER=admin
DASHBOARD_ADMIN_PASSWORD=change-this-password
```

Add to `phpunit.xml`:

```xml
<env name="DB_CONNECTION" value="sqlite_targets"/>
<env name="TARGET_DB_DATABASE" value=":memory:"/>
<env name="DASHBOARD_ADMIN_USER" value="admin"/>
<env name="DASHBOARD_ADMIN_PASSWORD" value="admin-test-password"/>
```

Add runtime files to `.gitignore`:

```gitignore
/database/database.sqlite
/database/database.sqlite-shm
/database/database.sqlite-wal
/database/*.sqlite-journal
```

- [ ] **Step 7: Run focused and existing tests**

```bash
php artisan test tests/Feature/SalesKpiTargetMigrationTest.php
php artisan test tests/Feature/SalesKpiPageTest.php
```

Expected: migration test and the unrelated existing page test pass. Do not run the old target-service test until Task 2 replaces its contract.

- [ ] **Step 8: Commit**

```bash
git add config/database.php .env.example .gitignore phpunit.xml database app/Models/SalesKpiTarget.php tests/Feature/SalesKpiTargetMigrationTest.php
git commit -m "feat: add sqlite storage for sales kpi targets"
```

---

### Task 2: Replace Config-Based Targets with a SQLite-Backed Service

**Files:**
- Modify: `app/Services/SalesKpi/SalesKpiTargetService.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `app/Console/Commands/ImportLegacySalesKpiTargets.php`
- Modify: `app/Console/Kernel.php`
- Test: `tests/Unit/SalesKpiTargetServiceTest.php`
- Test: `tests/Feature/ImportLegacySalesKpiTargetsTest.php`

**Interfaces:**
- Produces `SalesKpiTargetService::targetForLine(string $line): ?float`.
- Produces `SalesKpiTargetService::all(): array<string,?float>`.
- Produces `SalesKpiTargetService::saveBatch(array<string,?float> $targets): void`.
- Produces command `sales-kpi:import-legacy-targets`.

- [ ] **Step 1: Replace the unit tests with the combined-target contract**

```php
public function test_it_returns_one_combined_target_by_normalized_line(): void
{
    SalesKpiTarget::query()->create([
        'sales_line' => 'GERAI KATAMSO',
        'monthly_target' => 300_000_000,
    ]);

    $service = app(SalesKpiTargetService::class);

    self::assertSame(300_000_000.0, $service->targetForLine('  gerai   katamso '));
    self::assertNull($service->targetForLine('JAKARTA'));
}

public function test_save_batch_converts_zero_to_null_and_is_atomic(): void
{
    $service = app(SalesKpiTargetService::class);

    $service->saveBatch([
        'JAKARTA' => 500_000_000.0,
        'GERAI KATAMSO' => null,
    ]);

    self::assertSame(500_000_000.0, $service->targetForLine('JAKARTA'));
    self::assertNull($service->targetForLine('GERAI KATAMSO'));
}
```

Use `migrate:fresh --database=sqlite_targets` in `setUp()` for this test class.

- [ ] **Step 2: Run tests and verify failure against the old per-database constructor API**

```bash
php artisan test tests/Unit/SalesKpiTargetServiceTest.php
```

Expected: FAIL because the current service expects a nested config array and has no `targetForLine`, `all`, or `saveBatch` methods.

- [ ] **Step 3: Implement the SQLite-backed service**

```php
<?php

declare(strict_types=1);

namespace App\Services\SalesKpi;

use App\Models\SalesKpiTarget;
use Illuminate\Support\Facades\DB;

final class SalesKpiTargetService
{
    public function targetForLine(string $line): ?float
    {
        $value = SalesKpiTarget::query()
            ->where('sales_line', $this->normalizeLine($line))
            ->value('monthly_target');

        return $value === null || (float) $value <= 0.0 ? null : (float) $value;
    }

    /** @return array<string,?float> */
    public function all(): array
    {
        return SalesKpiTarget::query()
            ->orderBy('sales_line')
            ->get(['sales_line', 'monthly_target'])
            ->mapWithKeys(fn (SalesKpiTarget $target): array => [
                $target->sales_line => $target->monthly_target === null
                    ? null
                    : (float) $target->monthly_target,
            ])
            ->all();
    }

    /** @param array<string,?float> $targets */
    public function saveBatch(array $targets): void
    {
        DB::connection('sqlite_targets')->transaction(function () use ($targets): void {
            foreach ($targets as $line => $target) {
                $normalized = $this->normalizeLine($line);
                $value = $target === null || $target <= 0.0 ? null : $target;

                SalesKpiTarget::query()->updateOrCreate(
                    ['sales_line' => $normalized],
                    ['monthly_target' => $value],
                );
            }
        });
    }

    public function normalizeLine(string $line): string
    {
        $line = trim($line);

        return strtoupper(preg_replace('/\s+/', ' ', $line) ?? $line);
    }
}
```

- [ ] **Step 4: Remove the config-array singleton**

In `AppServiceProvider::register()`, delete the closure that passes `config('sales_kpi_targets')`. Keep the class container-resolvable with its zero-argument constructor and keep the calculator binding:

```php
$this->app->singleton(SalesKpiTargetService::class);

$this->app->singleton(
    SalesKpiCalculator::class,
    fn (): SalesKpiCalculator => new SalesKpiCalculator(
        $this->app->make(SalesKpiTargetService::class),
    ),
);
```

- [ ] **Step 5: Write the failing legacy-import feature test**

The test configuration must include duplicate lines across sources:

```php
config()->set('sales_kpi_targets', [
    'AS_INGCO' => ['JAKARTA' => 300_000_000, 'GERAI KATAMSO' => 100_000_000],
    'AS_SMI' => [' jakarta ' => 200_000_000, 'GERAI KATAMSO' => 200_000_000],
]);

$this->artisan('sales-kpi:import-legacy-targets')
    ->expectsOutput('2 target gabungan berhasil diimpor.')
    ->assertExitCode(0);

self::assertDatabaseHas('sales_kpi_targets', [
    'sales_line' => 'JAKARTA',
    'monthly_target' => 500_000_000,
], 'sqlite_targets');
```

- [ ] **Step 6: Run the import test and verify the command is missing**

```bash
php artisan test tests/Feature/ImportLegacySalesKpiTargetsTest.php
```

Expected: FAIL because command `sales-kpi:import-legacy-targets` does not exist.

- [ ] **Step 7: Implement the one-time import command**

Command behavior:

```php
protected $signature = 'sales-kpi:import-legacy-targets {--force : Replace existing rows with imported totals}';
```

Algorithm:

```php
$combined = [];
foreach ((array) config('sales_kpi_targets', []) as $lines) {
    foreach ((array) $lines as $line => $target) {
        $key = $service->normalizeLine((string) $line);
        $numeric = is_numeric($target) ? (float) $target : 0.0;
        if ($numeric > 0.0) {
            $combined[$key] = ($combined[$key] ?? 0.0) + $numeric;
        }
    }
}

if (SalesKpiTarget::query()->exists() && !$this->option('force')) {
    $this->components->warn('Import dilewati karena tabel target sudah berisi data.');
    return self::SUCCESS;
}

$service->saveBatch($combined);
$this->components->info(count($combined).' target gabungan berhasil diimpor.');
```

Register the command in `app/Console/Kernel.php` if command auto-discovery is not active in this project structure.

- [ ] **Step 8: Run service and import tests**

```bash
php artisan test tests/Unit/SalesKpiTargetServiceTest.php tests/Feature/ImportLegacySalesKpiTargetsTest.php
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Services/SalesKpi/SalesKpiTargetService.php app/Providers/AppServiceProvider.php app/Console tests/Unit/SalesKpiTargetServiceTest.php tests/Feature/ImportLegacySalesKpiTargetsTest.php
git commit -m "feat: persist combined kpi targets in sqlite"
```

---

### Task 3: Add Environment-Based Dashboard Authentication

**Files:**
- Create: `config/dashboard_auth.php`
- Create: `app/Http/Requests/DashboardLoginRequest.php`
- Create: `app/Http/Controllers/DashboardAuthController.php`
- Create: `app/Http/Middleware/EnsureDashboardAuthenticated.php`
- Modify: `app/Http/Kernel.php`
- Create: `resources/views/auth/login.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/DashboardAuthenticationTest.php`

**Interfaces:**
- Produces middleware alias `dashboard.auth`.
- Produces routes `dashboard.login`, `dashboard.login.store`, and `dashboard.logout`.
- Authenticated session key is `dashboard_authenticated`; stored username key is `dashboard_username`.

- [ ] **Step 1: Write failing authentication feature tests**

```php
public function test_valid_environment_credentials_create_session_and_redirect_to_kpi(): void
{
    config()->set('dashboard_auth.username', 'admin');
    config()->set('dashboard_auth.password', 'safe-password');

    $response = $this->post('/login', [
        'username' => 'admin',
        'password' => 'safe-password',
    ]);

    $response->assertRedirect('/sales-kpi');
    $response->assertSessionHas('dashboard_authenticated', true);
    $response->assertSessionHas('dashboard_username', 'admin');
}

public function test_invalid_credentials_return_one_generic_error(): void
{
    config()->set('dashboard_auth.username', 'admin');
    config()->set('dashboard_auth.password', 'safe-password');

    $this->from('/login')->post('/login', [
        'username' => 'wrong',
        'password' => 'wrong',
    ])->assertRedirect('/login')
      ->assertSessionHasErrors([
          'login' => 'Username atau password tidak valid.',
      ]);
}

public function test_protected_route_redirects_to_login(): void
{
    $this->get('/sales-kpi')->assertRedirect('/login');
}

public function test_logout_invalidates_dashboard_session(): void
{
    $this->withSession([
        'dashboard_authenticated' => true,
        'dashboard_username' => 'admin',
    ])->post('/logout')->assertRedirect('/login');

    $this->assertFalse(session()->has('dashboard_authenticated'));
}
```

- [ ] **Step 2: Run the test and verify missing routes/middleware behavior**

```bash
php artisan test tests/Feature/DashboardAuthenticationTest.php
```

Expected: FAIL because `/login`, `/logout`, and dashboard middleware do not exist.

- [ ] **Step 3: Add credential config**

```php
<?php

return [
    'username' => env('DASHBOARD_ADMIN_USER', 'admin'),
    'password' => env('DASHBOARD_ADMIN_PASSWORD'),
    'session_key' => 'dashboard_authenticated',
    'username_session_key' => 'dashboard_username',
];
```

- [ ] **Step 4: Implement request and controller**

`DashboardLoginRequest` rules:

```php
return [
    'username' => ['required', 'string', 'max:100'],
    'password' => ['required', 'string', 'max:255'],
];
```

Controller login check must use timing-safe comparisons:

```php
$validUser = hash_equals((string) config('dashboard_auth.username'), $request->string('username')->toString());
$validPassword = hash_equals((string) config('dashboard_auth.password'), $request->string('password')->toString());

if (!$validUser || !$validPassword) {
    return back()->withInput($request->only('username'))->withErrors([
        'login' => 'Username atau password tidak valid.',
    ]);
}

$request->session()->regenerate();
$request->session()->put(config('dashboard_auth.session_key'), true);
$request->session()->put(config('dashboard_auth.username_session_key'), $request->string('username')->toString());

return redirect()->intended(route('sales-kpi.index'));
```

Logout must call:

```php
$request->session()->invalidate();
$request->session()->regenerateToken();
```

- [ ] **Step 5: Implement middleware and alias**

```php
public function handle(Request $request, Closure $next): Response
{
    if ($request->session()->get((string) config('dashboard_auth.session_key')) !== true) {
        return redirect()->guest(route('dashboard.login'));
    }

    return $next($request);
}
```

Add to `app/Http/Kernel.php`:

```php
'dashboard.auth' => \App\Http\Middleware\EnsureDashboardAuthenticated::class,
```

- [ ] **Step 6: Add auth routes and login Blade**

Public routes:

```php
Route::get('/login', [DashboardAuthController::class, 'create'])->name('dashboard.login');
Route::post('/login', [DashboardAuthController::class, 'store'])->name('dashboard.login.store');
Route::post('/logout', [DashboardAuthController::class, 'destroy'])
    ->middleware('dashboard.auth')
    ->name('dashboard.logout');
```

The login view must include username, password, CSRF, validation error, and submit button text **Masuk ke Dashboard**. It must not prefill the password.

- [ ] **Step 7: Protect one representative route, rerun tests, then protect the complete route set in Task 7**

Wrap `/sales-kpi` in `dashboard.auth` now so the focused test becomes green. Leave the full route audit to Task 7.

- [ ] **Step 8: Run authentication tests**

```bash
php artisan test tests/Feature/DashboardAuthenticationTest.php
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add config/dashboard_auth.php app/Http/Requests/DashboardLoginRequest.php app/Http/Controllers/DashboardAuthController.php app/Http/Middleware/EnsureDashboardAuthenticated.php app/Http/Kernel.php resources/views/auth routes/web.php tests/Feature/DashboardAuthenticationTest.php
git commit -m "feat: add session login for dashboard routes"
```

---

### Task 4: Build the Combined Current-Month Target Catalog

**Files:**
- Modify: `app/Services/SalesKpi/SalesKpiDataService.php`
- Create: `app/Data/SalesKpi/SalesKpiTargetCatalogRow.php`
- Create: `app/Services/SalesKpi/SalesKpiTargetCatalogService.php`
- Test: `tests/Unit/SalesKpiTargetCatalogServiceTest.php`

**Interfaces:**
- Produces `SalesKpiDataService::loadRange(string $source, DateTimeImmutable $start, DateTimeImmutable $end): array`.
- Produces `SalesKpiTargetCatalogService::currentMonth(DateTimeImmutable $today): array` returning `list<SalesKpiTargetCatalogRow>`.
- Each row exposes `line`, `lineKey`, `monthlyTarget`, `currentOmzet`, `hasCurrentTransactions`, and `hasTarget`.

- [ ] **Step 1: Write failing catalog tests with two database sources and one inactive saved target**

Use `FakeSalesDataSource` to return:

```php
$fake->setRows('INGCO', [
    $this->salesRow(line: 'JAKARTA', lineTotal: 100_000_000),
    $this->salesRow(line: 'GERAI BOGOR', lineTotal: 50_000_000),
]);
$fake->setRows('SMI', [
    $this->salesRow(line: ' jakarta ', lineTotal: 75_000_000),
]);

SalesKpiTarget::query()->create([
    'sales_line' => 'GERAI KATAMSO',
    'monthly_target' => 300_000_000,
]);
```

Assertions:

```php
self::assertSame(['GERAI BOGOR', 'GERAI KATAMSO', 'JAKARTA'], array_column($rows, 'line'));
self::assertSame(175_000_000.0, $byLine['JAKARTA']->currentOmzet);
self::assertTrue($byLine['JAKARTA']->hasCurrentTransactions);
self::assertFalse($byLine['JAKARTA']->hasTarget);
self::assertFalse($byLine['GERAI KATAMSO']->hasCurrentTransactions);
self::assertTrue($byLine['GERAI KATAMSO']->hasTarget);
```

Add a second test proving that an exception from either source is rethrown as `SalesKpiDataException` and no partial catalog is returned.

- [ ] **Step 2: Run the focused test and verify missing catalog APIs**

```bash
php artisan test tests/Unit/SalesKpiTargetCatalogServiceTest.php
```

Expected: FAIL because `SalesKpiTargetCatalogService`, DTO, and public `loadRange()` do not exist.

- [ ] **Step 3: Refactor normalized range loading without changing existing KPI behavior**

In `SalesKpiDataService`, add:

```php
/** @return list<SalesKpiTransaction> */
public function loadRange(
    string $source,
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate,
): array {
    $source = strtoupper(trim($source));
    if (!in_array($source, ['INGCO', 'SMI'], true)) {
        throw SalesKpiDataException::forSource($source, 'Sumber database KPI tidak valid.');
    }

    try {
        return $this->fetchAndNormalize($source, 'AS_'.$source, $startDate, $endDate);
    } catch (SalesKpiDataException $exception) {
        throw $exception;
    } catch (Throwable $exception) {
        throw SalesKpiDataException::forSource(
            $source,
            'Function dbo.fn_PenjualanLini tidak dapat dijalankan untuk periode yang dipilih.',
            $exception,
        );
    }
}
```

Change `loadSource()` to call `loadRange()` for current, growth-current, and previous periods.

- [ ] **Step 4: Add the catalog DTO**

```php
final class SalesKpiTargetCatalogRow
{
    public function __construct(
        public readonly string $line,
        public readonly string $lineKey,
        public readonly ?float $monthlyTarget,
        public readonly float $currentOmzet,
        public readonly bool $hasCurrentTransactions,
        public readonly bool $hasTarget,
    ) {
    }
}
```

- [ ] **Step 5: Implement the catalog service**

Core algorithm:

```php
$start = $today->modify('first day of this month')->setTime(0, 0);
$end = $today->setTime(0, 0);
$transactions = [];

foreach (['INGCO', 'SMI'] as $source) {
    $transactions = array_merge(
        $transactions,
        $this->dataService->loadRange($source, $start, $end),
    );
}

$catalog = [];
foreach ($transactions as $transaction) {
    $key = $this->targetService->normalizeLine($transaction->lineKey);
    $catalog[$key]['line'] ??= $key;
    $catalog[$key]['omzet'] = ($catalog[$key]['omzet'] ?? 0.0) + $transaction->lineTotal;
}

foreach ($this->targetService->all() as $line => $target) {
    $catalog[$line]['line'] ??= $line;
    $catalog[$line]['omzet'] ??= 0.0;
    $catalog[$line]['target'] = $target;
}
```

Build DTO rows, sort case-insensitively by `line`, and return them.

- [ ] **Step 6: Run catalog and existing data-service tests**

```bash
php artisan test tests/Unit/SalesKpiTargetCatalogServiceTest.php tests/Unit/SalesKpiDataServiceTest.php tests/Unit/SalesKpiCombinedDataServiceTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/SalesKpi/SalesKpiDataService.php app/Data/SalesKpi/SalesKpiTargetCatalogRow.php app/Services/SalesKpi/SalesKpiTargetCatalogService.php tests/Unit/SalesKpiTargetCatalogServiceTest.php tests/Unit/SalesKpiDataServiceTest.php
git commit -m "feat: build combined current-month target catalog"
```

---

### Task 5: Add the Inline Target Settings Page and Atomic Batch Save

**Files:**
- Create: `app/Http/Requests/SalesKpiTargetUpdateRequest.php`
- Create: `app/Http/Controllers/SalesKpiTargetController.php`
- Create: `resources/views/sales-kpi/targets/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SalesKpiTargetSettingsTest.php`

**Interfaces:**
- Produces GET route `sales-kpi.targets.index` at `/sales-kpi/targets`.
- Produces PUT route `sales-kpi.targets.update` at `/sales-kpi/targets`.
- Submitted shape: `targets[<NORMALIZED_LINE>] = formatted currency string|null`.

- [ ] **Step 1: Write failing page and save tests**

Required cases:

```php
public function test_page_shows_current_and_inactive_lines(): void
{
    $this->actingAsDashboardAdmin()
        ->get('/sales-kpi/targets')
        ->assertOk()
        ->assertSee('JAKARTA')
        ->assertSee('GERAI KATAMSO')
        ->assertSee('Tidak ada transaksi bulan ini')
        ->assertSee('Simpan Semua Perubahan');
}

public function test_blank_and_zero_targets_are_saved_as_null(): void
{
    $this->actingAsDashboardAdmin()
        ->put('/sales-kpi/targets', [
            'targets' => [
                'JAKARTA' => '',
                'GERAI KATAMSO' => '0',
            ],
        ])->assertRedirect('/sales-kpi/targets');

    self::assertNull(SalesKpiTarget::query()->where('sales_line', 'JAKARTA')->value('monthly_target'));
    self::assertNull(SalesKpiTarget::query()->where('sales_line', 'GERAI KATAMSO')->value('monthly_target'));
}

public function test_unknown_line_rejects_entire_batch(): void
{
    SalesKpiTarget::query()->create(['sales_line' => 'JAKARTA', 'monthly_target' => 100]);

    $this->actingAsDashboardAdmin()
        ->from('/sales-kpi/targets')
        ->put('/sales-kpi/targets', [
            'targets' => [
                'JAKARTA' => '500.000.000',
                'BARIS PALSU' => '900.000.000',
            ],
        ])->assertRedirect('/sales-kpi/targets')
          ->assertSessionHasErrors('targets');

    self::assertSame(100.0, (float) SalesKpiTarget::query()->where('sales_line', 'JAKARTA')->value('monthly_target'));
}
```

- [ ] **Step 2: Run the tests and verify routes/controllers are missing**

```bash
php artisan test tests/Feature/SalesKpiTargetSettingsTest.php
```

Expected: FAIL with route/controller/view not found.

- [ ] **Step 3: Implement server-side currency normalization in the request**

Normalization rules:

```php
private function normalizeMoney(mixed $value): ?float
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }

    $digits = preg_replace('/[^0-9-]/', '', (string) $value) ?? '';
    if ($digits === '' || $digits === '0') {
        return null;
    }

    return (float) $digits;
}
```

Validation rules after normalization:

```php
return [
    'targets' => ['required', 'array', 'min:1'],
    'targets.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
];
```

Reject duplicate normalized keys by comparing the count of submitted keys with the count of normalized unique keys.

- [ ] **Step 4: Implement the controller index and update actions**

Index:

```php
$rows = $catalogService->currentMonth(new DateTimeImmutable('today'));
return view('sales-kpi.targets.index', compact('rows'));
```

Update must recompute the server-side catalog, derive `$allowed = array_column($rows, 'lineKey')`, and reject any submitted normalized key not in `$allowed`.

Persist only after all keys and values validate:

```php
$targetService->saveBatch($normalizedTargets);

return redirect()
    ->route('sales-kpi.targets.index')
    ->with('status', 'Semua perubahan target berhasil disimpan.');
```

Catch `SalesKpiDataException`, call `report($exception)`, and return the combined connection error without saving.

- [ ] **Step 5: Build the inline-edit Blade page**

The form must contain:

```blade
<form method="POST" action="{{ route('sales-kpi.targets.update') }}">
    @csrf
    @method('PUT')
    <table class="table table-bordered align-middle">
        <thead>
            <tr>
                <th>LiniPenjualan</th>
                <th>Target Gabungan</th>
                <th>Omzet Bulan Ini</th>
                <th>Status Transaksi</th>
                <th>Status Target</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row->line }}</td>
                <td>
                    <input
                        name="targets[{{ $row->lineKey }}]"
                        value="{{ old('targets.'.$row->lineKey, $row->monthlyTarget === null ? '' : number_format($row->monthlyTarget, 0, ',', '.')) }}"
                        inputmode="numeric"
                        class="form-control target-money-input"
                    >
                </td>
                <td>Rp {{ number_format($row->currentOmzet, 0, ',', '.') }}</td>
                <td>{{ $row->hasCurrentTransactions ? 'Aktif bulan ini' : 'Tidak ada transaksi bulan ini' }}</td>
                <td>{{ $row->hasTarget ? 'Sudah diatur' : 'Target belum diatur' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <button class="btn btn-primary" type="submit">Simpan Semua Perubahan</button>
</form>
```

Add a small client-side formatter for display convenience only; server normalization remains authoritative.

- [ ] **Step 6: Register protected routes**

```php
Route::middleware('dashboard.auth')->group(function (): void {
    Route::get('/sales-kpi/targets', [SalesKpiTargetController::class, 'index'])
        ->name('sales-kpi.targets.index');
    Route::put('/sales-kpi/targets', [SalesKpiTargetController::class, 'update'])
        ->name('sales-kpi.targets.update');
});
```

- [ ] **Step 7: Run page, save, rollback, CSRF, and unauthenticated tests**

```bash
php artisan test tests/Feature/SalesKpiTargetSettingsTest.php tests/Feature/DashboardAuthenticationTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/SalesKpiTargetUpdateRequest.php app/Http/Controllers/SalesKpiTargetController.php resources/views/sales-kpi/targets routes/web.php tests/Feature/SalesKpiTargetSettingsTest.php
git commit -m "feat: add editable combined kpi target settings"
```

---

### Task 6: Make KPI Filters and Calculations Permanently Combined

**Files:**
- Modify: `app/Data/SalesKpi/SalesKpiFilter.php`
- Modify: `app/Http/Requests/SalesKpiRequest.php`
- Modify: `app/Services/SalesKpi/SalesKpiCalculator.php`
- Modify: `app/Data/SalesKpi/SalesLineKpi.php`
- Modify: `app/Http/Controllers/SalesKpiController.php`
- Modify: `resources/views/sales-kpi/index.blade.php`
- Modify: `resources/views/sales-kpi/pdf.blade.php`
- Modify: `app/Exports/SalesKpi/SalesKpiSheet.php`
- Test: `tests/Unit/SalesKpiFilterCombinedTest.php`
- Test: `tests/Unit/SalesKpiCalculatorTest.php`
- Test: `tests/Feature/SalesKpiPageTest.php`

**Interfaces:**
- `SalesKpiFilter` always returns sources `['INGCO', 'SMI']` and database key `GABUNGAN`.
- `SalesKpiCalculator` calls `targetForLine($aggregate->lineKey)` once per line.
- `SalesLineKpi` exposes `targetConfigured: bool`; it no longer exposes per-source missing target names.

- [ ] **Step 1: Write failing combined-only filter and page tests**

```php
public function test_filter_is_always_combined_and_query_has_no_database_key(): void
{
    $filter = SalesKpiFilter::from(
        endDate: '2026-07-28',
        line: 'JAKARTA',
        discontinue: 'all',
        clearance: 'all',
        grade: 'ALL',
        search: '',
    );

    self::assertSame(['INGCO', 'SMI'], $filter->sources());
    self::assertSame('GABUNGAN', $filter->databaseKey());
    self::assertArrayNotHasKey('database', $filter->query());
}
```

Feature assertion:

```php
$this->actingAsDashboardAdmin()
    ->get('/sales-kpi?end_date=2026-07-28&database=ingco')
    ->assertOk()
    ->assertDontSee('name="database"', false)
    ->assertSee('AS_INGCO + AS_SMI');
```

Calculator assertion must create one SQLite target `JAKARTA = 500_000_000`, combine both source omzet, and assert the target score uses the combined actual against that single target.

- [ ] **Step 2: Run focused tests and verify failure against the three-mode filter and source targets**

```bash
php artisan test tests/Unit/SalesKpiFilterCombinedTest.php tests/Unit/SalesKpiCalculatorTest.php tests/Feature/SalesKpiPageTest.php
```

Expected: FAIL because the filter still accepts `database`, and the calculator still expects source targets.

- [ ] **Step 3: Simplify the filter constructor and factory**

New factory signature:

```php
public static function from(
    string $endDate,
    ?string $line = null,
    string $discontinue = 'all',
    string $clearance = 'all',
    string $grade = 'all',
    string $search = '',
): self
```

Keep the `database` readonly property only if needed for backward DTO compatibility, but set it internally to `'combined'`. Prefer removing the public property and making these methods constant:

```php
public function isCombined(): bool { return true; }
public function sources(): array { return ['INGCO', 'SMI']; }
public function databaseKeys(): array { return ['AS_INGCO', 'AS_SMI']; }
public function databaseKey(): string { return 'GABUNGAN'; }
public function databaseLabel(): string { return 'AS_INGCO + AS_SMI'; }
```

Remove `database` from `query()`.

- [ ] **Step 4: Remove database validation/input from `SalesKpiRequest`**

Rules no longer contain `database`. `prepareForValidation()` does not merge it. `filter()` calls the new factory without a database argument. A tampered `?database=ingco` query parameter is ignored.

- [ ] **Step 5: Refactor calculator target lookup**

Replace:

```php
$sourceTargets = $this->targetService->forLine($filter, $aggregate->lineKey);
$target = $this->targetService->combined($sourceTargets);
$missingSources = $this->targetService->missingSources($sourceTargets);
```

with:

```php
$target = $this->targetService->targetForLine($aggregate->lineKey);
$targetConfigured = $target !== null;
```

Keep source contributions for actual values, but set their target to `null` because targets are not source-specific:

```php
$sources[$databaseKey] = new SalesKpiSourceContribution(
    database: $databaseKey,
    target: null,
    targetConfigured: false,
    currentOmzet: $raw->currentOmzet,
    growthCurrentOmzet: $raw->growthCurrentOmzet,
    previousOmzet: $raw->previousOmzet,
    discontinuedOmzet: $raw->discontinuedOmzet,
    clearanceOmzet: $raw->clearanceOmzet,
    gradeAOmzet: $raw->gradeAOmzet,
    gradeBOmzet: $raw->gradeBOmzet,
);
```

Pass `targetConfigured` into `SalesLineKpi`. Remove `missingTargetSources`, `hasPartialTarget()`, and source-target UI dependencies.

- [ ] **Step 6: Update web, PDF, and Excel presentation**

Remove:

- Database selector.
- `Target AS_INGCO` and `Target AS_SMI` columns.
- Message `Target belum diatur: AS_INGCO, AS_SMI`.

Use one message:

```blade
@if (!$row->targetConfigured)
    <span class="target-missing">Target belum diatur untuk LiniPenjualan: {{ $row->line }}</span>
@endif
```

The summary table must retain source actual columns if they are useful for traceability:

```text
LiniPenjualan | Target Gabungan | Omzet AS_INGCO | Omzet AS_SMI | Omzet Gabungan | ...
```

Export filenames become:

```php
Sales_KPI_GABUNGAN_{$date}.xlsx
Sales_KPI_GABUNGAN_{$date}.pdf
```

- [ ] **Step 7: Run focused KPI tests and export tests**

```bash
php artisan test tests/Unit/SalesKpiFilterCombinedTest.php tests/Unit/SalesKpiCalculatorTest.php tests/Unit/SalesKpiServiceTest.php tests/Feature/SalesKpiPageTest.php tests/Feature/SalesKpiExportTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Data/SalesKpi app/Http/Requests/SalesKpiRequest.php app/Services/SalesKpi/SalesKpiCalculator.php app/Http/Controllers/SalesKpiController.php resources/views/sales-kpi app/Exports/SalesKpi tests/Unit/SalesKpiFilterCombinedTest.php tests/Unit/SalesKpiCalculatorTest.php tests/Feature/SalesKpiPageTest.php tests/Feature/SalesKpiExportTest.php
git commit -m "feat: make sales kpi permanently combined"
```

---

### Task 7: Protect the Complete Report Surface and Add Navigation

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/sales-dashboard/index.blade.php`
- Modify: `resources/views/sales-kpi/index.blade.php`
- Test: `tests/Feature/ProtectedReportRoutesTest.php`

**Interfaces:**
- Every report, export, PDF, detail endpoint, and target route is inside `dashboard.auth`.
- Authenticated layout displays `Dashboard Penjualan`, `Dashboard KPI`, `Pengaturan Target KPI`, username, and logout.

- [ ] **Step 1: Write a route-matrix test**

```php
#[DataProvider('protectedRoutes')]
public function test_every_report_route_requires_dashboard_login(string $method, string $uri): void
{
    $response = $this->call($method, $uri);
    $response->assertRedirect('/login');
}

public static function protectedRoutes(): array
{
    return [
        ['GET', '/sales-dashboard'],
        ['GET', '/sales-dashboard/export/excel'],
        ['GET', '/sales-dashboard/export/pdf'],
        ['GET', '/sales-kpi'],
        ['GET', '/sales-kpi/details'],
        ['GET', '/sales-kpi/export/excel'],
        ['GET', '/sales-kpi/export/pdf'],
        ['GET', '/sales-kpi/targets'],
        ['PUT', '/sales-kpi/targets'],
    ];
}
```

Add an authenticated test asserting navigation and logout are visible.

- [ ] **Step 2: Run the route test and identify any unprotected endpoints**

```bash
php artisan test tests/Feature/ProtectedReportRoutesTest.php
```

Expected: FAIL for routes still outside the middleware group.

- [ ] **Step 3: Restructure `routes/web.php` into public and protected groups**

```php
Route::get('/login', ...)->name('dashboard.login');
Route::post('/login', ...)->name('dashboard.login.store');

Route::middleware('dashboard.auth')->group(function (): void {
    Route::redirect('/', '/sales-kpi');
    Route::redirect('/sales-by-item', '/sales-dashboard');

    // Sales dashboard routes
    // Sales KPI routes
    // Target settings routes

    Route::post('/logout', [DashboardAuthController::class, 'destroy'])
        ->name('dashboard.logout');
});
```

Do not leave an alternate unprotected alias to any report action.

- [ ] **Step 4: Add authenticated navigation and logout form**

```blade
<nav>
    <a href="{{ route('sales-dashboard.index') }}">Dashboard Penjualan</a>
    <a href="{{ route('sales-kpi.index') }}">Dashboard KPI</a>
    <a href="{{ route('sales-kpi.targets.index') }}">Pengaturan Target KPI</a>

    <span>{{ session(config('dashboard_auth.username_session_key')) }}</span>
    <form method="POST" action="{{ route('dashboard.logout') }}">
        @csrf
        <button type="submit">Keluar</button>
    </form>
</nav>
```

Keep export links using the active filter query; no database parameter should be appended to KPI URLs.

- [ ] **Step 5: Run authentication, route, and existing page tests**

```bash
php artisan test tests/Feature/DashboardAuthenticationTest.php tests/Feature/ProtectedReportRoutesTest.php tests/Feature/SalesDashboardPageTest.php tests/Feature/SalesKpiPageTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add routes/web.php resources/views/layouts/app.blade.php resources/views/sales-dashboard/index.blade.php resources/views/sales-kpi/index.blade.php tests/Feature/ProtectedReportRoutesTest.php
git commit -m "feat: protect reports and add target navigation"
```

---

### Task 8: Align Errors, Details, and Exports with SQLite Targets

**Files:**
- Modify: `app/Http/Controllers/SalesKpiController.php`
- Modify: `app/Http/Controllers/SalesKpiTargetController.php`
- Modify: `app/Exports/SalesKpi/SalesKpiExport.php`
- Modify: `app/Exports/SalesKpi/SalesKpiSheet.php`
- Modify: `resources/views/sales-kpi/pdf.blade.php`
- Modify: `resources/views/sales-kpi/partials/detail-table.blade.php`
- Test: `tests/Feature/SalesKpiErrorHandlingTest.php`
- Test: `tests/Feature/SalesKpiExportTest.php`
- Test: `tests/Feature/SalesKpiDetailEndpointTest.php`

**Interfaces:**
- Combined connection failures identify failed database names and return HTTP 503 for JSON details.
- SQLite failures show the exact user-safe message from the design.
- Web, Excel, PDF, and detail endpoints all use the same `SalesKpiReport` generated with SQLite targets.

- [ ] **Step 1: Add failing error and export consistency tests**

Connection test:

```php
$this->fakeSource->fail('SMI');

$this->actingAsDashboardAdmin()
    ->get('/sales-kpi?end_date=2026-07-28')
    ->assertOk()
    ->assertSee('Koneksi ke database AS_SMI gagal.')
    ->assertSee('tidak ditampilkan agar hasil gabungan tidak menyesatkan');
```

Detail endpoint test:

```php
$this->actingAsDashboardAdmin()
    ->getJson('/sales-kpi/details?'.http_build_query($validDetailQuery))
    ->assertStatus(503)
    ->assertJsonPath('failed_databases.0', 'AS_SMI');
```

SQLite unavailable test must temporarily point `database.connections.sqlite_targets.database` to a non-creatable path and assert:

```text
Pengaturan target belum dapat dibuka.
Database konfigurasi lokal tidak tersedia.
```

Export consistency test must save `JAKARTA = 500000000`, generate the web report and exported sheet rows, and assert both expose exactly `500000000` for the combined target.

- [ ] **Step 2: Run focused tests and verify mismatched old messages/columns**

```bash
php artisan test tests/Feature/SalesKpiErrorHandlingTest.php tests/Feature/SalesKpiExportTest.php tests/Feature/SalesKpiDetailEndpointTest.php
```

Expected: FAIL until all old source-target messages and export columns are removed.

- [ ] **Step 3: Centralize safe error text**

Add a small private formatter or dedicated service with exact messages:

```php
private function combinedConnectionMessage(SalesKpiDataException $exception): string
{
    $labels = array_values(array_unique(array_map(
        static fn (string $source): string => 'AS_'.strtoupper(trim($source)),
        $exception->failedSources(),
    )));

    return "Laporan KPI gabungan tidak dapat dimuat.\n\n"
        .'Koneksi ke database '.implode(' dan ', $labels)." gagal.\n"
        .'Data database lainnya tidak ditampilkan agar hasil gabungan tidak menyesatkan.';
}
```

Do not include driver messages, server host, username, password, or stack traces in responses.

- [ ] **Step 4: Catch SQLite exceptions only at the settings boundary**

In `SalesKpiTargetController`, catch `Throwable`, call `report($exception)`, and return:

```text
Pengaturan target belum dapat dibuka.
Database konfigurasi lokal tidak tersedia.
```

Do not swallow `SalesKpiDataException`; preserve the database-specific combined error for SQL Server failures.

- [ ] **Step 5: Update export sheets and PDF columns**

Every KPI recap export uses:

```text
LiniPenjualan
Target Gabungan
Omzet AS_INGCO
Omzet AS_SMI
Omzet Gabungan
Skor Target
Skor Growth
Skor Discontinued/Clearance
Skor Grade A/B
Skor Akhir
Status
```

No sheet contains `Target AS_INGCO`, `Target AS_SMI`, or missing-source target labels.

Transaction detail export retains `Database`.

- [ ] **Step 6: Run focused and regression tests**

```bash
php artisan test tests/Feature/SalesKpiErrorHandlingTest.php tests/Feature/SalesKpiExportTest.php tests/Feature/SalesKpiDetailEndpointTest.php tests/Unit/SalesKpiCalculatorTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/SalesKpiController.php app/Http/Controllers/SalesKpiTargetController.php app/Exports/SalesKpi resources/views/sales-kpi tests/Feature/SalesKpiErrorHandlingTest.php tests/Feature/SalesKpiExportTest.php tests/Feature/SalesKpiDetailEndpointTest.php
git commit -m "fix: align combined kpi errors and exports"
```

---

### Task 9: Update Windows Installation, Migration Workflow, and Documentation

**Files:**
- Modify: `INSTALL_WINDOWS.bat`
- Modify: `UPDATE_AFTER_OVERWRITE.bat`
- Modify: `CHECK_REQUIREMENTS.bat`
- Modify: `README.md`
- Modify: `docs/INSTALL-WINDOWS-XAMPP.md`
- Modify: `docs/SALES-KPI-CALCULATION.md`
- Create: `tests/manual_sales_kpi_targets.php`
- Test: `tests/Feature/InstallationConfigurationTest.php`

**Interfaces:**
- Install/update scripts create the SQLite file, run the target migration, import legacy targets only when appropriate, clear caches, and run tests.
- Documentation gives exact `.env` keys and URLs.

- [ ] **Step 1: Write a failing static installation test**

```php
public function test_install_scripts_prepare_sqlite_and_authentication(): void
{
    $install = file_get_contents(base_path('INSTALL_WINDOWS.bat'));
    $update = file_get_contents(base_path('UPDATE_AFTER_OVERWRITE.bat'));
    $env = file_get_contents(base_path('.env.example'));

    self::assertStringContainsString('database\\database.sqlite', $install);
    self::assertStringContainsString('migrate --database=sqlite_targets --force', $install);
    self::assertStringContainsString('sales-kpi:import-legacy-targets', $update);
    self::assertStringContainsString('DASHBOARD_ADMIN_USER=', $env);
    self::assertStringContainsString('DASHBOARD_ADMIN_PASSWORD=', $env);
}
```

- [ ] **Step 2: Run the static test and verify scripts are missing required setup**

```bash
php artisan test tests/Feature/InstallationConfigurationTest.php
```

Expected: FAIL.

- [ ] **Step 3: Update Windows scripts**

Use an idempotent PHP command to create the file:

```bat
if not exist database mkdir database
if not exist database\database.sqlite php -r "touch('database/database.sqlite');"
php artisan migrate --database=sqlite_targets --force
php artisan sales-kpi:import-legacy-targets
php artisan optimize:clear
```

`UPDATE_AFTER_OVERWRITE.bat` must not pass `--force` to the import command, so existing target data is preserved.

Add requirement checks:

```bat
php -m | findstr /I "pdo_sqlite" >nul || echo [ERROR] Extension pdo_sqlite belum aktif.
php -m | findstr /I "sqlite3" >nul || echo [ERROR] Extension sqlite3 belum aktif.
```

- [ ] **Step 4: Add manual verification script**

`tests/manual_sales_kpi_targets.php` must verify:

1. `sqlite_targets` connection opens.
2. `sales_kpi_targets` table exists.
3. A temporary target can be inserted and rolled back inside a transaction.
4. Required authentication config keys are non-empty.
5. Route names `dashboard.login`, `sales-kpi.index`, and `sales-kpi.targets.index` exist.

The script must print `[PASS]`/`[FAIL]` lines and exit non-zero on failure.

- [ ] **Step 5: Update documentation with exact operating steps**

Document:

```text
Login: /login
KPI gabungan: /sales-kpi
Pengaturan target: /sales-kpi/targets
```

Document `.env`:

```env
TARGET_DB_DATABASE=database/database.sqlite
DASHBOARD_ADMIN_USER=admin
DASHBOARD_ADMIN_PASSWORD=use-a-strong-password
```

State explicitly that target values apply to all report periods and that editing a target changes historical report calculations when reopened.

- [ ] **Step 6: Run installation and manual/static tests**

```bash
php artisan test tests/Feature/InstallationConfigurationTest.php
php tests/manual_sales_kpi_targets.php
php tests/manual_sales_kpi.php
php tests/manual_sales_kpi_export.php
```

Expected: all commands exit 0.

- [ ] **Step 7: Commit**

```bash
git add INSTALL_WINDOWS.bat UPDATE_AFTER_OVERWRITE.bat CHECK_REQUIREMENTS.bat README.md docs tests/manual_sales_kpi_targets.php tests/Feature/InstallationConfigurationTest.php .env.example
git commit -m "docs: add sqlite target and login installation workflow"
```

---

### Task 10: Full Regression Verification and Deliverable Packaging

**Files:**
- No planned production source changes; any failure returns to the task that owns the affected behavior.
- Create: `Sales_KPI_Target_Settings_Verification_Report.txt`
- Create: `Sales_Report_Dashboard_Laravel12_KPI_Target_Settings.zip`

**Interfaces:**
- Produces a complete overwrite-ready ZIP without `.env`, credentials, logs, cache files, vendor, or runtime SQLite data.
- Produces a verification report containing commands, exit codes, and test totals.

- [ ] **Step 1: Run the complete automated suite**

```bash
php artisan optimize:clear
php artisan migrate:fresh --database=sqlite_targets --force
php artisan test
```

Expected: exit code 0 and zero failures.

- [ ] **Step 2: Run formatting and syntax checks**

```bash
vendor/bin/pint --test
find app config database routes tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

On Windows PowerShell, equivalent syntax check:

```powershell
Get-ChildItem app,config,database,routes,tests -Recurse -Filter *.php |
    ForEach-Object { php -l $_.FullName }
```

Expected: no syntax or formatting failures.

- [ ] **Step 3: Run the manual application checks**

```bash
php tests/manual_dashboard.php
php tests/manual_web_assets.php
php tests/manual_sales_kpi.php
php tests/manual_sales_kpi_assets.php
php tests/manual_sales_kpi_export.php
php tests/manual_sales_kpi_errors.php
php tests/manual_sales_kpi_targets.php
```

Expected: every script exits 0.

- [ ] **Step 4: Verify the required behavior matrix**

Record evidence for each item:

```text
[PASS] Unauthenticated /sales-kpi redirects to /login
[PASS] Valid admin credentials open /sales-kpi
[PASS] Invalid credentials show one generic error
[PASS] KPI page has no database selector
[PASS] AS_INGCO and AS_SMI JAKARTA data merge into one row
[PASS] One SQLite target is used for the merged row
[PASS] Missing target message names the LiniPenjualan
[PASS] /sales-kpi/targets shows active and inactive saved lines
[PASS] Blank and zero targets persist as NULL
[PASS] Unknown line rejects and rolls back the full batch
[PASS] SQL Server source failure suppresses partial combined output
[PASS] Excel/PDF use the same combined target as the web report
[PASS] Logout invalidates the session
```

- [ ] **Step 5: Build a clean ZIP**

Exclude:

```text
.env
vendor/
node_modules/
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
database/database.sqlite
database/database.sqlite-shm
database/database.sqlite-wal
.git/
.worktrees/
.superpowers/
```

Include `database/.gitkeep`, migrations, install/update scripts, and `.env.example`.

- [ ] **Step 6: Validate the ZIP**

```bash
unzip -t Sales_Report_Dashboard_Laravel12_KPI_Target_Settings.zip
```

Extract to a temporary directory and verify:

```bash
test ! -f extracted/.env
test ! -f extracted/database/database.sqlite
test -f extracted/database/migrations/2026_07_28_000000_create_sales_kpi_targets_table.php
test -f extracted/resources/views/auth/login.blade.php
test -f extracted/resources/views/sales-kpi/targets/index.blade.php
```

- [ ] **Step 7: Write the verification report**

The report must include:

- Project version and date.
- Git commit range.
- Full test command and pass/fail totals.
- PHP syntax-check file count.
- Manual-check results.
- ZIP integrity result.
- Explicit note that real SQL Server connectivity must still be verified on the user's Windows/XAMPP environment.

- [ ] **Step 8: Commit final verification metadata, but not the distributable ZIP if repository policy excludes build artifacts**

```bash
git add Sales_KPI_Target_Settings_Verification_Report.txt
git commit -m "test: verify combined target settings release"
```

---

## Plan Self-Review

### Spec coverage

- Authentication: Tasks 3 and 7.
- Dedicated SQLite target table: Tasks 1 and 2.
- Combined current-month catalog plus inactive targets: Task 4.
- Inline editing and atomic save: Task 5.
- Combined-only KPI and no database selector: Task 6.
- Safe SQL Server and SQLite errors: Task 8.
- Web/Excel/PDF consistency: Tasks 6 and 8.
- Legacy config import: Task 2.
- Windows installation and documentation: Task 9.
- Full test and package verification: Task 10.

### Placeholder scan

The plan contains no unresolved markers, deferred implementation notes, or unspecified test instructions. Exact routes, environment keys, table columns, method signatures, validation behavior, error text, and verification commands are defined.

### Type and naming consistency

- Connection: `sqlite_targets` everywhere.
- Model: `SalesKpiTarget`.
- Target lookup: `targetForLine(string): ?float`.
- Batch persistence: `saveBatch(array<string,?float>): void`.
- Catalog: `SalesKpiTargetCatalogService::currentMonth(DateTimeImmutable): array`.
- Session keys: `dashboard_authenticated`, `dashboard_username`.
- Middleware alias: `dashboard.auth`.
- Settings routes: `sales-kpi.targets.index`, `sales-kpi.targets.update`.
- Combined label: `AS_INGCO + AS_SMI`; file key: `GABUNGAN`.
