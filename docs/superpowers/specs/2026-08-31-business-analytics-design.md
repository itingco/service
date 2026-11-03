# Business Analytics Design

## Goal
Add a Business Analytics module to the existing `ingco-it/sales-dashboard` Laravel application that uses 36 months of sales history from AS_INGCO and AS_SMI to provide multi-level trends, three-month forecasts, target-risk status, and actionable insights.

## Scope
- Historical horizon: 36 completed/current months.
- Forecast horizon: 3 months.
- Drill-down: total -> company -> sales line -> customer -> salesperson -> item.
- Comparison: actual vs official target vs recommended target vs forecast.
- Forecast status: ABOVE TARGET, ON TRACK, AT RISK, NO TARGET.
- Forecast runs locally in PHP; no external AI or paid API.
- Existing `dbo.fn_PenjualanLini(start,end)` remains the source of truth.

## Architecture
The module introduces a local analytics data mart in the existing `sqlite_targets` connection. A console sync command reads one month at a time from SQL Server, normalizes existing sales rows, aggregates them to monthly dimensional grain, and replaces that month atomically in SQLite. The dashboard reads only the local data mart, avoiding repeated three-year SQL Server queries.

Forecasting uses an explainable ensemble combining linear trend, recent momentum, and prior-year seasonality when enough history exists. Confidence ranges are derived from historical month-to-month error/volatility and widen for later forecast months.

## Data Grain
`business_analytics_monthly_sales` stores one row per:
- period (YYYY-MM)
- source/company (INGCO/SMI)
- sales line
- customer code/name
- salesperson
- item code/name

Metrics:
- sales amount
- quantity
- invoice count

## Targets
`business_analytics_targets` stores official targets by period and scope. Initial UI supports TOTAL, COMPANY, and LINE targets. The schema supports future extension without replacing existing KPI target behavior.

## User Flow
1. Admin runs `php artisan analytics:sync --months=36` once for initial load.
2. Dashboard `/business-analytics` defaults to the latest synchronized month.
3. User selects company/source, month, and drill-down level.
4. Trend chart shows historical monthly actuals and future 3-month forecast.
5. Forecast cards show official target, recommended target, range, confidence, and risk status.
6. Breakdown table supports drill-down links through company, line, customer, salesperson, and item.
7. Insight cards explain YoY growth, 3-month momentum, concentration, and top positive/negative contributors.

## Performance and Safety
- Dashboard never loads 36 months of raw SQL Server data directly.
- Sync executes month-by-month and source-by-source.
- Local writes happen inside transactions and use chunked upsert/replace semantics.
- Existing sales dashboard, KPI, Sales Insights, Stock Aging, security, and target modules are not replaced.
