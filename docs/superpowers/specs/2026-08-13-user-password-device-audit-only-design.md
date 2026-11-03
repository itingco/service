# User/Password Login with Device Audit Only — Design

## Goal

Simplify Sales Dashboard access so login is controlled only by a valid active user account and password. Device information remains recorded for audit/history but never blocks a successful login.

## Authentication behavior

- A valid active username/password immediately creates an authenticated dashboard session.
- Existing login throttling remains active.
- Existing password hashing/rehash behavior remains active.
- Device approval status (`pending`, `approved`, `rejected`, `revoked`) is no longer consulted for login or session authorization.
- The `/device/pending` approval flow is removed from normal routing and is no longer reachable from login.

## Device audit behavior

- Every successful login calls the existing device registration/resolution service.
- Device/browser records remain in `dashboard_devices` and continue to capture label, browser, platform, first/last IP, user agent, first seen, and last seen.
- A device cookie may still be used only to correlate repeat logins from the same browser profile; loss of that cookie does not block access and simply creates/resolves an audit record on next login.
- New device records are stored as `approved` for compatibility with the existing schema, but that value has no authorization meaning.
- `/security/devices` becomes a history/inventory page. Admin may rename labels, but there are no approve/reject/revoke controls.

## Session authorization

- `dashboard.auth` validates only: authenticated session flag, valid active user, and idle timeout.
- A missing, stale, revoked, or changed device record never invalidates the session.
- If a session has a device id, middleware may load it for view/audit context and refresh `last_seen_at`/`last_ip`; if absent, session remains valid.

## Password self-service

- Existing minimum password length remains configurable with `DASHBOARD_PASSWORD_MIN_LENGTH`, default 8.
- Password changes no longer revoke other device records because device state is audit-only.
- Password change continues to require the old password and retains the current authenticated session.

## UI

- Login copy says device/browser information is recorded for audit, not approval.
- Security → Devices is labeled as device history and shows `TERCATAT` rather than authorization state.
- Pending-device counters and approval actions are removed.

## Compatibility

- No migration and no destructive database change.
- Existing `dashboard_devices.status`, approval columns, historical pending/approved data, and old pending view may remain in the codebase/database but are not used for access decisions.
- Existing audit and activity logging remains intact.
