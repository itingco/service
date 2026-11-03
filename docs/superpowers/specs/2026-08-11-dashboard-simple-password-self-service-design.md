# Dashboard Simple Password & Self-Service Design

## Goal

Make dashboard passwords easier to manage while retaining the existing approved-device security model. Every dashboard password uses a configurable minimum length of 8 characters with no mandatory uppercase/lowercase/number/symbol composition. Each authenticated user can change their own password after confirming the current password.

## Approved Behavior

- Password minimum is controlled by `DASHBOARD_PASSWORD_MIN_LENGTH`, default `8`.
- Password composition is unrestricted beyond the configured minimum length.
- The rule applies to initial administrator installation/reset, creating a user, administrator password reset, and self-service password changes.
- Self-service form fields: current password, new password, new password confirmation.
- Current password must match the authenticated user's stored hash.
- New password is stored only with Laravel `Hash::make()`.
- After a successful self-service change, the current approved device and current session remain valid.
- Every other device belonging to that user is changed to `revoked`; its approval metadata is cleared.
- The password change is recorded through the existing dashboard activity audit as `change_own_password`. Password values must never be placed in audit metadata.
- No new database migration or dependency is required.
- Administrator reset remains available and follows the same configurable minimum length.
- The existing `SalesDashboardDetailRequest extends SalesDashboardRequest` compatibility bug is included in the patch by ensuring `SalesDashboardRequest` is not `final`.

## UI

Add a `Ganti Password` button to the authenticated header near the user identity/logout controls. It opens `/account/password`. The page uses the existing Bootstrap dashboard layout and contains a compact card with current password, new password, confirmation, validation messages, and a note that other devices will be revoked.

## Routes and Components

- `GET /account/password` → `AccountPasswordController@edit`, route `account.password.edit`.
- `PUT /account/password` → `AccountPasswordController@update`, route `account.password.update`.
- Both routes live inside existing `dashboard.auth` and `dashboard.audit` middleware so only an active user on an approved device can use them.
- `ChangeOwnPasswordRequest` owns validation.
- `AccountPasswordController` owns password verification/update and revocation of other devices.

## Error Handling

- Wrong current password returns a validation error on `current_password` and does not modify the password or devices.
- New password shorter than configured minimum returns validation error.
- Confirmation mismatch returns validation error.
- Missing authenticated user/device state returns the normal security middleware behavior before the controller is reached.

## Verification

- Static/manual contract test proves routes, config, validation, controller behavior contract, audit mapping, view, and non-final `SalesDashboardRequest` are present.
- Laravel feature tests cover successful self-change, wrong current password, short password, current device retention, other-device revocation, and page accessibility for a non-admin role.
- Existing PHP files are linted.
