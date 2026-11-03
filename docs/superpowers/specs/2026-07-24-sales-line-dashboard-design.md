# Sales Line Dashboard Design

## Goal

Replace the item-centric report with a line/category sales dashboard that matches the supplied Excel-style screenshot, compares the selected month with the previous month, supports discontinued/clearance filters, and includes responsive visualizations.

## User workflow

The page contains three filters:

1. Database: All, INGCO, or SMI.
2. Report date: one selected date.
3. Item status: All, Normal, Discontinued, Clearance Sale, or Both.

The selected date defines these periods:

- Current MTD: first day of the selected month through the selected date.
- Previous MTD: first day of the previous month through the same day number, clamped to that month's final day.
- Query range: first day of the previous month through the selected date.
- DTD: selected date.
- LDTD: the day immediately before the selected date.

## Table

Rows are fixed sales-line categories in the same order and with the same targets as the reference report:

- JAKARTA — 250,000,000
- GERAI KATAMSO — 135,000,000
- GERAI BOGOR — 220,000,000
- GERAI SETIA BUDI — 135,000,000
- GERAI HM YAMIN — 110,000,000
- GERAI GATOT SUBROTO — 130,000,000
- CITY HARDWARE & CITY GROSIR — 650,000,000
- LIVE HOST CG & CH — 50,000,000
- GERAI PKU — 135,000,000
- ANITA — 350,000,000
- WANDA FINNY — 1,400,000,000
- TIKTOK & TOKPED - ARLIANA — 330,000,000
- SHOPEE - BELLA — 400,000,000
- WEB COMMERCE — 85,000,000
- LAZADA — 15,000,000
- FAJRIN — 900,000,000
- LAIN - LAIN — 950,000,000
- WILIAM — 900,000,000

Unknown line names are placed in LAIN - LAIN. Common naming variants are normalized through aliases.

Columns:

- Sales line
- Monthly sales target
- Daily sales target
- DTD
- LDTD
- Daily growth
- MTD
- LMTD
- MTD growth
- Average daily sales
- Estimated achievement at end of month
- Estimated achievement percentage
- MTD achievement percentage
- Gap to target

The total row sums monetary columns and recalculates percentage columns from totals.

## Calculation rules

- Total sales days: days in the selected month excluding Sundays.
- Elapsed days: selected day number minus one, with a minimum of one. This reproduces the supplied report's 22 July => 21 elapsed days behavior.
- Remaining sales days: total sales days minus elapsed days, with a minimum of one.
- Daily target: positive gap divided by remaining sales days.
- Daily growth: DTD / LDTD - 1; null when LDTD is zero.
- MTD growth: MTD / LMTD - 1; null when LMTD is zero.
- Average daily sales: MTD / elapsed days.
- Estimated EOM: average daily sales multiplied by total sales days.
- Estimated EOM percentage: estimated EOM / monthly target.
- Achievement percentage: MTD / monthly target.
- Gap: monthly target - MTD.

## Database flow

For each selected database, execute the function separately for DTD, LDTD, MTD, and LMTD:

```sql
SELECT *
FROM dbo.fn_PenjualanLini(?, ?)
```

Calling the function per period avoids requiring an InvoiceDate column from an already-aggregated function result. The normalizer accepts common aliases for sales line, sales amount, discontinued flag, clearance flag/discount, item code, and item name.

When a status filter needs a flag that the function does not expose, return a clear error listing the available function columns.

## Visual design

- Yellow multi-level table header matching the screenshot.
- Compact bordered table with right-aligned numbers.
- Arrow and semantic color for positive/negative growth.
- Red achievement cells, yellow total row, sticky line column, and horizontal scrolling on small screens.
- Two responsive native-canvas charts with no CDN dependency:
  - MTD versus Target by line.
  - MTD versus LMTD by line.

## Exports

Excel and PDF use the same table, filters, periods, targets, calculations, and total row as the browser view.

## Compatibility

- Laravel 12
- PHP 8.2+
- Blade and Bootstrap 5
- SQL Server connections sqlsrv_ingco and sqlsrv_smi
- Existing `.env` credentials remain outside the distribution ZIP.
