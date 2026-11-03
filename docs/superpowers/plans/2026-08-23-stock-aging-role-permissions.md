# Stock Aging & Dynamic Role Permissions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add dynamic per-menu roles, remove manual device naming at login, and add a combined INGCO+SMI Stock Aging dashboard with warehouse/source subtotals and MTD sales matching.

**Architecture:** Preserve `dashboard_users.role` for compatibility while adding role/menu mapping tables and a permission middleware/service. Stock Aging uses a new SQL Server stock data source plus the existing sales data source, normalizes rows, matches sales by source + ItemID + WarehouseID, aggregates by source/warehouse/age bucket, and renders compact summary/detail views.

**Tech Stack:** PHP 8.2+, Laravel 12, SQLite runtime security DB, SQL Server AS_INGCO/AS_SMI, Bootstrap 5 Blade.

**Spec:** `docs/superpowers/specs/2026-08-23-stock-aging-role-permissions-design.md`

## Global Constraints

- Use the user-provided ZIP as the source of truth; do not pull implementation files from GitHub.
- Do not overwrite or recreate `database/database.sqlite`.
- Do not commit `.env` or expose credentials.
- Administrator (`admin`) always has full access and cannot lose menu permissions.
- Permission granularity is menu-level only.
- Stock and sales match only inside the same source DB by ItemID + WarehouseID.
- AS_INGCO and AS_SMI warehouses are always displayed separately.

---

### Task 1: Remove manual device label from login

**Files:**
- Modify: `resources/views/auth/login.blade.php`
- Modify: `app/Http/Requests/DashboardLoginRequest.php`
- Modify: `app/Http/Controllers/DashboardAuthController.php`

**Interfaces:**
- Consumes: existing audit-only `DashboardDeviceService::registerOrResolve()`.
- Produces: username/password-only login while automatic device audit remains active.

- [ ] Add a regression test/verification that login no longer expects `device_label`.
- [ ] Remove the field and request rule.
- [ ] Stop passing/retaining `device_label` in controller errors/device registration.
- [ ] Run PHP syntax checks and source assertions.

### Task 2: Add role/menu permission persistence and policy

**Files:**
- Create: `database/migrations/2026_08_23_000130_create_dashboard_roles_and_menus.php`
- Create: `app/Models/DashboardRole.php`
- Create: `app/Models/DashboardRoleMenu.php`
- Create: `app/Support/DashboardMenuPolicy.php`
- Create: `app/Services/Security/DashboardPermissionService.php`
- Modify: `config/dashboard_security.php`

**Interfaces:**
- Produces: `DashboardPermissionService::can(DashboardUser $user,string $menuKey): bool`, `menuAccess()`, `roleOptions()`, `roleLabel()`, and `landingRoute()`.

- [ ] Write policy tests for administrator override and normal menu grants.
- [ ] Verify tests fail because policy does not exist.
- [ ] Implement policy, models, migration, menu catalog/default legacy mapping, and permission service.
- [ ] Re-run logic checks and PHP syntax checks.

### Task 3: Replace hard-coded role routing and navigation

**Files:**
- Create: `app/Http/Middleware/EnsureDashboardPermission.php`
- Create: `app/Http/Controllers/DashboardHomeController.php`
- Modify: `app/Http/Kernel.php`
- Modify: `app/Http/Middleware/EnsureApprovedDashboardDevice.php`
- Modify: `app/Http/Controllers/DashboardAuthController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/app.blade.php`

**Interfaces:**
- Consumes: permission service from Task 2.
- Produces: `dashboard.permission:<menu_key>` route enforcement and dynamic menu rendering.

- [ ] Add route/navigation source assertions.
- [ ] Implement permission middleware, menu access sharing, landing redirect, and permission-protected routes.
- [ ] Verify no active application route uses hard-coded `dashboard.role`.
- [ ] Run PHP syntax checks.

### Task 4: Add Role & Permission management UI

**Files:**
- Create: `app/Http/Controllers/Security/SecurityRoleController.php`
- Create: `app/Http/Requests/Security/StoreDashboardRoleRequest.php`
- Create: `app/Http/Requests/Security/UpdateDashboardRoleRequest.php`
- Create: `resources/views/security/roles/index.blade.php`
- Modify: `app/Http/Controllers/Security/SecurityUserController.php`
- Modify: `app/Http/Requests/Security/StoreDashboardUserRequest.php`
- Modify: `app/Http/Requests/Security/UpdateDashboardUserRequest.php`
- Modify: `resources/views/security/users/index.blade.php`
- Modify: `routes/web.php`
- Modify: `config/dashboard_security.php`

**Interfaces:**
- Produces: create role, rename/sync non-system role permissions, dynamic role choices for users.

- [ ] Add validation/source assertions for role existence and protected admin behavior.
- [ ] Implement controller/requests/view and user-role integration.
- [ ] Ensure non-admin cannot assign/remove Administrator role even when granted User Management.
- [ ] Run PHP syntax checks.

### Task 5: Add Stock Aging normalization and aggregation logic

**Files:**
- Create: `app/Contracts/StockAgingDataSource.php`
- Create: `app/Exceptions/StockAgingDataException.php`
- Create: `app/Data/StockAgingFilter.php`
- Create: `app/Data/StockAgingReport.php`
- Create: `app/Support/StockAgingRowNormalizer.php`
- Create: `app/Support/StockAgingSalesRowNormalizer.php`
- Create: `app/Services/SqlServerStockAgingDataSource.php`
- Create: `app/Services/StockAgingService.php`
- Create: `config/stock_aging.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Interfaces:**
- `StockAgingDataSource::fetch(string $source,DateTimeImmutable $date): array`
- `StockAgingService::generate(StockAgingFilter $filter): StockAgingReport`

- [ ] Write pure logic tests for bucket assignment, status filter, source isolation, sales-return subtraction, warehouse subtotal, source subtotal, and grand total.
- [ ] Verify tests fail because classes do not exist.
- [ ] Implement filters/normalizers/data source/service/DTO/config.
- [ ] Verify pure logic checks pass and PHP syntax checks succeed.

### Task 6: Add Stock Aging controllers and views

**Files:**
- Create: `app/Http/Requests/StockAgingRequest.php`
- Create: `app/Http/Requests/StockAgingDetailRequest.php`
- Create: `app/Http/Controllers/StockAgingController.php`
- Create: `app/Services/StockAgingDetailQueryService.php`
- Create: `resources/views/stock-aging/index.blade.php`
- Create: `resources/views/stock-aging/details.blade.php`
- Modify: `routes/web.php`
- Modify: `config/dashboard_security.php`

**Interfaces:**
- Produces Stock Aging summary and item-detail endpoints protected by `stock_aging` permission.

- [ ] Add request/view/route source assertions.
- [ ] Implement date/status filters, KPI cards, source/warehouse/bucket hierarchy, warehouse/source/grand totals, and detail pagination.
- [ ] Omit empty buckets and provide detail links preserving filter state.
- [ ] Run PHP syntax and Blade source checks.

### Task 7: Installer compatibility, verification, and handoff package

**Files:**
- Modify: `app/Console/Commands/InstallDashboardSecurity.php`
- Create: `CARA_PASANG_STOCK_AGING_ROLE_PERMISSION.txt`

**Interfaces:**
- Produces safe deployment instructions without replacing runtime SQLite.

- [ ] Ensure installer wording reflects audit-only devices and migrations create role tables.
- [ ] Run `php -l` over all changed PHP files.
- [ ] Run available standalone logic verifications; document Laravel/PHPUnit limitation if local PHP extensions are missing.
- [ ] Confirm `.env` and runtime SQLite are not introduced into the patch diff.
- [ ] Build a clean replacement ZIP from the modified baseline.
