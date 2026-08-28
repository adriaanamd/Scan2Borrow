# Scan2Borrow Frontend/Backend Refactor Design

## Goal

Split the existing Scan2Borrow PHP/MySQL application into a vanilla `frontend/` and an OOP PHP modular monolith under `backend/`, while preserving the current UI, UX, routes by behavior, authentication, authorization, data rules, integrations, and operational workflows.

The migration is behavior-preserving. Visual cleanup, copy changes, feature changes, and database redesign are out of scope until parity is proven.

## Non-negotiable constraints

- Frontend technology is vanilla HTML, CSS, and JavaScript only.
- Backend technology is PHP 8.3+ target code, MySQL, PDO, and no PHP framework.
- The current XAMPP CLI reports PHP 8.2.12; PHP 8.3+ is the target/runtime requirement, and the implementation setup will fail clearly when the required runtime/toolchain is unavailable. Where practical, source syntax will remain compatible with 8.2 so local syntax checks can still run during the transition.
- All new PHP application code uses `declare(strict_types=1)`, PSR-12 formatting, complete type declarations, readonly properties where appropriate, typed DTOs/value objects, dependency injection, and classes organized by responsibility.
- Thin entry scripts may bootstrap and dispatch the application; they contain no business logic or free-standing application functions.
- All state-changing requests retain CSRF protection, server-side validation, prepared statements, authorization checks, and audit logging where the current behavior has it.
- Protected pages and APIs are inaccessible without the required session and role, even when their URL is typed directly.
- Existing CSS, markup structure, copy, emoji/icons, dimensions, Bootstrap classes, CDN versions, print styles, modals, drawers, toasts, scanner flows, and responsive behavior are treated as compatibility requirements.
- The existing database schema and upgrade behavior are retained unless a compatibility-preserving migration is required by the new structure.
- PHPMailer remains an integration dependency; its vendored source is not rewritten as part of this refactor.
- Every production behavior change follows TDD: write a failing test, observe the expected failure, implement the smallest passing change, rerun the full relevant suite, then refactor without changing behavior.

## Current application inventory

The current checkout contains 37 root PHP files, six shared/config/helper PHP files, the vendored PHPMailer library, one global stylesheet, two JavaScript modules, and one SVG asset. There are 31 UI page surfaces and four root-level API/diagnostic/utility surfaces.

### UI surfaces to migrate

- Public/auth: student login and registration modals (`index.php`), staff login, borrower registration, borrower OTP verification, guest registration, guest OTP verification.
- Staff: dashboard, inventory, borrowers, borrower detail, overdue books, reports, notifications, staff management, and guest request review.
- Borrower: student dashboard, teacher dashboard, book search, borrowing history, settings, and receipts.
- Guest: dashboard, profile, profile OTP verification, browse books, borrowed books, history, borrow request, return request, government ID pass, and receipt.

### Non-page behavior to preserve

- `books_api.php`: inventory list/search/filter/sort/pagination, create/update, cover upload, archive, restore, bulk actions, permanent delete, duplicate checks, keyword updates, and current JSON response behavior.
- `api_notifications.php`: pending approval polling, return notifications, borrow notifications, marking notifications viewed, and dashboard HTML/notification payload compatibility. The legacy file currently has a pre-existing parse error caused by concatenated partial implementations; the new module must implement the intended caller contract cleanly.
- `toggle_borrowing.php`: staff-controlled borrower borrowing status mutation.
- `photo_check.php`: diagnostic checks for photo support, database connectivity, photo column size, and read/write behavior.
- `send_due_reminders.php`: scheduled due-date SMS processing.
- `logout.php`: session termination and redirect behavior.

## Target architecture

```text
scan2borrow/
├── frontend/
│   ├── pages/                         # static HTML only
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   └── shared/                       # static fragments and browser utilities
├── backend/
│   ├── public/
│   │   └── index.php                 # bootstrap + dispatch only
│   ├── src/
│   │   ├── Domain/
│   │   │   ├── Auth/
│   │   │   ├── Book/
│   │   │   ├── Borrowing/
│   │   │   ├── Guest/
│   │   │   ├── Notification/
│   │   │   ├── Report/
│   │   │   └── User/
│   │   ├── Application/
│   │   │   ├── DTO/
│   │   │   ├── Services/
│   │   │   └── Validators/
│   │   ├── Infrastructure/
│   │   │   ├── Database/
│   │   │   ├── Persistence/
│   │   │   ├── Mail/
│   │   │   ├── Sms/
│   │   │   └── Uploads/
│   │   └── Http/
│   │       ├── Controllers/
│   │       ├── Middleware/
│   │       ├── Requests/
│   │       ├── Responses/
│   │       └── Routing/
│   ├── tests/
│   │   ├── Unit/
│   │   ├── Feature/
│   │   └── Fixtures/
│   ├── composer.json                 # dev tooling and autoload only
│   ├── phpunit.xml
│   └── phpstan.neon
├── uploads/
├── .htaccess
└── sql/
```

This is one deployable modular monolith, not microservices. Domain modules own their rules; application services coordinate use cases; repositories isolate PDO; HTTP controllers translate requests to DTOs and responses; infrastructure adapters handle external systems. Modules communicate through typed services and interfaces rather than reaching into each other's SQL or global state.

## URL and access-control design

Apache will expose only clean routes and approved public assets. Direct requests to `frontend/pages`, `backend/src`, tests, configuration, and other source directories will be denied. The root rewrite file will route clean page requests and `/api/...` requests to the backend front controller.

Examples:

```text
/scan2borrow/                  → public student-login page
/scan2borrow/staff-login       → staff-login page
/scan2borrow/register          → registration page
/scan2borrow/student/dashboard → borrower page, student/teacher guard
/scan2borrow/staff/dashboard   → staff page, admin/librarian guard
/scan2borrow/inventory         → staff page, admin/librarian guard
/scan2borrow/api/auth/session  → session endpoint
/scan2borrow/api/books         → book endpoints
```

Page delivery is server-gated. A page request is resolved by a typed page route definition containing the page file and required access policy. The authorization middleware runs before the backend reads and streams the static HTML. Unauthenticated users receive the same role-appropriate login redirect behavior as the current application; authenticated users with the wrong role receive the same home redirect behavior. The browser-side session guard runs after delivery as a defense-in-depth check and handles expired sessions during an open page.

The API uses same-origin session cookies with `credentials: 'same-origin'`. A session endpoint returns the current user/visitor identity, role, display data, and CSRF token. API authorization is enforced independently for every route; hiding a link or page is never treated as authorization.

## Authentication and authorization

The current flows remain separate:

- students and teachers log in with a barcode;
- staff log in with a barcode and password;
- guest visitors register, verify by SMS OTP, and receive a visitor session;
- admin, librarian, student, teacher, and guest policies remain distinct;
- login-attempt counters, lockouts, last-login updates, session regeneration, role home routing, and logout are retained;
- password hashes remain one-way and are verified with PHP password APIs;
- CSRF token issuance and validation are centralized in a typed service;
- protected API requests reject missing/invalid sessions, missing/invalid CSRF on mutation, and insufficient roles with consistent JSON errors.

The session abstraction wraps PHP's session functions so controllers and services do not use `$_SESSION` directly. A request context carries the authenticated principal. A policy middleware checks the principal before invoking a controller. Guest identity is separate from staff/borrower identity, matching the current session model.

## Frontend parity design

Static HTML pages will be created from the current rendered structure. PHP interpolation becomes explicit data slots, `data-*` attributes, or API-rendered DOM created by class-based JavaScript modules. The global stylesheet is copied without visual redesign; inline page styles are moved to page-scoped CSS without changing declarations or specificity outcomes.

The browser layer is organized into small vanilla classes such as `ApiClient`, `SessionGuard`, `CameraCapture`, `BarcodeScanner`, `InventoryController`, `DashboardNotifications`, `BorrowingController`, `GuestPortalController`, `ReceiptController`, and `ReportController`. They own event binding and rendering for one surface, use the API client for requests, and preserve existing DOM IDs and user-visible state transitions.

The parity matrix will map every legacy file to a clean route, static page, JS controller, API use cases, assets, guards, forms, and visual reference. Migration is not complete for a surface until:

1. the static page contains the same visible structure and copy;
2. the same inputs, buttons, links, modals, drawers, alerts, toasts, print behavior, and camera/scanner affordances exist;
3. the same successful and failed flows are covered by tests;
4. a browser check confirms the page is blocked when unauthenticated and renders correctly for each permitted role;
5. screenshot comparison finds no intentional visual drift.

## API and data contracts

Controllers return a consistent JSON envelope for new endpoints:

```json
{
  "ok": true,
  "data": {},
  "message": null,
  "errors": []
}
```

Compatibility adapters retain the current keys where existing browser behavior depends on them, including inventory responses (`ok`, `data`, `total`, `page`, `pages`), notification responses (`success`, `count`, `html`, `notifications`), and existing user-facing messages. FormData remains supported for camera images and cover uploads. Dates, fine calculations, approval states, status labels, and receipt fields retain current formatting and policy values.

Repositories use parameterized SQL only. Dynamic sorting, filtering, pagination, and `IN` clauses use explicit allowlists and generated placeholders. Transactions cover multi-step borrow, return, approval, guest release, registration, OTP verification, and notification side effects where the current behavior requires atomicity.

## Testing and quality gates

The new backend uses PHPUnit unit and feature tests plus focused contract tests for the API. Test fixtures cover each role and relevant database state. Browser parity checks cover clean-route access and representative interaction flows; they do not replace backend tests.

Each implementation slice follows this exact sequence:

1. write one focused failing test;
2. run it and record the expected failure;
3. implement the smallest typed OOP code;
4. run the focused test and the relevant suite;
5. refactor only while green;
6. run PHPStan level 9 and syntax checks for the touched area;
7. commit the slice.

The migration targets 72 meaningful commits, grouped into setup, security/routing, domain modules, API contracts, frontend pages, parity verification, and cutover. Commit count is not achieved by empty commits: every commit contains a testable behavior or a required isolated migration boundary.

Before final handoff:

- `C:\xampp\php\php.exe` syntax-checks all new PHP code;
- the PHP 8.3+ runtime requirement is verified separately from the current local PHP 8.2.12 compatibility check;
- PHPUnit passes with no failures, warnings, or risky tests;
- PHPStan runs at level 9 with no errors;
- the parity matrix has no unchecked surface;
- direct protected URL requests redirect or return unauthorized before HTML delivery;
- public routes remain reachable;
- browser checks cover all role-specific dashboards, auth, inventory, borrowing, guest, receipt, notification, and reporting flows;
- no old application source is deleted until the new route has passed parity verification.

## Migration and rollback

The legacy files remain available as a comparison/reference implementation during the migration. New clean routes are introduced behind the new gateway. Each module can be verified independently while the database schema and uploads remain compatible. Cutover changes the root route mapping only after all parity gates pass. If a migrated surface fails verification, its clean route can be mapped back to the legacy page while the new module is corrected; no destructive database reset or source deletion is part of the migration.

## Known baseline issue

The clean baseline syntax check currently fails only for the legacy `api_notifications.php`: it contains an orphaned duplicate HTML/PHP fragment beginning at line 50, outside a valid switch/try block. This is a pre-existing defect in commit `bfa5b00`. The new notification controller will be implemented from the dashboard caller contract and covered by failing tests before production code is added.
