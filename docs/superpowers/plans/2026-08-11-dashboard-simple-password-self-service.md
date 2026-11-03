# Dashboard Simple Password & Self-Service Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add configurable minimum-8-character dashboard passwords and authenticated self-service password changes while preserving the current approved device and revoking all other devices.

**Architecture:** Keep password policy in `config/dashboard_security.php`, reuse Laravel hashing and the current dashboard identity/device middleware, and add one focused request/controller/view pair for self-service changes. Reuse the existing activity audit middleware by mapping the password update route to `change_own_password`; never put password fields in audit metadata.

**Tech Stack:** Laravel 12, PHP 8.2+, Blade, Bootstrap 5, SQLite security database, Laravel Hash/validation.

## Global Constraints

- Default password minimum is exactly 8 characters and configurable by `DASHBOARD_PASSWORD_MIN_LENGTH`.
- No mandatory character composition rules.
- Current password is required for self-service changes.
- Current approved device/session stays active after a successful change.
- All other devices for that user become `revoked`.
- No database migration and no new Composer dependency.
- Password values must never be logged or added to audit metadata.
- Preserve all existing Sales Dashboard, Sales Insights, KPI, target, device approval, and audit behavior.

---

### Task 1: Password Policy Configuration and Existing Admin Flows

**Files:**
- Modify: `config/dashboard_security.php`
- Modify: `.env.example`
- Modify: `app/Http/Requests/Security/StoreDashboardUserRequest.php`
- Modify: `app/Http/Requests/Security/ResetDashboardUserPasswordRequest.php`
- Modify: `app/Console/Commands/InstallDashboardSecurity.php`
- Modify: `resources/views/security/users/index.blade.php`
- Test: `tests/manual_dashboard_password_self_service.php`

**Interfaces:**
- Consumes: `config('dashboard_security.password_min_length')`.
- Produces: one numeric password minimum used by all password entry flows.

- [ ] **Step 1: Write the failing manual contract test** asserting the config key, `.env.example`, request rules, installer usage, and dynamic HTML `minlength` exist.
- [ ] **Step 2: Run `php tests/manual_dashboard_password_self_service.php` and confirm it fails because the new policy is absent.**
- [ ] **Step 3: Add `password_min_length`, update both request rules to `Password::min(max(1, ...))` without composition requirements, update installer minimum-length validation/message, and update the administrator UI minlength.**
- [ ] **Step 4: Run the manual test again; remaining failures should only concern Task 2 self-service files.**

### Task 2: Self-Service Password Change

**Files:**
- Create: `app/Http/Requests/Security/ChangeOwnPasswordRequest.php`
- Create: `app/Http/Controllers/AccountPasswordController.php`
- Create: `resources/views/account/password.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `config/dashboard_security.php`
- Test: `tests/Feature/DashboardPasswordSelfServiceTest.php`
- Test: `tests/manual_dashboard_password_self_service.php`

**Interfaces:**
- `ChangeOwnPasswordRequest::rules(): array` validates `current_password`, `password`, and `password_confirmation`.
- `AccountPasswordController::edit(): View` renders `account.password`.
- `AccountPasswordController::update(ChangeOwnPasswordRequest $request): RedirectResponse` verifies current password, changes hash, revokes devices except session device, and redirects back with status.
- Produces routes `account.password.edit` and `account.password.update`.

- [ ] **Step 1: Extend the failing manual test with required controller/request/view/route/audit/header contracts and add real Laravel feature tests for success and validation behavior.**
- [ ] **Step 2: Run the manual test and confirm it fails because self-service components are absent.**
- [ ] **Step 3: Implement request, controller, routes, activity-audit mapping, view, and header link.**
- [ ] **Step 4: Run the manual test and confirm it passes. If dependencies are available, run `php artisan test --filter=DashboardPasswordSelfServiceTest`.**

### Task 3: Compatibility Fix, Documentation, and Packaging Verification

**Files:**
- Modify: `app/Http/Requests/SalesDashboardRequest.php`
- Modify: `docs/DASHBOARD-DEVICE-SECURITY.md`
- Create: `CARA_PASANG_PASSWORD_SELF_SERVICE.txt`
- Test: `tests/manual_dashboard_password_self_service.php`

**Interfaces:**
- `SalesDashboardRequest` remains extendable by `SalesDashboardDetailRequest`.
- Installation documentation tells local HTTP users to keep secure device cookies disabled and to clear Laravel caches after patching.

- [ ] **Step 1: Add the compatibility assertion to the manual test and run it to prove the existing `final` declaration fails.**
- [ ] **Step 2: Remove only `final` from `SalesDashboardRequest`; update security documentation and installation instructions.**
- [ ] **Step 3: Run all manual security/password/dashboard contract tests, PHP lint, JavaScript syntax checks, and package-sensitive-file scan.**
- [ ] **Step 4: Build patch and full-project ZIPs, run `zip -T`, and generate a verification report with the exact commands/results.**
