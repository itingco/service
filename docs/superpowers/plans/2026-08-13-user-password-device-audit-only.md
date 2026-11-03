# User/Password Login with Device Audit Only Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make username/password the only access gate while retaining device records exclusively for audit/history.

**Architecture:** Keep the current user, audit, device tables, and device correlation cookie. Change login and `dashboard.auth` middleware so device state never denies access, convert the device admin page to history-only, and stop password changes from revoking device records.

**Tech Stack:** Laravel 12, PHP 8.2, Eloquent, SQLite runtime security database, Blade.

## Global Constraints

- No new migration.
- No `.env` or `database/database.sqlite` in patch artifacts.
- `DASHBOARD_PASSWORD_MIN_LENGTH` remains default 8.
- Login throttling, user active checks, hashing, audit logging, and idle timeout remain enforced.
- Device status is not an authorization signal.

---

### Task 1: Authentication and session authorization

**Files:**
- Modify: `app/Http/Controllers/DashboardAuthController.php`
- Modify: `app/Http/Middleware/EnsureApprovedDashboardDevice.php`
- Modify: `app/Services/Security/DashboardDeviceService.php`
- Modify: `routes/web.php`
- Test: `tests/manual_dashboard_device_audit_only.php`

**Interfaces:**
- `DashboardDeviceService::registerOrResolve()` remains the login-time audit recorder.
- `dashboard.auth` remains the route middleware alias but no longer requires an approved device.

- [ ] Write a manual contract test that fails while pending flow/device approval is still required.
- [ ] Run the test and verify RED.
- [ ] Make `registerOrResolve()` create audit devices as approved-compatible records without using approval policy.
- [ ] Make successful password validation always activate a session after recording the device.
- [ ] Remove pending route dependency from login.
- [ ] Change auth middleware to validate session/user/idle timeout only and treat device as optional context.
- [ ] Run the test and verify GREEN.

### Task 2: Device history UI and password behavior

**Files:**
- Modify: `app/Http/Controllers/Security/SecurityDeviceController.php`
- Modify: `resources/views/security/devices/index.blade.php`
- Modify: `resources/views/auth/login.blade.php`
- Modify: `app/Http/Controllers/AccountPasswordController.php`
- Modify: `config/dashboard_security.php`
- Test: `tests/manual_dashboard_device_audit_only.php`

- [ ] Extend the failing contract test for history-only device UI and password behavior.
- [ ] Run and verify RED for the new assertions.
- [ ] Remove approve/reject/revoke UI and status filters/actions; retain rename.
- [ ] Update login copy to explain audit-only device recording.
- [ ] Stop own-password change from revoking device records.
- [ ] Remove obsolete audited approval/rejection/revocation route mappings.
- [ ] Run and verify GREEN.

### Task 3: Regression verification and packaging

**Files:**
- Modify: `tests/manual_dashboard_security.php` to reflect the new security contract.
- Create: `CARA_PASANG_PATCH_DEVICE_AUDIT_ONLY.txt`
- Create patch/full ZIP artifacts outside the project tree.

- [ ] Update security regression assertions so they require user/password, rate limiting, audit recording, and optional device context rather than approved-device authorization.
- [ ] Run all manual security/password/dashboard regression tests available in the package.
- [ ] PHP lint all application/config/routes/tests PHP files.
- [ ] Confirm no `.env`, `database/database.sqlite`, `vendor`, `.git`, or runtime SQLite sidecars in artifacts.
- [ ] Test both ZIP archives with `zip -T`.
