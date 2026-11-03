# Separate Sales Insights & R&D Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mempertahankan `/sales-dashboard` sebagai laporan operasional yang sudah dikenal user, lalu memindahkan Advanced Management Dashboard ke `/sales-insights` dan menambahkan analisis R&D yang dapat ditindaklanjuti.

**Architecture:** Halaman operasional tetap memakai `SalesDashboardController` dan `SalesDashboardExport` lama. Halaman baru memakai `SalesInsightsController`, `SalesDashboardInsightService`, dan `SalesResearchInsightService`; data periode berjalan berasal dari `SalesDashboardReport`, sedangkan detail periode pembanding diambil melalui metode rentang baru pada `SalesDashboardService`. View baru memisahkan management control dan R&D insight agar navigasi serta tanggung jawab halaman jelas.

**Tech Stack:** Laravel 12, PHP 8.2, Blade, Maatwebsite Excel, JavaScript canvas charts, PHPUnit/manual PHP tests.

## Global Constraints

- URL lama `/sales-dashboard` tetap tersedia dan tidak menampilkan panel advanced management.
- URL baru adalah `/sales-insights`.
- Halaman lama memiliki tombol `Dashboard Analisis Lanjutan` yang membawa filter aktif ke URL baru.
- Halaman baru memiliki tombol kembali ke Sales Dashboard operasional.
- Analisis R&D harus berdasarkan data function yang tersedia: item, grade, customer, lini, quantity, discount, discontinued, clearance, dan LineTotal.
- Jangan mengubah rumus target, achievement, growth, forecast, atau data mentah lama.
- Tidak menambah migration atau dependency baru.

---

### Task 1: Route Separation and Navigation

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/SalesDashboardController.php`
- Create: `app/Http/Controllers/SalesInsightsController.php`
- Modify: `resources/views/sales-dashboard/index.blade.php`
- Create: `resources/views/sales-insights/index.blade.php`
- Test: `tests/Feature/SalesInsightsPageTest.php`

**Interfaces:**
- Produces route `sales-insights.index` at `/sales-insights`.
- Preserves route `sales-dashboard.index` at `/sales-dashboard`.

- [ ] Write a failing route/view test asserting both URLs exist, old page excludes management panels, and the old page includes the navigation button.
- [ ] Run the focused test and confirm failure because the new route/controller/view does not exist.
- [ ] Add the route, controller, view shell, and navigation buttons.
- [ ] Restore the old controller/view behavior so management insights are not built on `/sales-dashboard`.
- [ ] Run focused tests and confirm pass.

### Task 2: Reusable Period Detail Fetching

**Files:**
- Modify: `app/Services/SalesDashboardService.php`
- Test: `tests/Unit/SalesDashboardServicePeriodDetailsTest.php`

**Interfaces:**
- Produces `detailsForPeriod(SalesDashboardFilter $filter, DateTimeImmutable $start, DateTimeImmutable $end): array`.

- [ ] Write a failing unit test showing the service can normalize and combine selected sources for an arbitrary comparable period.
- [ ] Run the test and confirm the method is missing.
- [ ] Implement the smallest reusable method and make existing `details()` delegate to it.
- [ ] Run focused tests and confirm pass.

### Task 3: R&D Insight Engine

**Files:**
- Create: `app/Data/SalesResearchInsights.php`
- Create: `app/Services/SalesResearchInsightService.php`
- Test: `tests/Unit/SalesResearchInsightServiceTest.php`
- Test: `tests/manual_sales_research_insights.php`

**Interfaces:**
- Consumes current and previous detail rows.
- Produces product signals, demand gains/losses, customer concentration, discount effectiveness, grade demand, channel fit, and recommendations.

- [ ] Write failing tests for new demand, lost demand, scale-up, evaluation, concentration risk, grade growth, and channel fit.
- [ ] Run tests and confirm failure because the service/DTO do not exist.
- [ ] Implement aggregation by item and classifications with deterministic thresholds.
- [ ] Run unit/manual tests and confirm pass.

### Task 4: R&D Dashboard Presentation

**Files:**
- Create: `resources/views/sales-insights/partials/management-insights.blade.php`
- Create: `resources/views/sales-insights/partials/research-insights.blade.php`
- Reuse: `public/js/sales-dashboard-insights.js`
- Modify: `resources/views/sales-insights/index.blade.php`
- Test: `tests/Feature/SalesInsightsPageTest.php`

**Interfaces:**
- Displays management control plus R&D evidence and recommendations on `/sales-insights` only.

- [ ] Extend the feature test with R&D section labels and chart asset assertions.
- [ ] Run the test and confirm failure.
- [ ] Build the management and R&D partials with responsive tables/cards.
- [ ] Pass chart data using `Illuminate\Support\Js::from` and load the existing insight chart asset.
- [ ] Run focused tests and confirm pass.

### Task 5: Separate Insights Excel Export

**Files:**
- Restore: `app/Exports/SalesDashboardExport.php` to operational two-sheet behavior.
- Create: `app/Exports/SalesInsightsExport.php`
- Modify: `app/Http/Controllers/SalesInsightsController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SalesInsightsExportTest.php`

**Interfaces:**
- Produces route `sales-insights.export.excel`.
- Old export remains `Ringkasan Sales` + `Data Mentah`.
- New export provides management, R&D product signals, demand watch, and current raw data.

- [ ] Write failing tests for old/new sheet separation.
- [ ] Run tests and confirm failure.
- [ ] Restore the old export and add the dedicated insights export.
- [ ] Run focused tests and confirm pass.

### Task 6: Packaging and Verification

**Files:**
- Create: `CARA_PASANG_PATCH_SALES_INSIGHTS.txt`
- Create: `docs/SALES-INSIGHTS-RD.md`

- [ ] Run all manual insight tests.
- [ ] Run PHP lint on all changed PHP files.
- [ ] Run JavaScript syntax validation.
- [ ] Verify route names and Blade references by static integration checks.
- [ ] Build a patch ZIP without `.env`, SQLite, `vendor`, cache, or logs.
- [ ] Build a full project ZIP without `.env`, SQLite, `vendor`, cache, or logs.
- [ ] Verify ZIP integrity and write a verification report.
