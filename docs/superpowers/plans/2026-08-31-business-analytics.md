# Business Analytics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Build a Laravel Business Analytics module with a 36-month local data mart, three-month forecasting, multi-level drill-down, target comparison, and management insights.

**Architecture:** Extend the existing Laravel application without replacing current sales modules. SQL Server remains the source of truth; a monthly aggregate in `sqlite_targets` provides fast analytical queries and an explainable local PHP forecast engine.

**Tech Stack:** PHP 8.2+, Laravel 12, SQL Server, SQLite, Blade, Bootstrap 5, Chart.js CDN.

**Spec:** `docs/superpowers/specs/2026-08-31-business-analytics-design.md`

## Global Constraints
- Preserve all existing modules and routes.
- Historical window defaults to 36 months.
- Forecast window is exactly 3 months in UI.
- No external AI/forecasting API.
- Use existing `SalesDataSource` and `SalesDashboardRowNormalizer`.
- Store analytical aggregates on `sqlite_targets`.

---

### Task 1: Forecast engine
**Files:**
- Create `app/Services/BusinessAnalytics/ForecastEngine.php`
- Test `tests/Unit/BusinessAnalytics/ForecastEngineStandaloneTest.php`

- [x] Write a failing standalone test for 3-month periods, confidence range, short-series fallback, and target statuses.
- [x] Run it and verify failure because the engine does not exist.
- [x] Implement trend + recent momentum + seasonality ensemble.
- [x] Run the test and verify it passes.

### Task 2: Analytics data mart
**Files:**
- Create `database/migrations/2026_08_31_000200_create_business_analytics_tables.php`
- Create `app/Models/BusinessAnalyticsMonthlySale.php`
- Create `app/Models/BusinessAnalyticsTarget.php`
- Create `app/Services/BusinessAnalytics/AnalyticsSyncService.php`
- Create `app/Console/Commands/SyncBusinessAnalytics.php`

- [x] Add schema for monthly aggregate, targets, and sync runs.
- [x] Aggregate normalized `fn_PenjualanLini` output by month and full drill dimensions.
- [x] Replace each source/month transactionally.
- [x] Add `analytics:sync --months=36 --through=YYYY-MM` command.

### Task 3: Query, forecast, targets, and insights
**Files:**
- Create `app/Data/BusinessAnalytics/BusinessAnalyticsFilter.php`
- Create `app/Services/BusinessAnalytics/AnalyticsRepository.php`
- Create `app/Services/BusinessAnalytics/TargetService.php`
- Create `app/Services/BusinessAnalytics/InsightService.php`
- Create `app/Services/BusinessAnalytics/BusinessAnalyticsService.php`

- [x] Build monthly trend and year summary queries.
- [x] Build dimension breakdown and drill-down context.
- [x] Attach forecast, official target, recommended target, confidence, and status.
- [x] Generate management insights and contributor rankings.

### Task 4: HTTP and UI
**Files:**
- Create `app/Http/Requests/BusinessAnalyticsRequest.php`
- Create `app/Http/Requests/BusinessAnalyticsTargetRequest.php`
- Create `app/Http/Controllers/BusinessAnalyticsController.php`
- Create `resources/views/business-analytics/index.blade.php`
- Create `public/js/business-analytics.js`

- [x] Validate filters and target inputs.
- [x] Render executive cards, trend chart, forecasts, annual summary, insight cards, and drill table.
- [x] Add target update endpoint.
- [x] Add CSV export endpoint.

### Task 5: Integration patch
**Files:**
- Modify `routes/web.php`
- Modify `config/dashboard_security.php`
- Modify `resources/views/layouts/app.blade.php`
- Modify `app/Console/Kernel.php`

- [x] Register routes behind `business_analytics` permission.
- [x] Add menu catalog, legacy access, audit routes, and landing order.
- [x] Add sidebar entry.
- [x] Schedule incremental monthly sync daily.

### Task 6: Package and verification
**Files:**
- Create `README_INSTALL.md`
- Create `apply_patch.ps1`
- Create `apply_patch.sh`

- [x] PHP-lint all module files.
- [x] Run standalone forecast test.
- [x] Verify installer scripts contain backups and idempotency guards.
- [x] Build downloadable ZIP.
