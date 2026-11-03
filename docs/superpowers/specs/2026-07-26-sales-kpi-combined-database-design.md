# Sales KPI Dashboard — Combined Database Mode Design

**Date:** 2026-07-26  
**Project:** Laravel 12 `sales-report-web`  
**Scope:** Extend the approved Sales KPI Dashboard so it can report `AS_INGCO`, `AS_SMI`, or both databases combined.

## 1. Objective

Add a combined reporting mode that merges sales data from `AS_INGCO` and `AS_SMI` without losing the ability to review each database individually. The combined report must remain traceable, exportable, and safe against partial-data results.

## 2. Database Filter

The KPI page provides three choices:

- `GABUNGAN`
- `AS_INGCO`
- `AS_SMI`

`GABUNGAN` is an additional mode; the existing single-database modes remain available.

## 3. Grouping Key

All KPI aggregation continues to use the exact value returned by:

```text
LiniPenjualan
```

`LiniPenjualan` is not an item category or product type.

For matching and target lookup, the application normalizes the key using trimmed uppercase text. The original database value remains available for display.

## 4. Combined Aggregation Rules

When `GABUNGAN` is selected, rows from both databases with the same normalized `LiniPenjualan` are merged into one KPI row.

For example:

```text
AS_INGCO / JAKARTA = 300,000,000
AS_SMI   / JAKARTA = 200,000,000
Combined / JAKARTA = 500,000,000
```

The combined figures are calculated as follows:

```text
Combined Sales = AS_INGCO Sales + AS_SMI Sales
Combined Target = AS_INGCO Target + AS_SMI Target
```

A line found in only one database is still displayed. Its value from the other database is treated as zero.

## 5. Target Configuration

Targets remain stored in:

```text
config/sales_kpi_targets.php
```

The target structure remains separated by database:

```php
return [
    'AS_INGCO' => [
        'JAKARTA'       => 500000000,
        'GERAI KATAMSO' => 300000000,
    ],

    'AS_SMI' => [
        'JAKARTA'       => 500000000,
        'GERAI KATAMSO' => 300000000,
    ],
];
```

For combined mode:

```text
Combined Target per LiniPenjualan
= AS_INGCO Target + AS_SMI Target
```

If a target is missing in one database, the existing target from the other database is used and the missing source target is shown as not configured. If both targets are missing, target-dependent KPI components are not calculated.

## 6. Growth Calculation in Combined Mode

Growth is calculated after both databases are aggregated.

```text
Current Combined Sales
= Current AS_INGCO Sales + Current AS_SMI Sales

Previous Combined Sales
= Previous AS_INGCO Sales + Previous AS_SMI Sales

Combined Growth
= (Current Combined Sales - Previous Combined Sales)
  / Previous Combined Sales
```

The approved comparable-date rule remains unchanged:

- Current period starts from day 1 of the selected month.
- Previous period starts from day 1 of the previous month.
- Both periods use the same number of days.
- If the previous month is shorter, the current comparison period is shortened to match it.

If previous combined sales are zero, the Growth component is `Tidak dapat dihitung` and is excluded from the available maximum score before normalization to 90.

## 7. Other KPI Components in Combined Mode

### 7.1 Target Omzet — 40 points

The combined actual sales and combined target are used.

```text
Achievement = Combined Actual Sales / Combined Target
Score = MIN(Achievement / 70%, 1) × 40
```

### 7.2 Discontinued and Clearance — 20 points

Each source database is calculated independently, then summed:

```text
Combined Discontinued Sales
= AS_INGCO Discontinued Sales + AS_SMI Discontinued Sales

Combined Clearance Sales
= AS_INGCO Clearance Sales + AS_SMI Clearance Sales

Combined KPI Realization
= Combined Discontinued Sales + Combined Clearance Sales

Combined Special Target
= 9% × Combined Target
```

An item marked as both discontinued and clearance is counted once in each component, so its `LineTotal` contributes twice to this KPI, as previously approved.

### 7.3 Grade A — 12 points

```text
Combined Grade A Contribution
= Combined Grade A Sales / Combined Actual Sales

Grade A Score
= MIN(Combined Grade A Contribution / 35%, 1) × 12
```

### 7.4 Grade B — 8 points

```text
Combined Grade B Contribution
= Combined Grade B Sales / Combined Actual Sales

Grade B Score
= MIN(Combined Grade B Contribution / 20%, 1) × 8
```

If combined actual sales are zero, Grade A and Grade B are `Tidak dapat dihitung` and the score is normalized from the remaining available components.

## 8. Weighted Overall KPI

The overall KPI across all `LiniPenjualan` remains weighted by target.

In combined mode, the weight uses the combined target:

```text
Overall KPI
= Σ(Line KPI Score × Combined Line Target)
  / Σ(Combined Line Target)
```

Lines with no target in both databases remain visible but do not participate in the weighted total.

## 9. Summary Tab Design

The main chart on the Summary tab is removed and replaced by a sortable, searchable data table.

Columns in combined mode:

1. `LiniPenjualan`
2. Target `AS_INGCO`
3. Target `AS_SMI`
4. Combined Target
5. Sales `AS_INGCO`
6. Sales `AS_SMI`
7. Combined Sales
8. Target Score
9. Growth Score
10. Discontinued/Clearance Score
11. Grade A/B Score
12. Final Score
13. Status

The bottom row displays the weighted combined total.

Status thresholds remain:

```text
Baik              : score >= 80
Perlu Perhatian   : score 60 to 79.99
Kurang            : score < 60
```

## 10. Detail Tabs

The detail tabs remain:

1. Target Omzet
2. Growth vs Last Month
3. Discontinued & Clearance
4. Grade A & Grade B

Small supporting charts remain allowed on these detail tabs.

Every combined-mode detail table shows source contributions and combined results.

### 10.1 Target Omzet Columns

- `LiniPenjualan`
- Target `AS_INGCO`
- Target `AS_SMI`
- Combined Target
- Sales `AS_INGCO`
- Sales `AS_SMI`
- Combined Sales
- Achievement
- Gap to 70%
- Gap to 100%
- Score

### 10.2 Growth Columns

- `LiniPenjualan`
- Current Sales `AS_INGCO`
- Current Sales `AS_SMI`
- Current Combined Sales
- Previous Sales `AS_INGCO`
- Previous Sales `AS_SMI`
- Previous Combined Sales
- Difference
- Combined Growth
- Score
- Status

### 10.3 Discontinued and Clearance Columns

- `LiniPenjualan`
- Target `AS_INGCO`
- Target `AS_SMI`
- Combined Target
- Discontinued `AS_INGCO`
- Discontinued `AS_SMI`
- Clearance `AS_INGCO`
- Clearance `AS_SMI`
- Combined KPI Realization
- 9% Target
- Achievement
- Score

### 10.4 Grade A and B Columns

- `LiniPenjualan`
- Total Sales `AS_INGCO`
- Total Sales `AS_SMI`
- Combined Total Sales
- Grade A Sales `AS_INGCO`
- Grade A Sales `AS_SMI`
- Combined Grade A Sales
- Grade A Contribution
- Grade A Score / 12
- Grade B Sales `AS_INGCO`
- Grade B Sales `AS_SMI`
- Combined Grade B Sales
- Grade B Contribution
- Grade B Score / 8
- Total Grade Score / 20

## 11. Hybrid DataTable Strategy

The dashboard uses a hybrid loading model.

### 11.1 Summary and Recap Tables

The KPI recap tables per `LiniPenjualan` are loaded with the initial page response because their row count is expected to be small.

They support:

- search
- sorting
- pagination
- visible-column control where appropriate
- export of all filtered recap rows

### 11.2 Transaction and Item Detail Tables

Detailed transaction/item tables use server-side DataTable processing.

Laravel processes:

- search
- sorting
- pagination
- database source filter
- `LiniPenjualan` filter
- discontinued filter
- clearance filter
- grade filter
- item code/name search

The page response does not load every transaction into the browser.

## 12. Database Source Column

In `GABUNGAN` mode, all transaction/item detail tables display a `Database` column with one of these values:

```text
AS_INGCO
AS_SMI
```

In single-database mode, this column is hidden because every row has the same source.

The database source remains included in exports even when the on-screen column is hidden.

## 13. Export Rules

Exports are generated by the Laravel backend using the active filters.

Exports must include all rows matching the filters, not only the currently visible DataTable page.

### 13.1 Excel

The Excel workbook continues to provide multiple sheets and is extended with per-database contribution columns in combined mode.

Expected sheets:

1. Summary KPI
2. Target Omzet
3. Growth
4. Discontinued Clearance
5. Grade A B
6. Detail Item/Transaction

The workbook header includes:

- selected mode: `GABUNGAN`, `AS_INGCO`, or `AS_SMI`
- current period
- comparison period
- export timestamp
- note that the system maximum score is 90

Numeric cells remain true Excel numeric values.

### 13.2 PDF

The PDF remains a management report and includes:

- selected database mode
- reporting periods
- weighted KPI summary
- KPI results per `LiniPenjualan`
- source contributions in combined mode
- missing-target and non-calculable-component notes

Very long transaction details remain in Excel and the web DataTable rather than the PDF.

## 14. Connection Failure Rules

Combined mode is all-or-nothing.

If either database connection or function query fails:

- no combined KPI is calculated
- no data from the successful database is shown as a combined result
- the page names the database that failed
- technical connection details and credentials are not exposed
- the exception is logged in `storage/logs/laravel.log`

Example user-facing message:

```text
Laporan KPI gabungan tidak dapat dimuat.

Koneksi ke database AS_SMI gagal.
Data AS_INGCO tidak ditampilkan agar hasil gabungan tidak menyesatkan.
```

If both databases fail, both database names are listed.

Single-database mode only reports the selected database failure.

## 15. Data Flow

For `GABUNGAN` mode:

1. Validate filter and date.
2. Build current and previous comparison periods.
3. Query `dbo.fn_PenjualanLini` for current and previous periods from `AS_INGCO`.
4. Query the same periods from `AS_SMI`.
5. Abort if any required query fails.
6. Add source metadata to every raw row.
7. Aggregate each database independently by normalized `LiniPenjualan`.
8. Merge database aggregates by normalized `LiniPenjualan`.
9. Add both configured targets.
10. Calculate the combined KPI components.
11. Normalize non-calculable components to the 90-point scale.
12. Calculate the target-weighted overall result.
13. Send recap data to Blade.
14. Serve transaction/item detail through server-side DataTable endpoints.
15. Export from backend using the same filters and calculation services.

## 16. Service Boundaries

The change should preserve focused responsibilities:

- `SalesKpiDataService`: query one named database and attach its source name.
- `SalesKpiCombinedDataService`: coordinate both databases and enforce all-or-nothing behavior.
- `SalesKpiAggregator`: aggregate raw rows per source and `LiniPenjualan`.
- `SalesKpiCalculator`: calculate KPI scores from already-aggregated values.
- `SalesKpiTargetService`: return per-database and combined targets.
- `SalesKpiDetailQueryService`: provide server-side DataTable rows and counts.
- `SalesKpiExportService`: export all filtered results independently from browser pagination.

The Blade template must not calculate KPI formulas.

## 17. Error Handling

The application must handle:

- one or both database connections failing
- one or both function queries failing
- required columns missing from either database result
- target missing for one source or both sources
- no sales rows for the selected period
- previous combined sales equal to zero
- combined actual sales equal to zero
- invalid DataTable sorting column
- export request with invalid filters

Errors shown to the user remain concise. Technical details are logged.

## 18. Testing Requirements

Automated tests must cover at least:

1. Same `LiniPenjualan` from both databases merges into one row.
2. A line existing in only one database remains visible.
3. Combined sales equal the sum of source sales.
4. Combined target equals the sum of source targets.
5. Combined growth is calculated after summing both sources.
6. Growth is not calculated when previous combined sales are zero.
7. Discontinued and clearance source values combine correctly.
8. An item carrying both statuses contributes twice to the special KPI.
9. Grade A and B contributions use combined actual sales as the denominator.
10. Missing target in one source uses the available source target and shows a warning.
11. Missing targets in both sources exclude target-dependent components.
12. Weighted overall KPI uses combined targets.
13. Failure of `AS_INGCO` aborts combined mode.
14. Failure of `AS_SMI` aborts combined mode.
15. Error message names the failed database.
16. Server-side DataTable returns correct total and filtered counts.
17. Combined detail rows include the `Database` source.
18. Single-database detail hides the source column in the UI.
19. Excel export includes all filtered rows, not only one page.
20. PDF and Excel headers identify the selected database mode.

## 19. Non-Goals

This change does not:

- merge the physical SQL Server databases
- write data back to either database
- change `dbo.fn_PenjualanLini`
- replace the existing target configuration with a database table
- convert the Blade dashboard into React or Vue
- allow a partial combined result when one database is unavailable

## 20. Acceptance Criteria

The feature is accepted when:

- users can select `GABUNGAN`, `AS_INGCO`, or `AS_SMI`
- combined rows merge by normalized `LiniPenjualan`
- source and combined amounts are visible on every KPI tab
- summary uses a DataTable instead of the main chart
- detail tabs retain small supporting charts
- transaction/item detail uses server-side DataTable processing
- the `Database` column appears in combined detail mode
- Excel/PDF exports include all filtered rows
- combined mode aborts and names the failed database when either source fails
- all approved KPI formulas and 90-point normalization rules remain unchanged
