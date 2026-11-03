# Stock Aging & Dynamic Role Permissions Design

## Scope

Baseline is the user-provided `sales-report-web.zip` dated 2026-08-23. This change must preserve existing Sales Dashboard/KPI/Insights behavior and SQLite runtime data.

## Authentication / Device Audit

- Login form contains only username and password.
- Device detection remains audit-only and never blocks valid login.
- Device label is generated automatically from platform/browser when a new browser token is created.
- Existing device/audit tables remain compatible.

## Dynamic Role / Menu Permissions

- Permission granularity is menu-level only.
- `admin` / Administrator is a protected system super-admin and always has all menu permissions regardless of database rows.
- Existing `dashboard_users.role` string remains the user-to-role link for backward compatibility.
- New SQLite tables:
  - `dashboard_roles(role_key, name, is_system, timestamps)`
  - `dashboard_role_menus(role_key, menu_key, timestamps)`
- Existing roles are seeded/migrated with their current access:
  - admin: all menus by super-admin override
  - direksi/sales/rnd: Dashboard Penjualan, Dashboard KPI, Sales Insights & R&D
  - viewer: Dashboard Penjualan, Dashboard KPI
- New Stock Aging is initially Administrator-only until explicitly granted to another role.
- Menu catalog:
  - `sales_dashboard`
  - `sales_kpi`
  - `sales_insights`
  - `stock_aging`
  - `kpi_targets`
  - `security_users`
  - `security_roles`
  - `security_devices`
  - `security_audits`
- Password change and logout are always available after login.
- Routes enforce permissions server-side; hiding menu links is not the security boundary.
- Non-admin users who are granted Security Users cannot assign or remove the Administrator role. Administrator role permissions cannot be edited.
- Dashboard landing route redirects to the first menu the current user may access.

## Stock Aging Data Sources

Two independent sources are always loaded:

- AS_INGCO via `sqlsrv_ingco`
- AS_SMI via `sqlsrv_smi`

Each database must expose:

- `dbo.USP_Dashboard_SisaStokWarehouse @PerDate`
- the latest `dbo.fn_PenjualanLini(@date1,@date2)` including `ItemID` and `WarehouseID`

Physical warehouses are never auto-merged across databases. Identity is:

`SourceDB + WarehouseID + ItemID`

Warehouse names are presentation only and are not used for matching.

## Stock Aging Filter

- Report date, default today.
- Item status: All / Normal / Discontinued / Clearance / Discontinued + Clearance.
- No warehouse picker: all warehouses returned by each source are displayed.
- Sales MTD range: first day of selected month through selected report date.

## Aging Buckets

- 0-30 days
- 31-60 days
- 61-90 days
- 91-120 days
- >120 days

Stock quantity is `Smallest On Hand`.
Sisa Stok is `Smallest On Hand * Price KHUSUS-1` (prefer the USP-provided Sisa Stok value when present).

Sales MTD is summed from `fn_PenjualanLini` by ItemID + WarehouseID inside the same source database. Sales Returns remain negative because the sales function already normalizes LineTotal sign.

## Aggregation / UI

Hierarchy:

1. Source database (AS_INGCO / AS_SMI)
2. Warehouse
3. Age bucket
4. Item details

Each bucket shows:

- distinct items
- Qty Sisa
- Sisa Stok
- Sales MTD
- Sales / Sisa Stok %

Each warehouse has a subtotal. Each source has a subtotal. The report has a grand total. Empty buckets are omitted.

Top KPI cards:

- Total Sisa Stok
- Sales MTD for stock items
- Sisa Stok >120 days
- Total distinct stock rows/items across source + warehouse identity

Main page stays compact. Bucket rows link to a separate item-detail view.

## Error Handling / Performance

- Never silently show a partial combined result if either INGCO or SMI fails; show a friendly error naming failed source(s).
- Cache raw stock and MTD sales reads for a short period (default 300 seconds) keyed by source/date to reduce repeated heavy SQL execution.
- Filters/aggregation run in PHP over normalized cached rows.
