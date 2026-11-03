# Sales Line Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Sales By Item page with a line-category dashboard matching the supplied report and comparing the selected month with the previous month.

**Architecture:** A pure-PHP filter, normalizer, and aggregator calculate the report independently of Laravel. A Laravel service calls the function for DTD, LDTD, MTD, and LMTD on one or both SQL Server connections, applies the item-status filter, and returns a dashboard report DTO. Blade, Excel, and PDF render the same DTO.

**Tech Stack:** Laravel 12, PHP 8.2+, Blade, Bootstrap 5, SQL Server, Laravel Excel, DomPDF, native Canvas charts.

## Global Constraints

- One web page with database, report-date, and item-status filters.
- Current month and previous-month comparable period start on day one.
- Fixed line order and targets must match the reference report.
- Database access remains read-only through dbo.fn_PenjualanLini.
- Charts must work without an external CDN dependency.

---

### Task 1: Domain filter and period calculation

**Files:**
- Create: `app/Data/SalesDashboardFilter.php`
- Test: `tests/manual_dashboard.php`

**Interfaces:**
- Produces: `SalesDashboardFilter::from(string $database, string $endDate, string $itemStatus): self`

- [ ] Write failing date-range and validation assertions.
- [ ] Run `php tests/manual_dashboard.php` and verify missing-class failure.
- [ ] Implement database/status validation and all date boundaries.
- [ ] Run the manual tests and verify the filter assertions pass.

### Task 2: Function row normalization and status filtering

**Files:**
- Create: `app/Support/SalesDashboardRowNormalizer.php`
- Test: `tests/manual_dashboard.php`

**Interfaces:**
- Produces normalized arrays with line, invoice_date, sales, discontinue, clearance, item_code, item_name, and source.

- [ ] Add failing assertions for aliases, percentages, booleans, and required columns.
- [ ] Implement normalization and capability checks.
- [ ] Verify normalizer assertions pass.

### Task 3: Line report calculation

**Files:**
- Create: `app/Data/SalesDashboardLineRow.php`
- Create: `app/Data/SalesDashboardReport.php`
- Create: `app/Services/SalesDashboardAggregator.php`
- Create: `config/sales_dashboard.php`
- Test: `tests/manual_dashboard.php`

**Interfaces:**
- Produces: `SalesDashboardAggregator::aggregate(SalesDashboardFilter $filter, array $transactions): SalesDashboardReport`

- [ ] Add failing calculation assertions for DTD/LDTD/MTD/LMTD, targets, growth, estimates, aliases, and totals.
- [ ] Implement fixed category order, aliases, calculations, and total row.
- [ ] Verify all domain assertions pass.

### Task 4: Laravel data service and routes

**Files:**
- Create: `app/Services/SalesDashboardService.php`
- Create: `app/Http/Requests/SalesDashboardRequest.php`
- Create: `app/Http/Controllers/SalesDashboardController.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/web.php`

**Interfaces:**
- Controller routes: index, export Excel, export PDF.
- Service uses existing `SalesDataSource::fetch()` for the two-month range.

- [ ] Add service assertions using a fake data source.
- [ ] Implement service status filtering and schema validation.
- [ ] Implement request, controller, redirects, and filenames.
- [ ] Verify manual tests and PHP syntax.

### Task 5: Responsive dashboard UI and charts

**Files:**
- Create: `resources/views/sales-dashboard/index.blade.php`
- Create: `public/js/sales-dashboard-charts.js`
- Modify: `resources/views/layouts/app.blade.php`

**Interfaces:**
- Blade receives `$report`, `$filter`, and `$error`.
- Canvas elements consume JSON datasets embedded by Blade.

- [ ] Implement the screenshot-style grouped table header and totals.
- [ ] Add database, selected-date, and item-status filters.
- [ ] Implement responsive table behavior and semantic growth indicators.
- [ ] Implement dependency-free responsive canvas charts.
- [ ] Verify Blade directive balance and JavaScript syntax.

### Task 6: Excel/PDF exports and distribution

**Files:**
- Create: `app/Exports/SalesDashboardExport.php`
- Create: `resources/views/sales-dashboard/export-excel.blade.php`
- Create: `resources/views/sales-dashboard/pdf.blade.php`
- Modify: `composer.json`
- Modify: `.env.example`
- Modify: `README.md`
- Modify: `docs/SQL-CHECK.sql`

**Interfaces:**
- Excel and PDF consume `SalesDashboardReport`.

- [ ] Implement export views and spreadsheet styling.
- [ ] Update Laravel 12 dependency constraints and documentation.
- [ ] Run all manual tests, PHP syntax checks, JSON validation, route/static checks, and ZIP integrity checks.
- [ ] Package the full project without `.env`, `vendor`, `.git`, cache, or logs.
