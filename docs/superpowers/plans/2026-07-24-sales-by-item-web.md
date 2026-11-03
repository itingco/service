# Sales By Item Web Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Build a standalone Laravel 10 + Blade reporting application that reads `dbo.fn_PenjualanLini(date1, date2)` from AS_INGCO and AS_SMI, aggregates Sales By Item, and exports filtered results to Excel and PDF.

**Architecture:** A thin HTTP controller validates the selected database and end date, derives the first day of the month, and delegates all querying and aggregation to a focused service. Two SQL Server connections are configured through `.env`; exports reuse the same normalized report DTO so the HTML, Excel, and PDF outputs stay consistent.

**Tech Stack:** Laravel 10, PHP 8.1+, Blade, Bootstrap 5, SQL Server (`sqlsrv`/`pdo_sqlsrv`), Maatwebsite Laravel Excel 3.1, Barryvdh Laravel DomPDF, PHPUnit.

## Global Constraints

- New standalone Laravel 10 project.
- PHP 8.1 compatible.
- Blade frontend; no React/Vue build step required.
- Read-only reporting; no login and no database writes.
- One page with database filter: ALL, INGCO, SMI.
- User selects only end date; start date is the first day of the same month.
- Main output is Sales By Item only.
- ALL combines equal item codes from AS_INGCO and AS_SMI into one row.
- Export filtered results to XLSX and PDF.
- Database credentials live in `.env`, not controllers or views.
- SQL call: `SELECT * FROM dbo.fn_PenjualanLini(?, ?)`.

---

### Task 1: Scaffold Laravel 10-compatible application

**Files:**
- Create: `composer.json`
- Create: `artisan`
- Create: `bootstrap/app.php`
- Create: `app/Http/Kernel.php`
- Create: `app/Exceptions/Handler.php`
- Create: `app/Providers/AppServiceProvider.php`
- Create: `config/app.php`
- Create: `config/database.php`
- Create: `config/filesystems.php`
- Create: `config/logging.php`
- Create: `config/view.php`
- Create: `.env.example`
- Create: `phpunit.xml`

**Interfaces:**
- Produces: a Laravel 10-compatible project shell with `sqlsrv_ingco` and `sqlsrv_smi` connections.

- [x] Create the project structure and Laravel bootstrap files.
- [x] Configure composer dependencies for Laravel 10, Laravel Excel, and DomPDF.
- [x] Add environment variables for both SQL Server databases.
- [x] Verify PHP syntax with `find app config routes -name '*.php' -print0 | xargs -0 -n1 php -l`.

### Task 2: Define report request validation and immutable report data objects

**Files:**
- Create: `app/Http/Requests/SalesByItemRequest.php`
- Create: `app/Data/SalesByItemFilter.php`
- Create: `app/Data/SalesByItemRow.php`
- Create: `app/Data/SalesByItemReport.php`
- Test: `tests/Unit/SalesByItemFilterTest.php`

**Interfaces:**
- Produces: `SalesByItemFilter::from(string $database, string $endDate)` and report DTOs consumed by service, controller, and exports.

- [x] Write tests for end-date parsing and automatic month-start calculation.
- [x] Implement validation for `database` in `all|ingco|smi` and required ISO date.
- [x] Implement immutable DTOs with typed constructor properties.
- [x] Run unit tests.

### Task 3: Normalize unknown function column names and aggregate items

**Files:**
- Create: `app/Support/SalesRowNormalizer.php`
- Create: `app/Services/SalesByItemAggregator.php`
- Test: `tests/Unit/SalesRowNormalizerTest.php`
- Test: `tests/Unit/SalesByItemAggregatorTest.php`

**Interfaces:**
- Consumes: raw `stdClass|array` rows from SQL Server.
- Produces: normalized transaction arrays and `SalesByItemRow[]`, grouped by uppercase trimmed item code.

- [x] Test candidate aliases for item code, item name, quantity, and sales amount.
- [x] Test combining matching item codes across INGCO and SMI.
- [x] Test source label values `INGCO`, `SMI`, and `INGCO & SMI`.
- [x] Implement defensive numeric parsing, including Indonesian and invariant separators.
- [x] Implement totals, average price, and contribution percentage.

### Task 4: Query two SQL Server connections safely

**Files:**
- Create: `app/Contracts/SalesDataSource.php`
- Create: `app/Services/SqlServerSalesDataSource.php`
- Create: `app/Services/SalesByItemService.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/SalesByItemServiceTest.php`

**Interfaces:**
- Produces: `SalesByItemService::generate(SalesByItemFilter): SalesByItemReport`.
- SQL execution: `DB::connection($connection)->select('SELECT * FROM dbo.fn_PenjualanLini(?, ?)', [$start, $end])`.

- [x] Bind the data source interface to SQL Server implementation.
- [x] Map `ingco` to `sqlsrv_ingco` and `smi` to `sqlsrv_smi`.
- [x] Fail the ALL request if either database fails and include the failing source name.
- [x] Unit-test orchestration with a fake data source.

### Task 5: Build the Blade report page

**Files:**
- Create: `app/Http/Controllers/SalesByItemController.php`
- Create: `resources/views/layouts/app.blade.php`
- Create: `resources/views/sales-by-item/index.blade.php`
- Create: `routes/web.php`
- Test: `tests/Feature/SalesByItemPageTest.php`

**Interfaces:**
- Routes: `GET /sales-by-item` and `GET /` redirect.
- Query parameters: `database`, `end_date`.

- [x] Build the filter card with ALL/INGCO/SMI and one end-date field.
- [x] Display derived start date, KPI cards, and Sales By Item table.
- [x] Add clear empty state and database error alert.
- [x] Add export buttons preserving active filters.
- [x] Test validation, default filters, and successful rendering with a mocked service.

### Task 6: Add Excel and PDF exports

**Files:**
- Create: `app/Exports/SalesByItemExport.php`
- Create: `resources/views/sales-by-item/export-excel.blade.php`
- Create: `resources/views/sales-by-item/pdf.blade.php`
- Modify: `app/Http/Controllers/SalesByItemController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SalesByItemExportTest.php`

**Interfaces:**
- Routes: `GET /sales-by-item/export/excel`, `GET /sales-by-item/export/pdf`.
- Filename format: `Sales_By_Item_{DATABASE}_{START}_{END}.{xlsx|pdf}`.

- [x] Implement Excel export using `FromView`, autosizing, and numeric formatting.
- [x] Implement landscape A4 PDF using DomPDF.
- [x] Ensure both exports call the same service and filters as the page.
- [x] Test response types and filenames.

### Task 7: Add deployment documentation and verification

**Files:**
- Create: `README.md`
- Create: `docs/INSTALL-WINDOWS-XAMPP.md`
- Create: `docs/SQL-CHECK.sql`
- Create: `public/.htaccess`

**Interfaces:**
- Documents PHP extensions, ODBC driver, `.env`, install, run, and troubleshooting steps.

- [x] Document `sqlsrv` and `pdo_sqlsrv` requirements and SQL Server ODBC dependency.
- [x] Document composer install, key generation, cache clearing, and web-server setup.
- [x] Add a SQL check script for both function calls.
- [x] Run PHP syntax checks and static project checks.
- [x] Create a distributable ZIP excluding `.env`, `vendor`, caches, and logs.
