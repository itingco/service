# Dashboard Device Security Design

## Goal

Protect Sales Dashboard with personal user accounts, approved-device access, role-based administration, login/activity audits, rate-limited login, and session controls while preserving existing sales routes and reports.

## Security model

- A user must authenticate with a personal username and password stored as a Laravel password hash.
- A browser receives a cryptographically random device token in an HttpOnly cookie.
- Only the token hash is stored in SQLite.
- New devices are registered as pending and require admin approval.
- The first device used by the first active admin may be auto-approved to avoid an installation deadlock.
- Every protected request validates both the authenticated user and the approved device.
- MAC address and Windows hostname are not used because a normal browser cannot reliably provide them.
- Device labels are user/admin-defined and accompanied by browser, platform, IP, first-seen, and last-seen metadata.

## Roles

- admin: full dashboard access and security administration.
- direksi: all report and export access.
- sales: sales dashboard, sales insights, KPI, and exports.
- rnd: sales insights and R&D analysis plus read-only dashboard access.
- viewer: read-only report access without target management.

## Audit coverage

- Login success, failure, pending-device registration, logout, device rejection, and device revocation.
- Dashboard page views, detail endpoints, exports, target updates, and security administration.
- Password values and device secrets are never written to logs.

## Persistence

Security tables use the existing `sqlite_targets` connection and `database/database.sqlite` file so deployment does not introduce another writable SQLite path.

## Administration

- `/security/users`: create users, update role/status, and reset passwords.
- `/security/devices`: approve, reject, revoke, and label devices.
- `/security/audits`: view recent login and activity audit events.

## Installation

Run `php artisan dashboard-security:install` after migrations. The command creates/updates the initial admin using `DASHBOARD_ADMIN_USER`, `DASHBOARD_ADMIN_PASSWORD`, and `DASHBOARD_ADMIN_NAME`.
