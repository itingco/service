# Sales KPI Dashboard Design

**Project:** `sales-report-web`
**Platform:** Laravel 12, PHP 8.2+, Blade, SQL Server
**Date:** 2026-07-25
**System score ceiling:** 90 points

## 1. Purpose

Add a management KPI dashboard that evaluates sales performance by the exact value of `LiniPenjualan` returned by `dbo.fn_PenjualanLini`. A sales line is not an item category or item type. Examples include `JAKARTA` and `GERAI KATAMSO`.

The system calculates four KPI groups totaling 90 points:

| KPI | Maximum points |
|---|---:|
| Target Omzet | 40 |
| Growth Omzet vs Last Month | 10 |
| Discontinued and Clearance Sale | 20 |
| Grade A and Grade B | 20 |
| **Total system score** | **90** |

The remaining 10% KPI, daily monitoring, is outside this system and is not entered, scored, displayed, or exported by this dashboard.

## 2. Scope and page boundary

The KPI dashboard is added as one Blade page with all tab data loaded in one request. The existing sales dashboard remains available to avoid regression. The KPI page uses a dedicated route such as `/sales-kpi` and a navigation link from the existing dashboard.

The page contains these tabs:

1. Ringkasan KPI
2. Target Omzet
3. Growth vs Last Month
4. Discontinued & Clearance
5. Grade A & Grade B

The page supports one selected database at a time: `AS_INGCO` or `AS_SMI`. Cross-database combined scoring is outside this scope.

## 3. User filters

### 3.1 Score filters

These filters determine the KPI dataset:

- Database: `AS_INGCO` or `AS_SMI`
- Report end date

The current period for Target Omzet, Discontinued/Clearance, and Grade A/B is the first day of the selected month through the selected report end date.

### 3.2 Growth comparison period

Growth uses equal day counts:

- Current growth period: first day of the selected month through the comparable day
- Previous growth period: first day of the previous month through the same comparable day
- Comparable day: the smaller of the selected day number and the final day number of the previous month

Example for a selected end date of 31 March 2026:

- Target, Discontinued/Clearance, and Grade period: 1–31 March 2026
- Growth current period: 1–28 March 2026
- Growth previous period: 1–28 February 2026

The equal-day truncation applies only to Growth so that month-end differences do not remove valid current-month sales from the other KPI components.

### 3.3 Detail-only filters

These filters affect detail tables only and must never recalculate KPI scores:

- `LiniPenjualan`
- Discontinued: All, Yes, No
- Clearance: All, Clearance Sale, Not Clearance
- Grade: All or an individual grade
- Item search by `ItemCode` or `ItemName`

## 4. Target configuration

Monthly sales targets are stored in `config/sales_kpi_targets.php`. Targets are fixed per database and `LiniPenjualan` and remain the same for every month.

```php
<?php

return [
    'AS_INGCO' => [
        'JAKARTA'       => 500_000_000,
        'GERAI KATAMSO' => 300_000_000,
    ],

    'AS_SMI' => [
        'JAKARTA'       => 500_000_000,
        'GERAI KATAMSO' => 300_000_000,
    ],
];
```

Target lookup normalizes both configuration keys and function values with `trim` and uppercase matching. The original function value remains unchanged for display.

All sales lines returned by the function are displayed dynamically. A line missing from the configuration receives the badge `Target belum diatur`.

For a line without a configured target:

- Target Omzet is unavailable.
- Discontinued/Clearance is unavailable.
- Growth and Grade A/B may still be calculated when their own prerequisites exist.
- The line score is normalized from available components.
- The line is excluded from the weighted all-line total because it has no target weight.

## 5. SQL data source and field mapping

For each required period, execute the selected database function:

```sql
SELECT *
FROM dbo.fn_PenjualanLini(?, ?)
```

The design uses these verified result columns from the supplied workbook sample:

| Purpose | Function column | Usage |
|---|---|---|
| Sales-line grouping | `LiniPenjualan` | Grouping key |
| Sales amount | `LineTotal` | Summed as omzet |
| Discontinued flag | `Discontinue` | `1` means discontinued |
| Item status | `StatusItem` | Display/supporting information only |
| Grade | `Grade` | Grade A and B scoring |
| Quantity | `Quantity` | Detail display |
| Clearance status | `StatusClearanceSale` | Exact normalized value `CLEARANCE SALE` identifies clearance |
| Clearance discount | `ClearanceSaleDiscount` | Detail display only; not used to identify clearance |
| Invoice date | `InvoiceDate` | Detail display |
| Invoice number | `InvoiceNumber` | Detail display |
| Item code | `ItemCode` | Detail display/search |
| Item name | `ItemName` | Detail display/search |

Required scoring columns are validated before calculation:

- `LiniPenjualan`
- `LineTotal`
- `Discontinue`
- `StatusClearanceSale`
- `Grade`

Missing required columns stop the report and produce a user-safe error naming the missing column.

## 6. Data normalization

- `LiniPenjualan`, `Grade`, and `StatusClearanceSale` are compared using trimmed uppercase values.
- `LineTotal`, `Quantity`, and `Discontinue` are converted safely to numeric values.
- `NULL` and empty numeric values become zero.
- Negative `LineTotal` values remain negative so returns reduce the corresponding omzet.
- Component scores are always clamped between zero and their maximum points.
- `Discontinue = 1` identifies discontinued sales.
- `StatusClearanceSale = 'CLEARANCE SALE'` identifies clearance sales.
- `ClearanceSaleDiscount` is never the primary clearance condition.

## 7. KPI calculation rules

### 7.1 Target Omzet — maximum 40 points

Prerequisite: configured target greater than zero.

```text
Achievement = Current omzet / Target omzet
Raw score   = (Achievement / 70%) × 40
Final score = clamp(Raw score, 0, 40)
```

Examples:

- 35% achievement gives 20 points.
- 70% or more gives the maximum 40 points.

### 7.2 Growth vs Last Month — maximum 10 points

Prerequisite: previous comparable-period omzet greater than zero.

```text
Growth      = (Current comparable omzet - Previous comparable omzet)
              / Previous comparable omzet
Raw score   = (max(Growth, 0) / 10%) × 10
Final score = clamp(Raw score, 0, 10)
```

Examples:

- Negative growth gives 0 points.
- 4% growth gives 4 points.
- 10% or more gives the maximum 10 points.

When the previous comparable-period omzet is zero, Growth is `Tidak dapat dihitung`, contributes no points, and is removed from the available maximum before normalization.

### 7.3 Discontinued and Clearance — maximum 20 points

Prerequisite: configured target greater than zero.

```text
Special target  = 9% × Target omzet
KPI realization = Discontinued omzet + Clearance omzet
Raw score       = (KPI realization / Special target) × 20
Final score     = clamp(Raw score, 0, 20)
```

Discontinued and clearance omzet are calculated separately:

```text
Discontinued omzet = sum(LineTotal where Discontinue = 1)
Clearance omzet    = sum(LineTotal where StatusClearanceSale = 'CLEARANCE SALE')
```

A transaction that is both discontinued and clearance is intentionally counted twice in KPI realization, once in each subtotal. The score remains capped at 20 points.

### 7.4 Grade A — maximum 12 points

Prerequisite: current total omzet greater than zero.

```text
Grade A contribution = Grade A omzet / Current total omzet
Raw score             = (Grade A contribution / 35%) × 12
Final score           = clamp(Raw score, 0, 12)
```

### 7.5 Grade B — maximum 8 points

Prerequisite: current total omzet greater than zero.

```text
Grade B contribution = Grade B omzet / Current total omzet
Raw score             = (Grade B contribution / 20%) × 8
Final score           = clamp(Raw score, 0, 8)
```

Grade A and Grade B use total actual omzet for the same `LiniPenjualan` as the denominator, including other grades and blank grades.

When current total omzet is zero, both Grade components are `Tidak dapat dihitung` and are removed from the available maximum before normalization.

## 8. Per-line normalization and status

The native maximum is 90 points. When one or more components are unavailable, normalize the score back to the 90-point scale:

```text
Normalized line score =
    Earned available points / Available maximum points × 90
```

Normalization is not applied when all 90 points are available.

When no component is available, the line score and status are `Tidak dapat dihitung`.

Status thresholds use the normalized 0–90 score:

| Status | Rule |
|---|---:|
| Baik | Score >= 80 |
| Perlu Perhatian | 60 <= Score < 80 |
| Kurang | Score < 60 |
| Tidak dapat dihitung | No available component |

## 9. Weighted all-line total

Only lines with a configured positive target participate in the overall total. Each line is weighted by its target:

```text
Overall KPI score =
    sum(Normalized line score × Line target)
    / sum(Line target)
```

Lines without targets remain visible but are excluded from the numerator and denominator.

If no target-configured line has a calculable score, the overall score is `Tidak dapat dihitung`.

The summary component cards use the same target-weighting principle within their native maxima:

- Target Omzet: 0–40
- Growth: 0–10; unavailable growth lines are excluded from that card
- Discontinued/Clearance: 0–20
- Grade A/B: 0–20; unavailable grade lines are excluded from that card

## 10. Page design

### 10.1 Header and score cards

The page header shows:

- Database
- Full current report period
- Growth comparison periods
- Export buttons
- A note that the system maximum is 90 points because daily monitoring is outside the system

Five score cards appear below the filters:

1. Overall KPI, maximum 90
2. Target Omzet, maximum 40
3. Growth, maximum 10
4. Discontinued/Clearance, maximum 20
5. Grade A/B, maximum 20

Status colors:

- Green: Baik
- Yellow: Perlu Perhatian
- Red: Kurang
- Gray: target missing or unavailable

### 10.2 Ringkasan KPI tab

Charts:

- Overall normalized KPI score by `LiniPenjualan`, scale 0–90
- Component-score composition by `LiniPenjualan`

Table columns:

- `LiniPenjualan`
- Target
- Current omzet
- Target score / 40
- Growth score / 10
- Discontinued/Clearance score / 20
- Grade A/B score / 20
- Available maximum
- Normalized final score / 90
- Status

A final row displays the target-weighted overall total.

### 10.3 Target Omzet tab

Chart compares:

- Monthly target
- 70% KPI threshold
- Current omzet

Table columns:

- `LiniPenjualan`
- Monthly target
- 70% threshold
- Current omzet
- Achievement percentage
- Gap to 70%
- Gap to 100%
- Score / 40
- Status or target-missing badge

```text
Gap to 70%  = max(70% × target - current omzet, 0)
Gap to 100% = max(target - current omzet, 0)
```

### 10.4 Growth tab

Chart compares current and previous equal-day omzet by line.

Table columns:

- `LiniPenjualan`
- Current comparable period
- Previous comparable period
- Difference
- Growth percentage
- Target growth 10%
- Score / 10
- Status

A zero previous-period amount displays `Tidak dapat dihitung` and `N/A` rather than infinity or a divide-by-zero result.

### 10.5 Discontinued & Clearance tab

Chart displays per line:

- Discontinued omzet
- Clearance omzet
- Special target equal to 9% of the line target

Summary table columns:

- `LiniPenjualan`
- Target omzet
- Special target 9%
- Discontinued omzet
- Clearance omzet
- KPI realization
- Achievement percentage
- Score / 20

Detail table columns:

- `InvoiceDate`
- `InvoiceNumber`
- `LiniPenjualan`
- `ItemCode`
- `ItemName`
- `Discontinue`
- `StatusItem`
- `StatusClearanceSale`
- `ClearanceSaleDiscount`
- `Quantity`
- `LineTotal`

### 10.6 Grade A & Grade B tab

Chart displays Grade A and B contribution percentages against 35% and 20% targets.

Summary table columns:

- `LiniPenjualan`
- Current total omzet
- Grade A omzet
- Grade A contribution
- Grade A score / 12
- Grade B omzet
- Grade B contribution
- Grade B score / 8
- Combined score / 20

Detail table columns:

- `LiniPenjualan`
- `Grade`
- `ItemCode`
- `ItemName`
- `Quantity`
- `LineTotal`
- Contribution within the line

## 11. Application structure

```text
app/
├── Http/Controllers/
│   └── SalesKpiController.php
├── Http/Requests/
│   └── SalesKpiRequest.php
├── Services/SalesKpi/
│   ├── SalesKpiDataService.php
│   ├── SalesKpiCalculator.php
│   └── SalesKpiPeriodService.php
├── Data/SalesKpi/
│   ├── SalesKpiFilter.php
│   ├── SalesLineKpi.php
│   └── SalesKpiReport.php
├── Exports/
│   └── SalesKpiExport.php
└── Exceptions/
    └── SalesKpiDataException.php

config/
└── sales_kpi_targets.php

resources/views/sales-kpi/
├── index.blade.php
└── pdf.blade.php

public/js/
└── sales-kpi-charts.js
```

Responsibilities:

- `SalesKpiController`: request validation, service orchestration, view and export responses
- `SalesKpiPeriodService`: full current period plus equal-day Growth periods
- `SalesKpiDataService`: connection selection, function calls, required-column validation, normalized raw rows
- `SalesKpiCalculator`: grouping, component scores, unavailable-component handling, normalization, weighted totals
- Data objects: stable structures for Blade, Excel, PDF, and tests
- Blade: display only; no KPI formula logic

Charts use local JavaScript assets and no external CDN dependency.

## 12. Error handling

### Database connection failure

User message:

```text
Laporan KPI belum dapat dimuat. Koneksi ke database yang dipilih gagal.
```

Credentials and stack traces are never shown. Technical details are logged to `storage/logs/laravel.log`.

### Function execution failure

User message:

```text
Function dbo.fn_PenjualanLini tidak dapat dijalankan untuk periode yang dipilih.
```

### Missing required column

User message names the column, for example:

```text
Kolom StatusClearanceSale tidak ditemukan pada hasil fn_PenjualanLini.
```

### Empty period

The page displays `Tidak ada data penjualan pada periode yang dipilih` and does not render misleading empty charts.

### Missing target

The line remains visible with `Target belum diatur`; target-dependent components are unavailable and the line is excluded from the overall target-weighted score.

## 13. Exports

### Excel

One workbook contains:

1. Ringkasan KPI
2. Target Omzet
3. Growth
4. Discontinued Clearance
5. Grade A B
6. Detail Item

Each sheet includes database, periods, export timestamp, and the note that the system maximum is 90 points. Monetary and percentage cells are exported as numeric cells, not formatted text, so users can sort, filter, and calculate.

Summary sheets always reflect the full KPI dataset. The Detail Item sheet honors detail-only filters.

### PDF

The PDF is management-focused and includes:

- Database and period information
- Overall and component score summaries
- Score table by `LiniPenjualan`
- KPI component tables
- Notes for missing targets and unavailable calculations

Large transaction-level detail is omitted from PDF and remains available in Excel and the web page.

## 14. Test plan

### Unit tests

- Target achievement 35% returns 20/40.
- Target achievement 70% or more returns 40/40.
- Negative target achievement never creates a negative score.
- Negative Growth returns 0/10.
- Growth 5% returns 5/10.
- Growth 10% or more returns 10/10.
- Previous-period zero makes Growth unavailable.
- A discontinued and clearance row is counted in both subtotals.
- Discontinued/Clearance score is capped at 20.
- Grade A contribution 35% returns 12/12.
- Grade B contribution 20% returns 8/8.
- Current total zero makes Grade A/B unavailable.
- Unavailable components normalize available points to the 90-point scale.
- Missing target leaves the line visible and excludes it from the weighted total.
- Weighted overall score uses line targets, not a simple average.
- Detail-only filters do not change summary scores.
- March 31 Growth compares March 1–28 with February 1–28, while other current-period KPIs use March 1–31.

### Feature tests

- Valid database and date render all five tabs.
- Invalid database or date returns validation errors.
- Missing required function columns return a safe error.
- Empty data returns the empty-state page.
- Excel export produces all six sheets.
- PDF export returns a valid PDF response.

### Manual verification

- Compare calculations against the supplied workbook sample for several `LiniPenjualan` values.
- Verify `JAKARTA` and `GERAI KATAMSO` target lookup.
- Verify a newly returned, unconfigured `LiniPenjualan` remains visible.
- Verify mobile horizontal scrolling, sticky first column, tab navigation, chart readability, and export links.

## 15. Out of scope

- The external 10% daily-monitoring KPI
- A target-maintenance database table or target-editing UI
- Different targets by month
- Combined `AS_INGCO` and `AS_SMI` scoring in one report
- Changing `dbo.fn_PenjualanLini`
- User authentication or authorization changes
- Scheduled email delivery or automated KPI alerts

## 16. Acceptance criteria

The design is complete when:

1. Every function-returned `LiniPenjualan` is visible.
2. `JAKARTA` and `GERAI KATAMSO` are treated as sales-line values, not item categories.
3. All four agreed KPI groups calculate exactly according to this document.
4. The system score ceiling remains 90 points.
5. Unavailable components normalize correctly without divide-by-zero errors.
6. The all-line total is weighted by configured targets.
7. Detail filters do not alter KPI scores.
8. Web, Excel, and PDF show consistent KPI results.
9. Missing targets, missing columns, connection failures, and empty periods produce clear safe messages.
10. Existing sales-dashboard functionality remains available.
