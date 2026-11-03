# Dual Daily Sales Target Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show static per-HK daily target and dynamic required daily sales side by side throughout Sales Dashboard outputs.

**Architecture:** Keep `SalesDashboardLineRow` as the single source for both values. Redefine `dailyTarget` to mean static Monthly Target / Total HK, add `requiredDailySales` for the existing catch-up calculation, and explicitly migrate Sales Insights logic to the dynamic property.

**Tech Stack:** Laravel 12, PHP 8.2+, Blade, Maatwebsite Excel, DomPDF, PHPUnit/manual PHP tests.

## Global Constraints
- `Daily Sales Target / HK = Monthly Target / Total HK`.
- `Required Daily Sales = max(0, Monthly Target - MTD) / Remaining HK`.
- Both values appear side by side.
- Sales Insights actions use `Required Daily Sales`, not static daily target.
- Existing non-daily KPI formulas remain unchanged.

---

### Task 1: Add dual metrics to the row model and aggregator

**Files:**
- Modify: `app/Data/SalesDashboardLineRow.php`
- Modify: `app/Data/SalesDashboardReport.php`
- Modify: `app/Services/SalesDashboardAggregator.php`
- Modify: `tests/Unit/SalesDashboardAggregatorTest.php`
- Modify: `tests/manual_dashboard.php`

**Interfaces:**
- `dailyTarget: float` = static target per HK.
- `requiredDailySales: float` = dynamic catch-up requirement.

- [ ] Write assertions for both formulas.
- [ ] Run `php tests/manual_dashboard.php` and verify the old formula fails.
- [ ] Add `requiredDailySales` and implement both formulas.
- [ ] Run `php tests/manual_dashboard.php` and verify pass.

### Task 2: Keep Sales Insights tied to dynamic catch-up requirement

**Files:**
- Modify: `app/Services/SalesDashboardInsightService.php`
- Modify: `tests/Unit/SalesDashboardInsightServiceTest.php`
- Modify: `tests/manual_sales_dashboard_insights.php`

**Interfaces:**
- `required_daily_sales` must equal `SalesDashboardLineRow::$requiredDailySales`.

- [ ] Update test fixtures to include static and required values.
- [ ] Assert pace data exposes required value.
- [ ] Update service references from `dailyTarget` to `requiredDailySales`.
- [ ] Run manual insight test.

### Task 3: Display both values consistently in web/PDF/Excel

**Files:**
- Modify: `resources/views/sales-dashboard/index.blade.php`
- Modify: `resources/views/sales-dashboard/pdf.blade.php`
- Modify: `resources/views/sales-dashboard/export-excel.blade.php`
- Modify: `app/Exports/SalesDashboardExport.php`

**Interfaces:**
- Column order: Monthly Target, Daily Sales Target / HK, Required Daily Sales.

- [ ] Add the two headers side by side.
- [ ] Add both row and total values.
- [ ] Shift Excel number/percentage format columns to match the additional field.
- [ ] Verify PHP syntax and static references.

### Task 4: Package and verification

**Files:**
- Create patch ZIP with only changed application/test/docs files.
- Create full-project ZIP from the latest Device Security project base.
- Create verification report.

- [ ] Run all standalone manual tests available without vendor dependencies.
- [ ] Run `php -l` for PHP files.
- [ ] Check ZIP integrity.
- [ ] Confirm `.env`, SQLite runtime DB, `.git`, and `vendor` are excluded from full ZIP.
