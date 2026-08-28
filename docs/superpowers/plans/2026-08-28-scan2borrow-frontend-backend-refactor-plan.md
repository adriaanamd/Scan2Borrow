# Scan2Borrow Frontend/Backend Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move Scan2Borrow from embedded PHP pages to a vanilla static frontend and an OOP PHP modular monolith while preserving every current visual, interaction, auth, data, and integration behavior.

**Architecture:** Apache exposes clean routes to an OOP PHP front controller. The front controller authorizes protected page requests before reading static HTML from `frontend/pages`, denies direct source-file access, and dispatches `/api/...` requests to typed controllers. The backend is a single modular monolith with Domain, Application, Infrastructure, and HTTP boundaries; the frontend is class-based vanilla HTML/CSS/JS with existing DOM contracts preserved.

**Tech Stack:** PHP 8.3+ target runtime, PHP 8.2-compatible syntax during local transition where practical, PDO/MySQL, PHPUnit, PHPStan level 9, Apache/XAMPP `.htaccess`, vanilla HTML/CSS/JavaScript, Bootstrap 5.3.3 CDN, Inter font CDN, html5-qrcode 2.3.8 CDN, JsBarcode versions currently used by each page, and vendored PHPMailer.

## Global Constraints

- Frontend technology is vanilla HTML, CSS, and JavaScript only.
- Backend technology is PHP 8.3+ target code, MySQL, PDO, and no PHP framework.
- The current XAMPP CLI reports PHP 8.2.12; PHP 8.3+ is the target/runtime requirement, and the implementation setup will fail clearly when the required runtime/toolchain is unavailable. Where practical, source syntax will remain compatible with 8.2 so local syntax checks can still run during the transition.
- All new PHP application code uses `declare(strict_types=1)`, PSR-12 formatting, complete type declarations, readonly properties where appropriate, typed DTOs/value objects, dependency injection, and classes organized by responsibility.
- Thin entry scripts may bootstrap and dispatch the application; they contain no business logic or free-standing application functions.
- All state-changing requests retain CSRF protection, server-side validation, prepared statements, authorization checks, and audit logging where the current behavior has it.
- Protected pages and APIs are inaccessible without the required session and role, even when their URL is typed directly.
- Existing CSS, markup structure, copy, emoji/icons, dimensions, Bootstrap classes, CDN versions, print styles, modals, drawers, toasts, scanner flows, and responsive behavior are compatibility requirements.
- The existing SQL is the database source of truth and must be preserved to the maximum possible extent: table names, columns, types, indexes, constraints, statuses, seed data, upgrade scripts, and data semantics are not redesigned. Repositories wrap the existing schema; any required SQL change must be additive, compatibility-preserving, separately tested, and explicitly documented.
- PHPMailer remains an integration dependency; its vendored source is not rewritten as part of this refactor.
- Every production behavior change follows TDD: write a failing test, observe the expected failure, implement the smallest passing change, rerun the full relevant suite, then refactor without changing behavior.
- Target commit count is exactly 72 meaningful commits. Each commit below contains a testable behavior, a required boundary, a parity artifact, or a verified cutover step.

## File map

### Backend

- `backend/public/index.php`: thin bootstrap and front-controller invocation.
- `backend/src/Bootstrap/ApplicationFactory.php`: composition root and dependency graph.
- `backend/src/Domain/*`: enums, entities, value objects, and module contracts.
- `backend/src/Application/DTO/*`: readonly request and response data objects.
- `backend/src/Application/Services/*`: use-case orchestration and business rules.
- `backend/src/Application/Validators/*`: typed input validation.
- `backend/src/Infrastructure/Database/*`: PDO connection, transaction manager, schema helpers.
- `backend/src/Infrastructure/Persistence/*`: PDO repository implementations.
- `backend/src/Infrastructure/Mail/*`, `Sms/*`, `Uploads/*`: external adapters.
- `backend/src/Http/Routing/*`: route definitions and request dispatch.
- `backend/src/Http/Controllers/*`: thin HTTP adapters.
- `backend/src/Http/Middleware/*`: session, CSRF, role, and error middleware.
- `backend/src/Http/Requests/*`, `Responses/*`: typed HTTP input/output.
- `backend/tests/Unit/*`: isolated domain/service/controller tests.
- `backend/tests/Feature/*`: API, authorization, page-gateway, and database-contract tests.
- `backend/tests/Fixtures/*`: deterministic users, visitors, books, loans, and notifications.
- `backend/composer.json`, `backend/phpunit.xml`, `backend/phpstan.neon`: tooling and autoload configuration only.

### Frontend

- `frontend/pages/*.html`: static page bodies with no PHP.
- `frontend/assets/css/style.css`: current global CSS copied before cleanup.
- `frontend/assets/css/pages/*.css`: extracted inline rules with unchanged declarations.
- `frontend/assets/js/core/*.js`: `ApiClient`, session guard, DOM helpers, camera/scanner primitives.
- `frontend/assets/js/pages/*.js`: one class-based controller per page family.
- `frontend/assets/images/book-capture-pose-guide.svg`: preserved asset.
- `frontend/shared/*.html`: static shell fragments where composition does not alter markup.
- `frontend/parity/page-matrix.md`: every legacy file, route, guard, asset, control, and verification state.

### Root deployment

- `.htaccess`: clean page/API rewrites and source-directory denial.
- `uploads/`: preserved public image storage path, with upload authorization enforced by the backend.
- `sql/`: existing schema and upgrades retained; new SQL only when required by compatibility or test isolation.

## Commit ledger

| Commit | Task | Commit message |
|---:|---:|---|
| 01 | 1 | `test: define backend runtime contract` |
| 02 | 1 | `chore: add framework-free php tooling` |
| 03 | 1 | `feat: add typed backend bootstrap` |
| 04 | 1 | `chore: enforce phpstan and phpunit quality gates` |
| 05 | 2 | `test: define typed http request and response` |
| 06 | 2 | `feat: add http request and json response objects` |
| 07 | 2 | `test: define modular route dispatch` |
| 08 | 2 | `feat: add route dispatcher and error mapping` |
| 09 | 3 | `test: deny direct source file access` |
| 10 | 3 | `feat: add apache clean route rewrites` |
| 11 | 3 | `test: enforce protected page policy before streaming` |
| 12 | 3 | `feat: add authorized static page gateway` |
| 13 | 4 | `test: model session principal and csrf behavior` |
| 14 | 4 | `feat: add session and csrf services` |
| 15 | 4 | `test: enforce role and guest policies` |
| 16 | 4 | `feat: add authorization middleware and request context` |
| 17 | 5 | `test: preserve student and staff login contracts` |
| 18 | 5 | `feat: add user authentication repositories and service` |
| 19 | 5 | `test: expose auth session and logout contracts` |
| 20 | 5 | `feat: add auth controllers and clean auth routes` |
| 21 | 6 | `test: preserve borrower registration validation` |
| 22 | 6 | `feat: add registration DTOs validators and service` |
| 23 | 6 | `test: preserve borrower OTP lifecycle` |
| 24 | 6 | `feat: add OTP repository service and controllers` |
| 25 | 7 | `test: preserve guest registration and visitor session` |
| 26 | 7 | `feat: add guest registration and visitor authentication` |
| 27 | 7 | `test: preserve guest profile and OTP update rules` |
| 28 | 7 | `feat: add guest profile and visitor services` |
| 29 | 8 | `test: preserve book query and status rules` |
| 30 | 8 | `feat: add book domain repository and query service` |
| 31 | 8 | `test: preserve inventory mutation validation` |
| 32 | 8 | `feat: add inventory controller and book mutation service` |
| 33 | 9 | `test: preserve inventory page markup contract` |
| 34 | 9 | `feat: migrate inventory page and shared assets` |
| 35 | 9 | `test: preserve inventory browser interactions` |
| 36 | 9 | `feat: add class-based inventory frontend controller` |

---

### Task 1: Backend tooling and composition root

**Files:** Create `backend/composer.json`, `backend/phpunit.xml`, `backend/phpstan.neon`, `backend/src/Bootstrap/ApplicationFactory.php`, `backend/src/Support/Runtime.php`, and `backend/tests/Unit/Support/RuntimeTest.php`.

**Interfaces:** `Runtime::minimumPhpVersion(): string` returns `'8.3.0'`; `Runtime::assertSupported(string $version): void`; `ApplicationFactory::create(): Application` returns the configured application object.

- [ ] **Step 1 / commit 01:** Write `RuntimeTest::testRejectsPhpBelowTarget()` asserting `Runtime::assertSupported('8.2.12')` throws `RuntimeException` containing `PHP 8.3+ is required`; run PHPUnit and observe the missing-class failure.
- [ ] **Step 2 / commit 02:** Add framework-free Composer PSR-4 autoloading and PHPUnit/PHPStan dev dependencies; run `composer install` from `backend` and rerun the test, observing the runtime-class failure.
- [ ] **Step 3 / commit 03:** Implement strict-typed `Runtime` and `ApplicationFactory` classes; run the focused test and commit the smallest green implementation.
- [ ] **Step 4 / commit 04:** Configure strict PHPUnit and PHPStan level 9, run both tools on the foundation, and commit only the quality-gate configuration.

### Task 2: HTTP primitives and modular dispatcher

**Files:** Create `backend/src/Http/Requests/ServerRequest.php`, `backend/src/Http/Responses/ResponseInterface.php`, `JsonResponse.php`, `RedirectResponse.php`, `backend/src/Http/Routing/Route.php`, `Router.php`, `backend/src/Http/Exceptions/HttpException.php`, and matching Unit tests.

**Interfaces:** `ServerRequest::fromGlobals(): self`; `Router::dispatch(ServerRequest $request): ResponseInterface`; `JsonResponse::toString(): string` emits `Content-Type: application/json`.

- [ ] **Step 1 / commit 05:** Write tests for normalized `/api/books/` routing and JSON `{ "ok": true, "data": {} }` serialization; run PHPUnit and observe missing-class failures.
- [ ] **Step 2 / commit 06:** Implement immutable request/response objects with typed properties and JSON encoding; run HTTP tests and commit.
- [ ] **Step 3 / commit 07:** Write a test for unknown routes returning HTTP 404 with `{ "ok": false, "errors": [...] }`; observe the expected failure and commit the test.
- [ ] **Step 4 / commit 08:** Implement router lookup and exception-to-response mapping, run all HTTP tests and PHPStan, and commit.

### Task 3: Apache denial, clean rewrites, and protected page gateway

**Files:** Create `.htaccess`, `backend/src/Http/Routing/PageRoute.php`, `PageRouteTable.php`, `backend/src/Http/Controllers/PageController.php`, `backend/tests/Feature/SourceAccessTest.php`, and `PageGatewayTest.php`; modify `backend/public/index.php`.

**Interfaces:** `PageRouteTable::forPath(string $path): PageRoute`; `PageController::__invoke(ServerRequest $request, PageRoute $route): ResponseInterface` authorizes before streaming an allowlisted file.

- [ ] **Step 1 / commit 09:** Write tests proving direct `frontend/pages`, `backend/src`, and `backend/tests` requests are denied and unauthenticated `/staff/dashboard` redirects without page HTML; run and observe failures.
- [ ] **Step 2 / commit 10:** Add `.htaccess` rules denying source directories and routing `/api/` plus clean pages to `backend/public/index.php`; validate the rules with focused tests.
- [ ] **Step 3 / commit 11:** Write a page-policy matrix test for public login, staff dashboard, student dashboard, teacher dashboard, guest dashboard, inventory, and admin staff page.
- [ ] **Step 4 / commit 12:** Implement typed page routes and authorization-before-`readfile`; run controller tests and local Apache requests when available.

### Task 4: Sessions, CSRF, principals, and policies

**Files:** Create `backend/src/Domain/Auth/Role.php`, `Principal.php`, `SessionIdentity.php`, `backend/src/Application/Services/SessionService.php`, `CsrfService.php`, `backend/src/Http/Middleware/AuthenticationMiddleware.php`, `AuthorizationMiddleware.php`, `backend/src/Http/RequestContext.php`, and tests.

**Interfaces:** `SessionService::start(): void`, `current(): ?SessionIdentity`, `login(Principal $principal): void`, `logout(): void`; `CsrfService::token(): string`, `assertValid(string $submitted): void`; authorization middleware requires roles or guest.

- [ ] **Step 1 / commit 13:** Write tests for session identity/regeneration, CSRF constant-time comparison, and student/teacher/staff/admin/guest policy decisions.
- [ ] **Step 2 / commit 14:** Implement strict-typed session, CSRF, role, principal, and identity classes; run focused tests.
- [ ] **Step 3 / commit 15:** Write tests proving wrong-role requests redirect to current home destinations and missing sessions redirect to the correct login page.
- [ ] **Step 4 / commit 16:** Implement request context and middleware composition, run auth tests plus PHPStan, and commit.

### Task 5: Student/staff authentication, session endpoint, and logout

**Files:** Create `backend/src/Domain/User/User.php`, `UserRepositoryInterface.php`, `backend/src/Infrastructure/Persistence/PdoUserRepository.php`, typed login DTOs, `AuthenticationService.php`, `AuthController.php`, and `backend/tests/Feature/AuthApiTest.php`.

**Interfaces:** `AuthenticationService::loginBorrower(StudentLoginRequest $request): Principal`; `loginStaff(StaffLoginRequest $request): Principal`; `AuthController` exposes `studentLogin`, `staffLogin`, `session`, and `logout`.

- [ ] **Step 1 / commit 17:** Write feature tests for valid student barcode, valid staff barcode/password, invalid password, lockout, role home, session identity, and logout.
- [ ] **Step 2 / commit 18:** Implement prepared-statement user repository and authentication service with password verification, failed-attempt tracking, lockout, and session regeneration.
- [ ] **Step 3 / commit 19:** Write API contract tests asserting legacy-compatible `success`, `message`, `role`, and session payload keys.
- [ ] **Step 4 / commit 20:** Add `/api/auth/student/login`, `/api/auth/staff/login`, `/api/auth/session`, and `/api/auth/logout` routes; run feature tests.

### Task 6: Borrower registration and OTP lifecycle

**Files:** Create typed registration/OTP DTOs, `RegistrationService.php`, `OtpService.php`, `PdoOtpRepository.php`, `RegistrationController.php`, and Unit/Feature tests.

**Interfaces:** `RegistrationService::registerBorrower(RegisterBorrowerRequest $request): RegistrationPending`; `OtpService::issue`, `verify`, and `resend` preserve five-minute expiry and sixty-second cooldown.

- [ ] **Step 1 / commit 21:** Write tests for required student/teacher fields, contact format, duplicate barcode, photo payload, six-digit OTP, expiry, resend cooldown, and one-time use.
- [ ] **Step 2 / commit 22:** Implement typed validation, registration service, OTP repository/service, and injected SMS port.
- [ ] **Step 3 / commit 23:** Write tests for expired/invalid OTP and successful account creation retaining role and fields.
- [ ] **Step 4 / commit 24:** Implement `/api/auth/register`, `/api/auth/otp`, `/register`, and `/verify-otp` routes; run all registration tests.

### Task 7: Guest registration, visitor session, profile, and profile OTP

**Files:** Create typed `Visitor` domain classes, visitor repository, guest auth/profile DTOs and services, controllers, and Guest Unit/Feature tests.

**Interfaces:** `GuestAuthenticationService::beginRegistration`, `completeRegistration`; `GuestProfileService::update`; guest identity remains separate from user identity.

- [ ] **Step 1 / commit 25:** Write tests for guest fields, duplicate government ID, photo requirement, visitor number/QR, expiry/status, visit history, and guest session.
- [ ] **Step 2 / commit 26:** Implement visitor entity/repository and guest registration/OTP authentication.
- [ ] **Step 3 / commit 27:** Write tests for profile update, contact re-verification, purpose validation, and security logs.
- [ ] **Step 4 / commit 28:** Implement guest profile routes with guest-only authorization and run tests.

### Task 8: Book domain, inventory queries, and mutations

**Files:** Create book entities/enums/query objects, book repository/service/controller, typed mutation DTOs, `backend/tests/Feature/SchemaContractTest.php`, and Unit/Feature tests.

**Interfaces:** `BookService::list(BookQuery $query): PaginatedBooks`; `create`, `update`, `archive`, `restore`, `delete`; preserve `ok`, `data`, `total`, `page`, and `pages`.

- [ ] **Step 1 / commit 29:** Write `SchemaContractTest` against `scan2borrow_2_0.sql` and the existing upgrade scripts, then add tests for allowlisted sorts, search fields, pagination, archived filtering, duplicate barcode/accession, keyword replacement, active-loan delete protection, and status values. The schema test must fail if required existing tables, columns, statuses, or upgrade files disappear.
- [ ] **Step 2 / commit 30:** Implement typed book models, allowlisted query builder, PDO repository, and service with parameterized SQL.
- [ ] **Step 3 / commit 31:** Write mutation contract tests for FormData fields, cover path handling, bulk IDs, audit events, and exact messages.
- [ ] **Step 4 / commit 32:** Implement inventory routes for list/create/update/archive/restore/delete and run all book tests.

### Task 9: Inventory page and browser controller

**Files:** Create `frontend/pages/inventory.html`, `frontend/assets/js/pages/inventory-controller.js`, `frontend/parity/page-matrix.md`; copy `style.css`; create `frontend/assets/js/core/api-client.js` and `barcode-scanner.js`; add markup/browser tests.

**Interfaces:** Preserve IDs `inv-body`, `inv-search`, `inv-status`, `inv-view`, `inv-pager`, `bookDrawer`, `book-form`, `toast-host`, and all `data-sort`, `data-bulk`, `data-scan-target` attributes.

- [ ] **Step 1 / commit 33:** Write failing markup tests for table headings, drawer fields, Bootstrap assets, CSS classes, and exact current copy.
- [ ] **Step 2 / commit 34:** Copy current inventory structure and `style.css` into static frontend files, preserving scanner behavior and dimensions.
- [ ] **Step 3 / commit 35:** Write browser-module tests for debounce, filters, sort, pagination, selection, confirmations, cover preview, drawer reset, toasts, and API errors.
- [ ] **Step 4 / commit 36:** Implement class-based `InventoryController` by extracting current `assets/js/inventory.js` behavior without changing selectors/messages.

| Commit | Task | Commit message |
|---:|---:|---|
| 37 | 10 | `test: preserve borrower loan policy calculations` |
| 38 | 10 | `feat: add borrowing domain and transaction services` |
| 39 | 10 | `test: preserve student teacher borrow return contracts` |
| 40 | 10 | `feat: add borrower borrowing controllers and routes` |
| 41 | 11 | `test: preserve borrower dashboard data contract` |
| 42 | 11 | `feat: migrate borrower dashboards and shared shell` |
| 43 | 11 | `test: preserve search history recommendations and receipts` |
| 44 | 11 | `feat: add borrower frontend controllers and search pages` |
| 45 | 12 | `test: preserve guest borrow release and return rules` |
| 46 | 12 | `feat: add guest borrowing domain and review services` |
| 47 | 12 | `test: preserve guest visit security and notification rules` |
| 48 | 12 | `feat: add guest workflow controllers and routes` |
| 49 | 13 | `test: preserve guest page structures and camera controls` |
| 50 | 13 | `feat: migrate guest frontend pages without visual changes` |
| 51 | 13 | `test: preserve guest receipt pass and history interactions` |
| 52 | 13 | `feat: add guest frontend controllers and protected routes` |
| 53 | 14 | `test: preserve staff approval and notification contracts` |
| 54 | 14 | `feat: add notification domain and staff workflow services` |
| 55 | 14 | `test: preserve notification polling and viewed state` |
| 56 | 14 | `feat: add staff dashboard controllers and notification routes` |
| 57 | 15 | `test: preserve staff borrower report and settings contracts` |
| 58 | 15 | `feat: add staff report receipt user and settings services` |
| 59 | 15 | `test: preserve print csv and modal page behavior` |
| 60 | 15 | `feat: migrate staff utility and receipt pages` |
| 61 | 16 | `test: preserve photo mail sms and reminder adapters` |
| 62 | 16 | `feat: add typed photo mail sms and scheduler adapters` |
| 63 | 16 | `test: preserve integration failure isolation` |
| 64 | 16 | `feat: connect integration adapters to use cases` |
| 65 | 17 | `test: cover complete clean-route authorization matrix` |
| 66 | 17 | `test: cover API contract and regression matrix` |
| 67 | 17 | `feat: add browser parity and screenshot verification harness` |
| 68 | 17 | `chore: pass full syntax phpstan phpunit and parity gates` |
| 69 | 18 | `test: verify clean-route cutover and rollback mapping` |
| 70 | 18 | `feat: switch root routes to protected frontend gateway` |
| 71 | 18 | `docs: publish deployment and parity runbook` |
| 72 | 18 | `chore: finalize refactor verification and handoff` |

### Task 10: Borrowing domain and student/teacher loan controllers

**Files:** Create `backend/src/Domain/Borrowing/Borrowing.php`, `BorrowingStatus.php`, `LoanPolicy.php`, `BorrowingRepositoryInterface.php`, `backend/src/Application/Services/BorrowingService.php`, `FineService.php`, `backend/src/Infrastructure/Persistence/PdoBorrowingRepository.php`, `backend/src/Http/Controllers/BorrowingController.php`, and Unit/Feature tests.

**Interfaces:** `LoanPolicy::dueDateFor(Role $role, DateTimeImmutable $borrowDate, ?DateTimeImmutable $requestedDue): DateTimeImmutable`; `BorrowingService::borrowOne`, `borrowMany`, `returnByBarcode`, and `returnById` are transaction boundaries.

- [ ] **Step 1 / commit 37:** Write tests for max concurrent loans, `REQUIRE_APPROVAL`, student default due dates, teacher requested-due cap, duplicate scans, available/borrowed/overdue transitions, fine calculation, transaction-code format, notifications, and audit events.
- [ ] **Step 2 / commit 38:** Implement typed loan policy, borrowing entity/repository, fine service, and transaction-aware application service.
- [ ] **Step 3 / commit 39:** Write API contract tests for single borrow, multi-book borrow, unified return, direct return, and exact current messages.
- [ ] **Step 4 / commit 40:** Implement borrower controller routes with borrower/staff policies and run all borrowing tests.

### Task 11: Borrower dashboards, search, history, receipts, and frontend modules

**Files:** Create static `frontend/pages/student-dashboard.html`, `teacher-dashboard.html`, `student-search.html`, `student-history.html`, `receipt.html`; create borrower/search/receipt JS controllers; create `BorrowerController.php`, `RecommendationService.php`, `ReceiptService.php`; add page-contract tests.

**Interfaces:** Dashboard data includes profile/avatar, counts, overdue/fines, active loans, recommendations, achievements, and receipt transaction codes. `ReceiptService::forBorrower(string $transactionCode, Principal $principal): ReceiptView` enforces ownership/staff access.

- [ ] **Step 1 / commit 41:** Write failing tests for dashboard sections, DOM IDs, forms, modal contents, barcode SVG, recommendation links, history table, and receipt links.
- [ ] **Step 2 / commit 42:** Migrate student/teacher markup and shared shell into static pages, preserving inline styles as CSS and exact CDN versions.
- [ ] **Step 3 / commit 43:** Write tests for search filters, logging, recommendations, receipt ownership, print/email actions, and teacher due-date controls.
- [ ] **Step 4 / commit 44:** Implement borrower/search/receipt controllers and services; run page/API contract tests.

### Task 12: Guest borrowing, review, release, return verification, and notifications

**Files:** Create `GuestBorrowing.php`, `GuestBorrowingStatus.php`, `VisitorNotification.php`, `GuestBorrowingService.php`, `GuestReviewService.php`, `PdoGuestBorrowingRepository.php`, `GuestBorrowingController.php`, and Guest Unit/Feature tests.

**Interfaces:** `GuestBorrowingService::submitRequest`, `submitReturnVerification`, `history`, `borrowed`, and `receipt`; `GuestReviewService::approve`, `reject`, and `pending` preserve all current guest statuses.

- [ ] **Step 1 / commit 45:** Write tests for available-book checks, government-ID scan, verification-photo requirement, pending/released/rejected/return-verification states, rejection reason, visitor notification, return verification, logs, and ownership.
- [ ] **Step 2 / commit 46:** Implement guest borrowing/review entities, repositories, services, and transactions.
- [ ] **Step 3 / commit 47:** Write API tests for visitor dashboard summary, browse filters, history/date filters, receipt access, and notification payloads.
- [ ] **Step 4 / commit 48:** Implement guest borrowing/review controllers and protected guest routes.

### Task 13: Guest frontend pages and camera flows

**Files:** Create static guest pages for registration, OTP, dashboard, profile, profile OTP, browse, borrowed, history, request, return, pass, and receipt; create `CameraCapture`, guest registration/request/page controllers; add Guest page/Camera tests.

**Interfaces:** `CameraCapture` preserves start/capture/retake, video/canvas/preview IDs, JPEG quality `.85`, facing mode, stop-on-unload, and current error messages. Guest pages preserve current form names, modal IDs, barcode element, print controls, and photo-review data attributes.

- [ ] **Step 1 / commit 49:** Write failing contracts for every guest page’s sections, forms, IDs, camera controls, SVG/barcode, print button, and copy.
- [ ] **Step 2 / commit 50:** Migrate all guest HTML and CSS/inline print rules without changing markup semantics, CDN versions, or dimensions.
- [ ] **Step 3 / commit 51:** Write tests for history/receipt/pass navigation, photo viewer/review modal, OTP resend, purpose toggle, and camera state transitions.
- [ ] **Step 4 / commit 52:** Implement class-based guest controllers and protected-page bootstrap hooks; run guest tests.

### Task 14: Staff dashboard, approvals, and notifications

**Files:** Create notification entities/enums, `NotificationService.php`, `ApprovalService.php`, `PdoNotificationRepository.php`, `NotificationController.php`, `StaffDashboardController.php`, static staff dashboard, and notification tests.

**Interfaces:** Preserve notification keys `success`, `count`, `html`, `notifications`, `id`, `title`, `message`, and formatted `created_at`. `ApprovalService::approve` and `reject` update loan/book/notification/audit state transactionally.

- [ ] **Step 1 / commit 53:** Write tests for approval/rejection, pending count, return/borrow notifications, viewed state, five-second polling payloads, and the legacy dashboard caller contract.
- [ ] **Step 2 / commit 54:** Implement notification repositories/services and approval transactions, replacing the parse-broken legacy endpoint contract cleanly.
- [ ] **Step 3 / commit 55:** Write frontend tests for modal auto-open, badge updates, toast suppression/open-state, message rendering, and mark-viewed requests.
- [ ] **Step 4 / commit 56:** Migrate dashboard markup and implement class-based polling with Bootstrap integration.

### Task 15: Staff borrowers, reports, settings, receipts, and utilities

**Files:** Create `ReportService.php`, `StaffService.php`, `SettingsService.php`, report/staff/settings/receipt controllers, static borrowers/detail/overdue/reports/notification/staff/settings/photo-check pages, and Staff/Report tests.

**Interfaces:** Reports preserve `type`, `from`, `to`, CSV headers/rows, and print data. Staff service preserves admin-only promotion/password/status actions and borrower photo updates. Settings preserves guest editable fields and the contact-number OTP branch.

- [ ] **Step 1 / commit 57:** Write tests for role authorization, borrower search/detail, overdue refresh, reports, CSV/print flags, notification forms, staff actions, settings, and diagnostics.
- [ ] **Step 2 / commit 58:** Implement typed services/controllers with prepared repositories and exact current response/message semantics.
- [ ] **Step 3 / commit 59:** Write markup/interaction tests for print CSS, report buttons, modal forms, and confirmation behavior.
- [ ] **Step 4 / commit 60:** Migrate staff/utility/receipt pages and bind frontend controllers.

### Task 16: Photo, mail, SMS, and scheduled reminder adapters

**Files:** Create photo, mail, SMS interfaces/adapters, `ReminderService.php`, `Console/SendDueReminders.php`, and Infrastructure/Reminder tests.

**Interfaces:** Photo storage preserves 4 MB limits, allowed MIME types, safe filenames, `uploads/photos`, and data URLs. Mail/SMS adapters return typed delivery results and cannot roll back committed borrowing. `ReminderService::sendDueTomorrow(): int` preserves `sms_logs` duplicate prevention.

- [ ] **Step 1 / commit 61:** Write tests for valid/invalid photos, MIME/size limits, safe paths, disabled/configured/failing mail/SMS, duplicate reminders, and command count.
- [ ] **Step 2 / commit 62:** Implement adapters and scheduler service with injected configuration and no hardcoded secrets.
- [ ] **Step 3 / commit 63:** Write tests proving committed borrowing survives SMS/email failure and audit data remains available.
- [ ] **Step 4 / commit 64:** Connect adapters to registration, borrowing, approval, return, notifications, and reminders.

### Task 17: Authorization, API regression, browser parity, and quality harness

**Files:** Create authorization/API regression tests, fixtures, `frontend/parity/page-matrix.md`, `expected-routes.json`, `SessionGuard`, browser route/parity scripts; modify PHPUnit/PHPStan configuration.

**Interfaces:** `SessionGuard::boot()` calls `/api/auth/session`, redirects on `401/403`, and never replaces server authorization. The route matrix lists every clean route, required principal, legacy source, page, and redirect.

- [ ] **Step 1 / commit 65:** Write the full role matrix and assert protected responses contain no page HTML before authorization.
- [ ] **Step 2 / commit 66:** Write API regression tests for every compatibility envelope, message, status transition, upload, CSV, print, notification, and scheduler contract.
- [ ] **Step 3 / commit 67:** Add browser checks that request every route, compare redirects/status, validate DOM IDs/text, and capture representative screenshots.
- [ ] **Step 4 / commit 68:** Run XAMPP PHP syntax, PHPUnit, PHPStan level 9, route tests, and browser checks; resolve verified failures only.

### Task 18: Protected cutover, documentation, rollback, and handoff

**Files:** Create `DEPLOYMENT.md`, `frontend/parity/cutover-report.md`, README route/auth/test sections, Cutover/Rollback tests; modify `.htaccess`, `frontend/pages/*`, and `backend/public/index.php`.

**Interfaces:** `PageRouteTable` is the only clean route-to-page/access-policy source. Rollback can map a failed clean surface to its legacy reference without deleting code or resetting data.

- [ ] **Step 1 / commit 69:** Write tests for final mappings, direct protected URL access, direct source denial, public assets, and rollback mapping.
- [ ] **Step 2 / commit 70:** Switch root rewrites and front-controller mappings to the protected frontend gateway; run cutover tests.
- [ ] **Step 3 / commit 71:** Document XAMPP Apache setup, PHP 8.3+ requirement, Composer dev tooling, database import, clean routes, roles, tests, scheduler, and rollback.
- [ ] **Step 4 / commit 72:** Run the complete verification checklist, inspect `git diff --check` and `git status`, record exact outputs, and commit only with all required evidence.

## Verification commands

Run from `C:\xampp\htdocs\scan2borrow\.worktrees\scan2borrow-refactor`:

```powershell
$phpExe = 'C:\xampp\php\php.exe'
Get-ChildItem backend\src,backend\public -Recurse -Filter *.php | ForEach-Object { & $phpExe -l $_.FullName }

Set-Location backend
vendor\bin\phpunit --testdox
vendor\bin\phpstan analyse --level=9

Set-Location ..
powershell -ExecutionPolicy Bypass -File tests\browser\clean-route-access.ps1

git diff --check
git status --short
```

Expected final evidence: all new PHP syntax checks exit 0; PHPUnit reports zero failures, warnings, and risky tests; PHPStan level 9 reports zero errors; direct protected URLs redirect or return unauthorized before HTML delivery; public routes/assets remain reachable; every parity matrix row is checked; and no legacy source is deleted before its replacement passes parity verification.
