# Library Aide Workspace Design

**Status:** Approved for implementation

**Date:** 2026-11-10

## Goal

Add a dedicated Library Aide workspace backed by a seeded `LIBRARY001` account while keeping `ADMIN001` as the full system-owner account. `LIBRARY001` remains an actual `admin` database role for staff compatibility, but receives a narrower `library_aide` access profile enforced by the backend.

## Current context

Scan2Borrow already has `admin` and `librarian` roles, a shared staff shell, staff circulation, borrowers, inventory, reports, notifications, and an Admin-only staff-management surface. The current Admin UI can promote borrower accounts to librarian or administrator, manage staff accounts, manage courses, and review profile changes. Role-only authorization cannot express the requested distinction because the Library Aide account must use the `admin` role while being unable to perform system-administration actions.

The repository is a framework-free PHP modular monolith with vanilla HTML/CSS/JavaScript frontend pages, MySQL-compatible SQL scripts, PHPUnit/PHPStan backend checks, and Node native-module frontend tests. Existing uncommitted changes in the user’s main checkout are outside this feature and must remain untouched.

## Approved access model

Add a nullable staff access profile to `users`:

- `system_admin`: full Admin workspace and all current Admin-only actions.
- `library_aide`: operational staff workspace only.
- `NULL`: non-staff accounts; current borrower role rules remain unchanged.

The database `role` remains authoritative for broad staff access:

- `ADMIN001`: `role = 'admin'`, `access_profile = 'system_admin'`.
- `LIBRARY001`: `role = 'admin'`, `access_profile = 'library_aide'`.
- Existing administrator accounts receive `system_admin` during the upgrade so their current behavior is preserved.

The seeded Library Aide account is:

- Barcode: `LIBRARY001`
- Display name: `Library Aide`
- Role: `admin`
- Access profile: `library_aide`
- Initial password: `library123`, documented as temporary and required to be changed after first login.

Do not hardcode `LIBRARY001` in authorization logic. The session identity must carry the profile loaded from the account record, and authorization must use profile policy methods.

## Authorization behavior

Introduce a focused staff access policy that distinguishes:

- `isStaff`: active `admin` or `librarian` account.
- `isSystemAdmin`: active `admin` account with `system_admin` profile.
- `isLibraryAide`: active `admin` account with `library_aide` profile.

All existing staff operational endpoints remain available to the aide profile when their current behavior is appropriate:

- Staff dashboard data
- Circulation lookup, borrow, return, and approval workflows
- Borrower search, details, and staff notifications
- Inventory listing, catalog edits, quantity updates, and copy maintenance that are reversible
- Reports, report export, and overdue views
- Staff notifications and notification actions

The aide profile must receive a server-side `403` or the project’s existing unauthorized response for all of the following, regardless of the URL or client used:

- Staff listing and staff-account actions: promote, demote, reset password, enable, disable, or otherwise change staff account state
- Student/teacher-to-staff role changes
- Profile-change request listing, approval, and rejection
- Course management
- API documentation pages and API documentation endpoints
- Permanently deleting or archiving catalog titles or physical copies
- Any other endpoint that changes a role, permission, password, staff status, or irreversible inventory state

The frontend must hide these controls for aides, but backend policy enforcement is the security boundary. Existing `ADMIN001` behavior must remain unchanged.

## Login and routing

Staff login continues to accept barcode and password. After authentication:

- `system_admin` redirects to the existing `/staff/dashboard` surface.
- `library_aide` redirects to a new aide dashboard route under `/staff/aide/dashboard`.

The page authorizer and API controllers must enforce the profile for aide and system-admin routes. Direct navigation to `/admin/staff`, `/admin/api-docs`, or any blocked API from `LIBRARY001` must not expose content or mutate state.

## Aide workspace UI

### Visual direction

Use the Swiss anchor already present in the project’s circulation and copy-history surfaces:

- Surface: `#F7F7F8` and `#FFFFFF`.
- Accent: Yves Klein blue `#002FA7`.
- Ink: near-black `#101114`.
- Muted text: `#62666F`.
- Structure: visible 1px rules, compact data tables, left-aligned type, asymmetric balance, and tabular numerals.
- Typography: Helvetica Neue with a robust system fallback.
- Avoid decorative gradients, noisy textures, fake operational metrics, and invented user data.

### Navigation

The aide shell exposes only:

1. Dashboard
2. Circulation
3. Borrowers
4. Inventory
5. Reports
6. Notifications
7. Logout

System-administration navigation such as Staff Management, College Settings, API Docs, and profile approvals is not rendered for aides.

### Dashboard composition

The new aide dashboard provides:

- A prominent `Start circulation` action.
- A persistent scan lane with the sequence `Borrower → Copies → Confirm`.
- Live counts for pending requests, overdue loans, available copies, and unread notifications.
- A `Today at the desk` activity list using real API data and explicit empty/error states.
- Quick links into inventory, reports, and notification review.

The scan lane is the workspace differentiator: it keeps the aide in a short, keyboard-friendly borrower/book confirmation loop without requiring navigation through system-administration screens.

### Reuse boundaries

Reuse the existing staff services, controllers, API contracts, circulation components, inventory workflows, report data, and notification behavior wherever they already satisfy the approved aide scope. Add a new aide shell, dashboard, profile-aware navigation, and scoped styles rather than duplicating business logic.

## Data and migration flow

1. Add the access-profile column and an idempotent upgrade script.
2. Update the canonical installer and legacy database seed to set profiles for `ADMIN001` and `LIBRARY001`.
3. Preserve existing administrator behavior by assigning `system_admin` to existing admin rows during upgrade.
4. Load the profile in the user repository and authentication result.
5. Store the profile in the session principal.
6. Use the policy at page, controller, and destructive inventory boundaries.
7. Return the profile in the existing session identity payload so the frontend can choose the correct shell and navigation.

The migration must be safe to run once on an existing database and must not recreate or erase user data. The installer must include the seeded account for fresh databases.

## Error handling and security

- Missing or unknown profiles fail closed for privileged actions; existing admin accounts are normalized to `system_admin` by the migration.
- Aide-denied pages and APIs use the project’s existing unauthorized response shape and status conventions.
- CSRF validation remains required for every state-changing request.
- Aide UI actions use the existing confirmation service for irreversible or consequential operations that remain allowed.
- No authorization decision may rely only on a hidden or disabled frontend control.
- The seeded password is temporary and must be changed after first login.

## Testing strategy

Add tests before implementation for the new behavior:

- Domain/policy tests for `system_admin`, `library_aide`, missing profile, and non-staff accounts.
- Authentication/session tests proving the profile survives login and is returned by the session endpoint.
- Route and controller tests proving `LIBRARY001` can use operational staff endpoints but receives the unauthorized response for staff management, profile approvals, course management, API docs, and destructive inventory actions.
- SQL/schema contract tests for the new column, installer seed, idempotent upgrade, and both seeded accounts.
- Frontend contract tests for aide route registration, profile-aware redirect, navigation items, scan-lane mounts, and absence of system-admin actions.
- Regression tests proving `ADMIN001` retains current Admin navigation and operations.

The completion gate is:

```text
npm test
C:\Users\Adriaan M. Dimate\AppData\Local\Programs\pharmacy-backend-toolchain\php-8.5.12\php.exe C:\Users\Adriaan M. Dimate\AppData\Local\Programs\pharmacy-backend-toolchain\composer.phar validate --working-dir=backend --no-check-publish
C:\Users\Adriaan M. Dimate\AppData\Local\Programs\pharmacy-backend-toolchain\php-8.5.12\php.exe C:\Users\Adriaan M. Dimate\AppData\Local\Programs\pharmacy-backend-toolchain\composer.phar run quality --working-dir=backend
```

The pre-feature branch currently has unrelated backend baseline failures that must be recorded separately from feature regressions. No existing test coverage may be removed or weakened.

## Out of scope

- Replacing the existing `admin` and `librarian` roles.
- Adding a user-facing permissions editor.
- Changing borrower registration or borrower self-service behavior.
- Making the aide account a new role with a separate login flow.
- Automatically rotating or distributing the seeded password beyond the documented temporary value.
