# Sales KPI Combined Target Settings — Design Specification

**Date:** 2026-07-28
**Project:** Laravel 12 Sales Report / KPI Dashboard
**Scope:** Authentication, combined KPI mode, and editable combined sales targets stored in local SQLite.

## 1. Goal

Create a dedicated **Pengaturan Target KPI** feature so sales targets can be viewed and edited from the application without changing PHP configuration files.

The KPI dashboard must always use combined actual sales from:

- `AS_INGCO`
- `AS_SMI`

Targets are not separated by database. There is exactly one active target for each `LiniPenjualan`.

## 2. Combined KPI Rule

The KPI dashboard no longer provides a database selector.

For each normalized `LiniPenjualan`:

```text
Omzet Aktual Gabungan
= Omzet AS_INGCO
+ Omzet AS_SMI
```

The same `LiniPenjualan` from both databases is merged into one KPI row.

Transaction details remain in their original form. A `Database` column may still identify whether a row came from `AS_INGCO` or `AS_SMI`, but that source does not change the target calculation.

If one database fails to connect, the combined KPI report must stop and display which database failed. Partial combined results must not be shown.

## 3. Target Storage

Targets move from `config/sales_kpi_targets.php` to the local application SQLite database:

```text
database/database.sqlite
```

Table:

```text
sales_kpi_targets
- id
- sales_line
- monthly_target nullable
- created_at
- updated_at
```

Rules:

- `sales_line` is unique.
- The normalized key is `strtoupper(trim($salesLine))`.
- `monthly_target` is a numeric value.
- Empty or zero target input is stored as `NULL`.
- `NULL` means **Target belum diatur**.
- A target is one active value for all report months.
- Changing a target affects current and historical KPI reports that are opened afterward.

## 4. Source of LiniPenjualan

The list shown on the target settings page is built from:

1. Current-month `LiniPenjualan` returned by `dbo.fn_PenjualanLini` from `AS_INGCO`.
2. Current-month `LiniPenjualan` returned by `dbo.fn_PenjualanLini` from `AS_SMI`.
3. Existing target records stored in SQLite.

The same normalized name is merged into one row.

A target that already exists in SQLite must remain visible even when that line has no transaction in the current month.

Users cannot manually add or rename `LiniPenjualan`. Names only come from the function results or existing previously discovered target records.

## 5. Target Settings Page

URL:

```text
/sales-kpi/targets
```

Navigation:

```text
Dashboard KPI
Pengaturan Target KPI
```

The page displays a searchable table with these columns:

| Column | Description |
|---|---|
| LiniPenjualan | Read-only line name |
| Target Gabungan | Editable target value |
| Omzet Bulan Ini | Combined current-month actual sales |
| Status Transaksi | `Aktif bulan ini` or `Tidak ada transaksi bulan ini` |
| Status Target | `Sudah diatur` or `Target belum diatur` |

Example:

| LiniPenjualan | Target Gabungan | Omzet Bulan Ini | Status Transaksi | Status Target |
|---|---:|---:|---|---|
| JAKARTA | Rp500.000.000 | Rp275.000.000 | Aktif bulan ini | Sudah diatur |
| GERAI BOGOR | kosong | Rp85.000.000 | Aktif bulan ini | Target belum diatur |
| GERAI KATAMSO | Rp300.000.000 | Rp0 | Tidak ada transaksi bulan ini | Sudah diatur |

Editing behavior:

- Target values are edited directly in the table.
- Multiple rows may be changed before saving.
- One button is provided: **Simpan Semua Perubahan**.
- The entire save runs inside one SQLite transaction.
- If any submitted row fails validation, no row is saved.
- Successful save returns a clear success message.
- Validation errors identify the affected `LiniPenjualan`.

## 6. KPI Dashboard Behavior

The KPI dashboard always uses combined data and one combined target.

```text
Achievement
= Omzet Aktual Gabungan
÷ Target Gabungan
```

If the target is missing:

```text
Target belum diatur untuk LiniPenjualan: <nama lini>
```

KPI components that require a target become `N/A`:

- Target Omzet
- Discontinued/Clearance

Other components continue to calculate when their own inputs are available:

- Growth vs Last Month
- Grade A
- Grade B

The final line score is normalized to the 90-point scale using only components that can be calculated.

The previous KPI formulas remain unchanged:

- Target Omzet: maximum 40 points
- Growth: maximum 10 points
- Discontinued/Clearance: maximum 20 points
- Grade A: maximum 12 points
- Grade B: maximum 8 points
- Maximum system score: 90 points

## 7. Authentication

All report and settings routes must require login.

Protected areas include:

- `/sales-dashboard`
- `/sales-kpi`
- `/sales-kpi/targets`
- Excel exports
- PDF exports
- DataTable endpoints

The login page reads credentials from environment variables:

```env
DASHBOARD_ADMIN_USER=admin
DASHBOARD_ADMIN_PASSWORD=admin
```

After successful login:

- An authenticated session is created.
- The user is redirected to the KPI dashboard.
- A logout action destroys the session.
- Invalid login attempts show a generic error without revealing which credential was wrong.

## 8. Error Handling

### Combined database connection failure

If either database fails:

```text
Laporan KPI gabungan tidak dapat dimuat.

Koneksi ke database AS_SMI gagal.
Data database lainnya tidak ditampilkan agar hasil gabungan tidak menyesatkan.
```

The technical exception is written to `storage/logs/laravel.log`.

### SQLite unavailable

If the local SQLite database or target table cannot be accessed:

```text
Pengaturan target belum dapat dibuka.
Database konfigurasi lokal tidak tersedia.
```

### Save validation failure

Examples:

- Non-numeric target
- Negative target
- Unknown or tampered `LiniPenjualan`
- Duplicate normalized line names in one request

No partial update is allowed.

## 9. Security Rules

- All target-setting actions require an authenticated session.
- POST requests require CSRF validation.
- Only known `LiniPenjualan` values from the generated page may be submitted.
- Credentials are not hardcoded in source files.
- Database passwords and application login passwords must not appear in logs or validation responses.
- Currency input is normalized on the server before persistence.

## 10. Testing Requirements

Automated tests must cover:

1. Login succeeds with valid environment credentials.
2. Login fails with invalid credentials.
3. Protected routes redirect unauthenticated users.
4. Current-month lines from both databases merge by normalized `LiniPenjualan`.
5. Existing SQLite targets remain visible without current-month transactions.
6. Manual addition of an unknown line is rejected.
7. Blank and zero target values save as `NULL`.
8. Multiple target changes save in one transaction.
9. One invalid row rolls back all submitted changes.
10. Dashboard reads targets from SQLite.
11. Missing targets produce `N/A` only for target-dependent KPI components.
12. Combined KPI stops if either SQL Server database fails.
13. Database selector is absent from the KPI page.
14. Exports use the same combined target values as the web dashboard.

## 11. Migration and Compatibility

- Existing `config/sales_kpi_targets.php` is no longer the primary data source.
- A one-time migration command should import existing configured targets into SQLite when the table is empty.
- The import must normalize names and avoid duplicates.
- Existing dashboards and export routes remain available but become protected by authentication.
- Transaction detail source labels remain available for tracing records to `AS_INGCO` or `AS_SMI`.

## 12. Success Criteria

The feature is complete when:

- An authenticated user can open `/sales-kpi/targets`.
- Current-month combined lines and saved inactive lines appear correctly.
- Targets can be edited inline and saved in one transaction.
- Dashboard KPI immediately uses saved SQLite targets.
- The KPI page always represents `AS_INGCO + AS_SMI`.
- No database selector remains on the KPI page.
- Missing targets are clearly identified by line name.
- Partial combined reports are never displayed after a database connection failure.
