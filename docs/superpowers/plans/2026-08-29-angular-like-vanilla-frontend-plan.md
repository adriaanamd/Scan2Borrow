# Angular-Like Vanilla Frontend Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reorganize the complete Scan2Borrow frontend into feature-first native ES modules with reusable components and focused services while preserving all existing UI, UX, routes, API behavior, and workflows.

**Architecture:** Keep server-authorized static pages and clean URLs, but move canonical templates into feature page folders. Each page loads one native ES module bootstrap. Shared infrastructure owns API/session/layout primitives; feature services own endpoint workflows; page modules compose bounded components. The plan is split into independently testable feature groups so the refactor can be reviewed and rolled back incrementally.

**Tech Stack:** Vanilla HTML, CSS, and JavaScript; native browser ES modules; PHP 8.2-compatible existing backend during the transition; Apache/XAMPP; Bootstrap 5.3.3; Inter font; JsBarcode 3.11.6; html5-qrcode 2.3.8; PHPUnit; PHPStan; Node 24 built-in test runner.

## Global Constraints

- Remain framework-free: vanilla HTML, CSS, and JavaScript only.
- Use native browser ES modules with import and export; no bundler is required.
- Preserve all existing clean URLs, redirects, session behavior, CSRF behavior, API payloads, response shapes, and user-facing messages.
- Preserve existing UI/UX: layout, typography, colors, spacing, responsive behavior, Bootstrap integration, copy, icons, forms, modals, drawers, alerts, toasts, loading states, empty states, printing, camera, barcode, and upload flows.
- Preserve existing DOM IDs, form names, query parameters, data attributes, and accessibility affordances wherever current behavior or tests depend on them.
- Do not convert the application into a client-side SPA or replace server-side page authorization with browser logic.
- Do not redesign the database or change backend business rules as part of this frontend refactor.
- Preserve the current uncommitted changes in frontend/assets/js/pages/registration.js and frontend/assets/js/pages/student-search.js until a migration task explicitly includes them.
- Deliver exactly 60 meaningful, non-empty implementation commits, within the requested 50–70 range. The design and plan commits do not count.
- Do not delete a legacy page or module until its replacement passes route, markup, interaction, and visual parity checks.

## Planned file ownership

    frontend/
      app/
        bootstrap/
          page-registry.js
          page-context.js
          auth-page.js
          student-page.js
          teacher-page.js
          staff-page.js
          guest-page.js
        core/
          api/api-client.js
          api/api-error.js
          auth/session.service.js
          auth/session.guard.js
          layout/app-shell.component.js
          services/modal.service.js
          services/notification.service.js
          services/toast.service.js
          utils/dom.js
          utils/formatters.js
          utils/security.js
        shared/components/
          app-navbar/
          auth-brand/
          barcode-scanner/
          camera-capture/
          data-table/
          empty-state/
          loading-state/
          toast-host/
        shared/models/
      features/
        auth/
        student/
        teacher/
        staff/
        guest/
      assets/css/
      assets/images/
      tests/
      parity/

Feature page folders use one directory per page and contain the canonical HTML template, page module, and page-specific CSS only when moving the CSS does not change specificity or rendering. frontend/assets/css/style.css remains the global compatibility stylesheet until visual parity is proven.

Modify backend/src/Http/Routing/PageRouteTable.php and backend/src/Bootstrap/ApplicationFactory.php to point clean routes at canonical feature templates. Modify .htaccess to deny direct frontend/app and frontend/features reads while allowing frontend/assets. Add backend/tests/Support/FrontendPagePaths.php so PHP contract tests use one explicit template map.

## Required test cycle

Every numbered commit below follows this explicit cycle:

- [ ] Write the focused failing test or contract.
- [ ] Run the focused command and record the expected failure.
- [ ] Implement the smallest behavior-preserving change.
- [ ] Run the focused test, relevant regression tests, and syntax checks.
- [ ] Commit only the files belonging to that numbered slice.

Use these commands unless a commit gives a narrower command:

    npm test
    node --test frontend/tests/*.test.js
    Get-ChildItem frontend -Recurse -Filter *.js | ForEach-Object { node --check $_.FullName }
    C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml --testdox
    C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon
    git diff --check

## Commit map

The following 60 commits are the implementation sequence. Each commit is a separate reviewable boundary; none is an empty progress commit.

---

### Task 1: Baseline contracts and migration inventory

**Files:** Create package.json, frontend/tests/architecture.test.js, backend/tests/Feature/FrontendModuleLayoutTest.php, backend/tests/Support/FrontendPagePaths.php, frontend/parity/page-matrix.md. Modify the existing page completeness and visual contract tests.

**Interfaces:** FrontendPagePaths::path(string $name): string; package script npm test; matrix fields route, policy, legacy template, legacy script, canonical template, page module, bootstrap, and status.

#### Commit 1: Add Node module test runner

- [ ] Test: add a Node test that imports a local module and verifies frontend exists.
- [ ] Run: node --test frontend/tests/architecture.test.js; expected initial failure because package.json and the test do not exist.
- [ ] Implement: add package.json with private=true, type=module, and test script node --test frontend/tests/*.test.js.
- [ ] Verify: npm test and node --check frontend/tests/architecture.test.js; expected PASS.
- [ ] Commit: git add package.json frontend/tests/architecture.test.js; git commit -m "test: add native frontend module test runner".

#### Commit 2: Add architecture folder contract

- [ ] Test: add FrontendModuleLayoutTest assertions for frontend/app, frontend/features, frontend/tests, and no cross-feature imports.
- [ ] Run: phpunit filtered to FrontendModuleLayoutTest; expected FAIL because the folders and class do not exist.
- [ ] Implement: create the empty architecture directories and the PHPUnit test with repository-root path resolution.
- [ ] Verify: phpunit filtered to FrontendModuleLayoutTest; expected PASS.
- [ ] Commit: git add frontend/app frontend/features frontend/tests backend/tests/Feature/FrontendModuleLayoutTest.php; git commit -m "test: define frontend feature layout contract".

#### Commit 3: Add explicit template path resolver

- [ ] Test: assert login, student dashboard, staff dashboard, and guest dashboard resolve to explicit feature template paths; unknown names throw InvalidArgumentException.
- [ ] Run: phpunit filtered to FrontendPagePaths; expected FAIL because the resolver does not exist.
- [ ] Implement: create backend/tests/Support/FrontendPagePaths.php with an explicit associative map and no filename heuristics.
- [ ] Verify: phpunit filtered to FrontendPagePaths; expected PASS.
- [ ] Commit: git add backend/tests/Support/FrontendPagePaths.php backend/tests/Feature/FrontendModuleLayoutTest.php; git commit -m "test: add canonical frontend page paths".

#### Commit 4: Create the parity matrix

- [ ] Test: assert every current PageRouteTable route has one row in frontend/parity/page-matrix.md.
- [ ] Run: phpunit filtered to CleanRouteMatrixTest; expected FAIL for the missing matrix.
- [ ] Implement: record all public, borrower, teacher, staff, guest, receipt, and admin routes with current policies and legacy ownership.
- [ ] Verify: phpunit filtered to CleanRouteMatrixTest; expected PASS.
- [ ] Commit: git add frontend/parity/page-matrix.md backend/tests/Feature/CleanRouteMatrixTest.php; git commit -m "docs: record frontend route parity matrix".

#### Commit 5: Record large-file extraction boundaries

- [ ] Test: add matrix assertions naming staff.js, inventory.js, borrower-dashboard.js, duplicated script tags, and inline styles as migration boundaries.
- [ ] Run: npm test and the filtered matrix test; expected FAIL until the risk entries exist.
- [ ] Implement: add the Current extraction risks section and update existing page tests to read the matrix.
- [ ] Verify: npm test, filtered matrix test, and git diff --check; expected PASS.
- [ ] Commit: git add frontend/parity/page-matrix.md backend/tests/Feature/CleanRouteMatrixTest.php; git commit -m "docs: define frontend extraction boundaries".

---

### Task 2: Native bootstrap and bounded DOM utilities

**Files:** Create frontend/app/bootstrap/page-registry.js, auth-page.js, student-page.js, teacher-page.js, staff-page.js, guest-page.js, frontend/app/core/utils/dom.js, frontend/tests/bootstrap.test.js.

**Interfaces:** registerPage(name, factory), bootPage(name, context), pageNameFromDocument(document), requiredElement(root, selector), optionalElement(root, selector), setText(node, value), clear(node).

#### Commit 6: Add page registry

- [ ] Test: assert pageNameFromDocument reads body.dataset.appPage and bootPage rejects an unknown name.
- [ ] Run: node --test frontend/tests/bootstrap.test.js; expected FAIL because the registry is missing.
- [ ] Implement: add a private Map registry, registerPage, pageNameFromDocument, and bootPage returning factory(context).start().
- [ ] Verify: node --test frontend/tests/bootstrap.test.js; expected PASS.
- [ ] Commit: git add frontend/app/bootstrap/page-registry.js frontend/tests/bootstrap.test.js; git commit -m "feat: add native frontend page registry".

#### Commit 7: Add bounded DOM helpers

- [ ] Test: assert requiredElement throws a selector-specific Error, optionalElement returns null, setText uses textContent, and clear removes children.
- [ ] Run: node --test frontend/tests/bootstrap.test.js; expected FAIL for missing helpers.
- [ ] Implement: add dom.js with root-scoped querySelector calls and safe text rendering.
- [ ] Verify: node --test frontend/tests/bootstrap.test.js; expected PASS.
- [ ] Commit: git add frontend/app/core/utils/dom.js frontend/tests/bootstrap.test.js; git commit -m "feat: add bounded frontend DOM helpers".

#### Commit 8: Add bootstrap dependency seam

- [ ] Test: assert role bootstraps accept an injected context factory and do not construct global fetch clients or sessions at module evaluation time.
- [ ] Run: node --test frontend/tests/bootstrap.test.js; expected FAIL because the context seam does not exist.
- [ ] Implement: keep bootstrap entrypoints dependent on a context object supplied by page-context.js; define the registry-facing factory contract in bootstrap tests without importing API/session implementations yet.
- [ ] Verify: node --test frontend/tests/bootstrap.test.js; expected PASS.
- [ ] Commit: git add frontend/app/bootstrap frontend/tests/bootstrap.test.js; git commit -m "refactor: define bootstrap dependency seam".

#### Commit 9: Add role bootstrap entrypoints

- [ ] Test: import all five bootstrap modules and assert each exports boot.
- [ ] Run: node --test frontend/tests/bootstrap.test.js; expected FAIL for missing role entrypoints.
- [ ] Implement: add role bootstrap modules that register only their feature page factories and attach one once-only DOMContentLoaded listener when document exists.
- [ ] Verify: node --test frontend/tests/bootstrap.test.js and node --check on frontend/app; expected PASS.
- [ ] Commit: git add frontend/app/bootstrap frontend/tests/bootstrap.test.js; git commit -m "feat: add role-specific page bootstraps".

#### Commit 10: Enforce one page entrypoint

- [ ] Test: add a fixture assertion requiring one body page marker and one module script, and rejecting multiple module entrypoints.
- [ ] Run: phpunit filtered to FrontendModuleLayoutTest; expected FAIL for the unconverted production pages.
- [ ] Implement: add fixture parser utilities and the production-page assertion without weakening existing classic-script parity tests.
- [ ] Verify: fixture test passes and the production assertion is the only expected failure; run git diff --check.
- [ ] Commit: git add frontend/tests backend/tests/Feature/FrontendModuleLayoutTest.php; git commit -m "test: enforce one module entrypoint per page".

---

### Task 3: API, session, and shared infrastructure

**Files:** Create frontend/app/core/api/api-error.js, api-client.js, auth/session.service.js, auth/session.guard.js, services/modal.service.js, services/toast.service.js, services/notification.service.js, utils/formatters.js, utils/security.js, and Node tests.

**Interfaces:** ApiClient.request/get/post, ApiError, SessionService.load/current/csrf/clear, SessionGuard.boot, createPageContext(options), ToastService.show/hideAll, ModalService.show/hide/reset, formatDate/formatPeso/statusClass, escapeHtml/safePath.

#### Commit 11: Add ApiError

- [ ] Test: construct ApiError with status 403 and payload { ok: false }; assert all fields survive.
- [ ] Run: node --test frontend/tests/api-client.test.js; expected FAIL because the file is missing.
- [ ] Implement: export class ApiError extends Error with status and payload properties.
- [ ] Verify: focused Node test passes.
- [ ] Commit: git add frontend/app/core/api/api-error.js frontend/tests/api-client.test.js; git commit -m "feat: add frontend API error type".

#### Commit 12: Add ApiClient GET/POST

- [ ] Test: mock fetch and assert URL query encoding, same-origin credentials, Accept header, form encoding, and CSRF field.
- [ ] Run: node --test frontend/tests/api-client.test.js; expected FAIL because ApiClient is missing.
- [ ] Implement: add injected fetchImpl, JSON parsing, compatibility checks for ok=false and success=false, ApiError conversion, GET, and POST.
- [ ] Verify: focused Node test and node --check pass.
- [ ] Commit: git add frontend/app/core/api/api-client.js frontend/tests/api-client.test.js; git commit -m "feat: centralize frontend API requests".

#### Commit 13: Add safe rendering and formatters

- [ ] Test: assert HTML escaping, safe internal paths, invalid-date preservation, peso formatting, and current status class mappings.
- [ ] Run: node --test frontend/tests/api-client.test.js; expected FAIL for missing utilities.
- [ ] Implement: add pure functions using text-safe escaping and the current en-US date/peso/status behavior.
- [ ] Verify: focused Node test passes.
- [ ] Commit: git add frontend/app/core/utils/security.js frontend/app/core/utils/formatters.js frontend/tests/api-client.test.js; git commit -m "feat: add shared frontend formatters".

#### Commit 14: Add SessionService and SessionGuard

- [ ] Test: mock session API success and 401/403 errors; assert caching, CSRF lookup, clear, and login redirect.
- [ ] Run: node --test frontend/tests/session.test.js; expected FAIL because auth services are missing.
- [ ] Implement: add session load/current/csrf/clear and post-delivery guard with public path bypass.
- [ ] Verify: focused Node test passes.
- [ ] Commit: git add frontend/app/core/auth frontend/tests/session.test.js; git commit -m "feat: add frontend session services".

#### Commit 15: Add session context, modal, toast, and notification services

- [ ] Test: assert createPageContext returns one ApiClient, SessionService, SessionGuard, document, and window; assert Bootstrap modal wrappers call the available global, toast service emits the current classes/messages, and notification service preserves five-second polling and compatibility keys.
- [ ] Run: node --test frontend/tests/shared-services.test.js; expected FAIL for missing services.
- [ ] Implement: add frontend/app/bootstrap/page-context.js using ApiClient and SessionService imports, then add injected window/document services with no hard dependency during Node tests.
- [ ] Verify: focused Node test, full npm test, and JS syntax checks pass.
- [ ] Commit: git add frontend/app/bootstrap/page-context.js frontend/app/core/services frontend/tests/session.test.js frontend/tests/shared-services.test.js; git commit -m "feat: add shared frontend UI services".

---

### Task 4: Shared layout and UI components

**Files:** Create app-shell, app-navbar, auth-brand, toast-host, loading-state, empty-state, data-table, barcode-scanner, and component tests. Modify current navbar/auth-brand contract tests.

**Interfaces:** Every component has constructor(root, options), start(), destroy(); render methods are bounded to root.

#### Commit 16: Extract navbar

- [ ] Test: assert role-specific labels, clean links, active link, logout link, and fallback behavior.
- [ ] Run: Node layout tests and RoleNavbarContractTest; expected FAIL for the importable component.
- [ ] Implement: move current AppNavbar behavior into AppNavbarComponent, remove document-level auto-start, preserve exact routes and data-nav-path values.
- [ ] Verify: Node and PHPUnit contracts pass.
- [ ] Commit: git add frontend/app/shared/components/app-navbar backend/tests/Feature/RoleNavbarContractTest.php; git commit -m "refactor: extract navbar component".

#### Commit 17: Extract auth brand and app shell

- [ ] Test: assert logo, alt text, wordmark, School Library copy, and navbar mounting.
- [ ] Run: layout tests and AuthBrandContractTest; expected FAIL for missing exports.
- [ ] Implement: move exact auth-brand markup and add AppShellComponent without changing page content.
- [ ] Verify: focused tests pass.
- [ ] Commit: git add frontend/app/shared/components/auth-brand frontend/app/core/layout backend/tests/Feature/AuthBrandContractTest.php; git commit -m "refactor: extract auth brand and app shell".

#### Commit 18: Add state components

- [ ] Test: assert loading/empty hidden state, textContent messages, toast structure, alert classes, and current toast host placement.
- [ ] Run: node --test frontend/tests/shared-components.test.js; expected FAIL.
- [ ] Implement: add ToastHostComponent, LoadingStateComponent, and EmptyStateComponent with lifecycle cleanup.
- [ ] Verify: focused Node tests pass.
- [ ] Commit: git add frontend/app/shared/components/toast-host frontend/app/shared/components/loading-state frontend/app/shared/components/empty-state frontend/tests/shared-components.test.js; git commit -m "feat: add shared state components".

#### Commit 19: Add data table

- [ ] Test: assert configured rows render, empty text is preserved, and feature renderRow callbacks stay bounded.
- [ ] Run: node --test frontend/tests/shared-components.test.js; expected FAIL.
- [ ] Implement: add DataTableComponent that selects an existing tbody or root and does not impose new table markup on feature pages.
- [ ] Verify: Node tests and JS syntax checks pass.
- [ ] Commit: git add frontend/app/shared/components/data-table frontend/tests/shared-components.test.js; git commit -m "feat: add bounded data table component".

#### Commit 20: Add scanner component boundary

- [ ] Test: assert scanner target lookup, start/stop, success callback, cleanup, and existing html5-qrcode CDN contract.
- [ ] Run: node --test frontend/tests/scanner.test.js; expected FAIL.
- [ ] Implement: wrap current core/scanner.js behavior in BarcodeScannerComponent with injected document/window and no page-level globals.
- [ ] Verify: focused test and all current scanner contract tests pass.
- [ ] Commit: git add frontend/app/shared/components/barcode-scanner frontend/tests/scanner.test.js; git commit -m "refactor: extract barcode scanner component".

---

### Task 5: Auth feature pages

**Files:** Create frontend/features/auth/pages/login, register, otp, guest-registration, guest-otp, services/auth.service.js, and auth tests. Modify old auth modules only as compatibility re-exports during cutover.

**Interfaces:** AuthService loginBorrower/loginStaff/register/verifyOtp/resendOtp; LoginPage, RegistrationPage, OtpPage start/destroy.

#### Commit 21: Add AuthService login flows

- [ ] Test: assert borrower/staff endpoints, CSRF, registration_required redirect, success redirect, and exact login-error rendering.
- [ ] Run: node --test frontend/tests/auth-pages.test.js; expected FAIL.
- [ ] Implement: move endpoint selection and redirect handling into AuthService and keep LoginPage responsible only for its bounded form.
- [ ] Verify: focused Node test and AuthPageParityTest pass.
- [ ] Commit: git add frontend/features/auth/services frontend/features/auth/pages/login frontend/tests/auth-pages.test.js; git commit -m "refactor: extract auth login feature".

#### Commit 22: Extract registration flow

- [ ] Test: assert role picker, preselected query role, details/photo steps, field visibility, camera IDs, redirects, and errors.
- [ ] Run: focused auth tests; expected FAIL.
- [ ] Implement: move RegistrationPageController into RegistrationPage and preserve showStep behavior, form names, and current visual markup.
- [ ] Verify: focused tests, AuthPageParityTest, and syntax checks pass.
- [ ] Commit: git add frontend/features/auth/pages/register frontend/tests/auth-pages.test.js frontend/assets/js/pages/registration.js; git commit -m "refactor: extract registration page".

#### Commit 23: Extract borrower OTP

- [ ] Test: assert verification field, resend endpoint, countdown, hidden values, messages, and redirect.
- [ ] Run: focused auth tests; expected FAIL.
- [ ] Implement: add borrower OtpPage using AuthService with existing IDs and copy.
- [ ] Verify: auth and CSRF contract tests pass.
- [ ] Commit: git add frontend/features/auth/pages/otp frontend/tests/auth-pages.test.js backend/tests/Feature/FrontendCsrfContractTest.php; git commit -m "refactor: extract borrower OTP page".

#### Commit 24: Extract guest registration page

- [ ] Test: assert guest registration fields, photo_data, role/purpose controls, camera roots, and redirect.
- [ ] Run: GuestMarkupParityTest; expected FAIL for missing canonical feature page.
- [ ] Implement: move guest registration markup and behavior into auth/guest-registration, injecting CameraCaptureComponent.
- [ ] Verify: focused Node and guest markup tests pass.
- [ ] Commit: git add frontend/features/auth/pages/guest-registration frontend/tests/auth-pages.test.js backend/tests/Feature/GuestMarkupParityTest.php; git commit -m "refactor: extract guest registration auth page".

#### Commit 25: Extract guest OTP pages

- [ ] Test: assert guest verification and profile verification preserve distinct endpoints, fields, resend, messages, and redirects.
- [ ] Run: guest interaction tests; expected FAIL.
- [ ] Implement: add explicit GuestOtpPage and ProfileOtpPage configuration without merging payload contracts.
- [ ] Verify: focused Node tests, GuestInteractionParityTest, and JS syntax checks pass.
- [ ] Commit: git add frontend/features/auth/pages/guest-otp frontend/features/auth/pages/profile-otp frontend/tests/auth-pages.test.js backend/tests/Feature/GuestInteractionParityTest.php; git commit -m "refactor: extract guest OTP pages".

---

### Task 6: Student and teacher feature services

**Files:** Create student and teacher models/services plus Node tests. Preserve existing student-search.js modifications until Commit 28 includes that file.

**Interfaces:** Dashboard/search/settings services return existing API envelopes and preserve field names; teacher due-date controls remain teacher-only.

#### Commit 26: Add student models

- [ ] Test: assert default stats/arrays, role fallback, user fields, loan fields, and immutability.
- [ ] Run: node --test frontend/tests/student-services.test.js; expected FAIL.
- [ ] Implement: add normalizeDashboard, normalizeBook, normalizeLoan, and normalizeUser.
- [ ] Verify: focused test passes.
- [ ] Commit: git add frontend/features/student/models frontend/tests/student-services.test.js; git commit -m "feat: add student browser models".

#### Commit 27: Add student services

- [ ] Test: assert dashboard/search/settings endpoint paths, query names, FormData fields, and borrow/return actions.
- [ ] Run: focused student services test; expected FAIL.
- [ ] Implement: add StudentDashboardService, StudentSearchService, and StudentSettingsService using ApiClient injection.
- [ ] Verify: focused Node test passes.
- [ ] Commit: git add frontend/features/student/services frontend/tests/student-services.test.js; git commit -m "feat: add student feature services".

#### Commit 28: Add teacher models/services

- [ ] Test: assert teacher dashboard/settings endpoints, requested due-date fields, role defaults, and contact-number branch.
- [ ] Run: node --test frontend/tests/teacher-services.test.js; expected FAIL.
- [ ] Implement: add teacher models and services, importing only student model normalizers where needed; include the current user changes in student-search.js only if required by the service extraction.
- [ ] Verify: focused tests and git diff preserve all unrelated user changes.
- [ ] Commit: git add frontend/features/teacher frontend/assets/js/pages/student-search.js frontend/tests/teacher-services.test.js; git commit -m "feat: add teacher feature services".

#### Commit 29: Add receipt service

- [ ] Test: assert code query, receipt endpoint, ownership error, print/email action fields, and compatibility payload.
- [ ] Run: node --test frontend/tests/receipt-service.test.js; expected FAIL.
- [ ] Implement: add the receipt service under student with injected ApiClient and no DOM dependency.
- [ ] Verify: focused test and existing receipt contracts pass.
- [ ] Commit: git add frontend/features/student/services/receipt.service.js frontend/tests/receipt-service.test.js; git commit -m "feat: add borrower receipt service".

#### Commit 30: Export feature service boundaries

- [ ] Test: import student and teacher index modules and assert only public services/models are exported.
- [ ] Run: node --test frontend/tests/student-services.test.js frontend/tests/teacher-services.test.js; expected FAIL for missing indexes.
- [ ] Implement: add services/index.js and models/index.js files with explicit exports.
- [ ] Verify: npm test and JS syntax checks pass.
- [ ] Commit: git add frontend/features/student frontend/features/teacher frontend/tests; git commit -m "refactor: define borrower feature exports".

---

### Task 7: Student and teacher page migration

**Files:** Create canonical feature HTML and page modules for student dashboard/search/history/settings/receipt and teacher dashboard/settings. Modify borrower/teacher parity tests.

**Interfaces:** StudentDashboardPage, StudentSearchPage, StudentHistoryPage, StudentSettingsPage, StudentReceiptPage, TeacherDashboardPage, TeacherSettingsPage all have start/destroy.

#### Commit 31: Migrate student dashboard

- [ ] Test: compare canonical template for hero, library card, stats, modals, toast host, IDs, copy, and CDN versions.
- [ ] Run: BorrowerMarkupParityTest; expected FAIL because canonical template is missing.
- [ ] Implement: copy current student-dashboard.html exactly, add page marker/module entry, and split behavior into StudentDashboardPage plus components.
- [ ] Verify: Node page tests, BorrowerMarkupParityTest, and syntax checks pass.
- [ ] Commit: git add frontend/features/student/pages/dashboard backend/tests/Feature/BorrowerMarkupParityTest.php; git commit -m "refactor: migrate student dashboard".

#### Commit 32: Migrate student search

- [ ] Test: assert filters, sorting, book rows, borrow modal, scanner target, query parameters, and empty/error states.
- [ ] Run: BorrowerPagesParityTest and student page tests; expected FAIL.
- [ ] Implement: move student-search.js behavior into StudentSearchPage with StudentSearchService and existing DOM contracts.
- [ ] Verify: focused tests and the existing user modification are preserved in the final diff.
- [ ] Commit: git add frontend/features/student/pages/search frontend/assets/js/pages/student-search.js backend/tests/Feature/BorrowerPagesParityTest.php; git commit -m "refactor: migrate student search page".

#### Commit 33: Migrate history and receipt

- [ ] Test: assert history dates/table/empty state and receipt code/print/error behavior.
- [ ] Run: borrower parity tests; expected FAIL for missing canonical pages.
- [ ] Implement: move student-history.js and receipt.js into bounded page classes with canonical templates.
- [ ] Verify: focused Node tests and BorrowerPagesParityTest pass.
- [ ] Commit: git add frontend/features/student/pages/history frontend/features/student/pages/receipt backend/tests/Feature/BorrowerPagesParityTest.php; git commit -m "refactor: migrate student history and receipt".

#### Commit 34: Migrate student settings

- [ ] Test: assert field population, update action, contact OTP branch, navbar paths, and role label.
- [ ] Run: StudentSettingsMarkupTest; expected FAIL for missing canonical page.
- [ ] Implement: move student-settings.js and template into StudentSettingsPage while preserving CSS/markup.
- [ ] Verify: focused test and settings markup contract pass.
- [ ] Commit: git add frontend/features/student/pages/settings backend/tests/Feature/StudentSettingsMarkupTest.php; git commit -m "refactor: migrate student settings page".

#### Commit 35: Migrate teacher pages

- [ ] Test: assert teacher dashboard/settings markup, due-date inputs, modals, scanner IDs, and teacher-only links.
- [ ] Run: TeacherSettingsMarkupTest; expected FAIL for missing canonical pages.
- [ ] Implement: move teacher-dashboard.js and settings markup into TeacherDashboardPage and TeacherSettingsPage.
- [ ] Verify: focused Node/PHP tests and JS syntax checks pass.
- [ ] Commit: git add frontend/features/teacher/pages backend/tests/Feature/TeacherSettingsMarkupTest.php; git commit -m "refactor: migrate teacher pages".

---

### Task 8: Guest models, services, camera, and auth portal pages

**Files:** Create guest models/services, CameraCaptureComponent, guest auth/portal pages, and tests; modify GuestMarkupParityTest and GuestInteractionParityTest.

**Interfaces:** GuestDashboardService, GuestBrowseService, GuestBorrowingService, GuestProfileService; CameraCaptureComponent start/capture/retake/stop/destroy.

#### Commit 36: Add guest models

- [ ] Test: assert guest summary, book rows, history, borrowed rows, profile fields, photos, and status strings.
- [ ] Run: node --test frontend/tests/guest-services.test.js; expected FAIL.
- [ ] Implement: add pure normalizers preserving pending, released, rejected, and return-verification strings.
- [ ] Verify: focused test passes.
- [ ] Commit: git add frontend/features/guest/models frontend/tests/guest-services.test.js; git commit -m "feat: add guest browser models".

#### Commit 37: Add guest services

- [ ] Test: assert exact dashboard/browse/borrowed/history/borrow-request/return/profile endpoints, query names, and FormData fields.
- [ ] Run: focused guest services test; expected FAIL.
- [ ] Implement: add injected services preserving purpose, book_barcode, return_input, photo_data, and visitor fields.
- [ ] Verify: focused test passes.
- [ ] Commit: git add frontend/features/guest/services frontend/tests/guest-services.test.js; git commit -m "feat: add guest portal services".

#### Commit 38: Extract camera capture

- [ ] Test: assert camera constraints, JPEG quality .85, IDs, start/capture/retake/stop, error copy, and stream cleanup.
- [ ] Run: node --test frontend/tests/camera.test.js; expected FAIL.
- [ ] Implement: move guest/camera-capture.js into CameraCaptureComponent with destroy cleanup and injected media APIs.
- [ ] Verify: focused test and existing media contracts pass.
- [ ] Commit: git add frontend/app/shared/components/camera-capture frontend/tests/camera.test.js; git commit -m "refactor: extract camera capture component".

#### Commit 39: Migrate guest registration and OTP templates

- [ ] Test: assert guest registration, guest OTP, and profile OTP structure and module contracts.
- [ ] Run: guest markup/interaction tests; expected FAIL.
- [ ] Implement: move canonical templates and page modules into guest feature folders, injecting the camera component and AuthService.
- [ ] Verify: focused tests and GuestMarkupParityTest pass.
- [ ] Commit: git add frontend/features/guest/pages/registration frontend/features/guest/pages/otp frontend/features/guest/pages/profile-otp backend/tests/Feature/GuestMarkupParityTest.php; git commit -m "refactor: migrate guest auth portal pages".

#### Commit 40: Add guest page registry

- [ ] Test: assert guest bootstrap registers every guest page with its correct factory and no auth-only page.
- [ ] Run: node --test frontend/tests/guest-pages.test.js; expected FAIL.
- [ ] Implement: add guest page registry/factories for registration, OTP, profile OTP, dashboard, profile, browse, borrowed, history, borrow request, return, pass, and receipt.
- [ ] Verify: focused Node tests and syntax checks pass.
- [ ] Commit: git add frontend/app/bootstrap/guest-page.js frontend/features/guest/pages frontend/tests/guest-pages.test.js; git commit -m "feat: register guest feature pages".

---

### Task 9: Guest portal pages and receipts

**Files:** Create guest dashboard/profile/browse/borrowed/history/borrow-request/return/pass/receipt templates and page modules; modify guest parity tests.

**Interfaces:** Each Guest*Page is bounded, service-injected, and preserves current route links, IDs, copy, and state transitions.

#### Commit 41: Migrate guest dashboard/profile

- [ ] Test: assert dashboard summary, navbar root, scanner controls, profile fields, success/error nodes, and media URLs.
- [ ] Run: GuestPortalContractTest; expected FAIL.
- [ ] Implement: copy templates and move dashboard.js/profile.js behavior into GuestDashboardPage and GuestProfilePage.
- [ ] Verify: focused tests pass.
- [ ] Commit: git add frontend/features/guest/pages/dashboard frontend/features/guest/pages/profile backend/tests/Feature/GuestPortalContractTest.php; git commit -m "refactor: migrate guest dashboard and profile".

#### Commit 42: Migrate browse/borrowed/history

- [ ] Test: assert filters, cards, rows, date query, empty states, image URLs, and navigation.
- [ ] Run: GuestMarkupParityTest; expected FAIL.
- [ ] Implement: move browse.js, borrowed.js, and history.js into focused page classes using guest services and safe rendering.
- [ ] Verify: focused tests pass.
- [ ] Commit: git add frontend/features/guest/pages/browse frontend/features/guest/pages/borrowed frontend/features/guest/pages/history backend/tests/Feature/GuestMarkupParityTest.php; git commit -m "refactor: migrate guest catalog and history".

#### Commit 43: Migrate borrow and return request flows

- [ ] Test: assert purpose toggle, barcode query, camera/photo review, return verification, fields, errors, and success redirects.
- [ ] Run: GuestInteractionParityTest; expected FAIL.
- [ ] Implement: move borrow-request.js and return-book.js into page classes using CameraCaptureComponent and GuestBorrowingService.
- [ ] Verify: focused tests pass.
- [ ] Commit: git add frontend/features/guest/pages/borrow-request frontend/features/guest/pages/return-book backend/tests/Feature/GuestInteractionParityTest.php; git commit -m "refactor: migrate guest borrow and return flows".

#### Commit 44: Migrate guest pass

- [ ] Test: assert government-ID/pass fields, barcode output, print controls, photo viewer, and navigation.
- [ ] Run: guest interaction tests; expected FAIL.
- [ ] Implement: move guest/pass.js and pass template into GuestPassPage with existing media and print behavior.
- [ ] Verify: focused tests and syntax checks pass.
- [ ] Commit: git add frontend/features/guest/pages/pass frontend/tests/guest-pages.test.js; git commit -m "refactor: migrate guest pass page".

#### Commit 45: Migrate guest receipt

- [ ] Test: assert receipt query, ownership errors, print behavior, transaction code, rows, and navigation.
- [ ] Run: guest portal tests; expected FAIL.
- [ ] Implement: move guest/receipt.js and template into GuestReceiptPage using the receipt service.
- [ ] Verify: full guest contract tests pass.
- [ ] Commit: git add frontend/features/guest/pages/receipt backend/tests/Feature/GuestPortalContractTest.php; git commit -m "refactor: migrate guest receipt page".

---

### Task 10: Staff dashboard, inventory, and borrowers

**Files:** Create staff models/services/pages/components for overview charts, inventory/book drawer, borrowers, and borrower detail; modify staff/inventory scripts and staff contracts.

**Interfaces:** StaffDashboardService, StaffApprovalService, InventoryService, BorrowerService; StaffDashboardPage, InventoryPage, BorrowersPage, BorrowerDetailPage; OverviewChartComponent and BookDrawerComponent.

#### Commit 46: Extract staff dashboard services/models

- [ ] Test: assert stats, overview, pending approvals, approval/rejection fields, and five-second polling.
- [ ] Run: node --test frontend/tests/staff-pages.test.js; expected FAIL.
- [ ] Implement: split StaffApi calls into StaffDashboardService and StaffApprovalService without changing response keys.
- [ ] Verify: focused tests and StaffDashboardFrontendContractTest pass.
- [ ] Commit: git add frontend/features/staff/models frontend/features/staff/services backend/tests/Feature/StaffDashboardFrontendContractTest.php; git commit -m "refactor: extract staff dashboard services".

#### Commit 47: Extract overview chart

- [ ] Test: assert activity, category trend, status, categories, genres, top borrowers, and recent activity IDs, SVG dimensions, labels, palette, and empty copy.
- [ ] Run: staff page tests; expected FAIL.
- [ ] Implement: move staff chart render methods into OverviewChartComponent.
- [ ] Verify: focused tests and visual contract tests pass.
- [ ] Commit: git add frontend/features/staff/components/overview-chart frontend/tests/staff-pages.test.js; git commit -m "refactor: extract staff overview chart".

#### Commit 48: Migrate staff dashboard

- [ ] Test: assert all dashboard cards, overview sections, approval modal, notification host, copy, and page marker.
- [ ] Run: StaffDashboardMarkupTest; expected FAIL.
- [ ] Implement: copy dashboard template and compose StaffDashboardPage with chart, approval, notification, session, and toast dependencies.
- [ ] Verify: focused Node/PHP tests pass.
- [ ] Commit: git add frontend/features/staff/pages/dashboard backend/tests/Feature/StaffDashboardMarkupTest.php; git commit -m "refactor: migrate staff dashboard".

#### Commit 49: Extract inventory service/drawer and migrate inventory

- [ ] Test: assert filters, sort/pagination, uploads, cover preview, drawer fields, bulk actions, and exact confirmation/errors.
- [ ] Run: inventory tests; expected FAIL.
- [ ] Implement: split inventory.js into InventoryService, BookDrawerComponent, and InventoryPage while preserving markup.
- [ ] Verify: InventoryMarkupParityTest and InventoryBrowserParityTest pass.
- [ ] Commit: git add frontend/features/staff/services/inventory.service.js frontend/features/staff/components/book-drawer frontend/features/staff/pages/inventory backend/tests/Feature/InventoryMarkupParityTest.php backend/tests/Feature/InventoryBrowserParityTest.php; git commit -m "refactor: migrate staff inventory".

#### Commit 50: Extract borrower service and migrate borrower pages

- [ ] Test: assert borrower search/detail/history/photo upload, media rendering, permissions, and error states.
- [ ] Run: staff borrower tests; expected FAIL.
- [ ] Implement: split staff borrower methods into BorrowerService, BorrowersPage, and BorrowerDetailPage.
- [ ] Verify: focused tests, full staff markup tests, and JS syntax checks pass.
- [ ] Commit: git add frontend/features/staff/services/borrower.service.js frontend/features/staff/pages/borrowers frontend/features/staff/pages/borrower-detail frontend/tests/staff-borrowers.test.js; git commit -m "refactor: migrate staff borrower workflows".

---

### Task 11: Staff utilities and admin pages

**Files:** Create report/notification/admin services and pages for reports, overdue, notifications, guest requests, staff management, and API docs. Modify existing staff/api-docs modules and contracts.

**Interfaces:** ReportService load/csv/print; NotificationService poll/markViewed/send; AdminStaffService list/promote/updateStatus/resetPassword.

#### Commit 51: Extract report and notification services

- [ ] Test: assert report type/from/to, CSV/print flags, notification polling, viewed state, and send form fields.
- [ ] Run: staff utility service tests; expected FAIL.
- [ ] Implement: move exact endpoint/query construction from staff.js into services.
- [ ] Verify: StaffReportsContractTest and Node tests pass.
- [ ] Commit: git add frontend/features/staff/services/report.service.js frontend/features/staff/services/notification.service.js backend/tests/Feature/StaffReportsContractTest.php; git commit -m "refactor: extract staff utility services".

#### Commit 52: Migrate reports and overdue pages

- [ ] Test: assert report controls, table IDs, print CSS, CSV links, overdue refresh, empty state, and copy.
- [ ] Run: staff utility page tests; expected FAIL.
- [ ] Implement: move report and overdue branches into ReportsPage and OverduePage with canonical templates.
- [ ] Verify: focused tests pass.
- [ ] Commit: git add frontend/features/staff/pages/reports frontend/features/staff/pages/overdue backend/tests/Feature/StaffReportsContractTest.php; git commit -m "refactor: migrate staff reports and overdue".

#### Commit 53: Migrate notifications and guest requests

- [ ] Test: assert notification form/polling/modal behavior and guest approval/rejection/photo review behavior.
- [ ] Run: staff utility and guest interaction tests; expected FAIL.
- [ ] Implement: add NotificationsPage and GuestRequestsPage with existing polling interval, modal IDs, and messages.
- [ ] Verify: focused tests pass.
- [ ] Commit: git add frontend/features/staff/pages/notifications frontend/features/staff/pages/guest-requests frontend/tests/staff-utility-pages.test.js; git commit -m "refactor: migrate staff notifications and guest requests".

#### Commit 54: Migrate admin staff management

- [ ] Test: assert admin-only actions, promotion/status/password forms, borrower list, errors, and confirmation behavior.
- [ ] Run: admin staff tests; expected FAIL.
- [ ] Implement: add AdminStaffService and StaffManagementPage from the staff.js branch.
- [ ] Verify: focused tests and admin route contracts pass.
- [ ] Commit: git add frontend/features/staff/services/admin-staff.service.js frontend/features/staff/pages/staff-management frontend/tests/staff-utility-pages.test.js; git commit -m "refactor: migrate admin staff management".

#### Commit 55: Migrate API docs

- [ ] Test: assert docs grouping/search/rendering, endpoint count, admin-only link, and API docs CSS.
- [ ] Run: ApiDocumentationMarkupTest; expected FAIL.
- [ ] Implement: move api-docs.js into ApiDocsPage and preserve endpoint catalog rendering.
- [ ] Verify: focused tests, all staff tests, and syntax checks pass.
- [ ] Commit: git add frontend/features/staff/pages/api-docs backend/tests/Feature/ApiDocumentationMarkupTest.php; git commit -m "refactor: migrate API documentation page".

---

### Task 12: Canonical templates, cutover, parity, and handoff

**Files:** Modify PageRouteTable, ApplicationFactory, .htaccess, all canonical HTML script tags, CSS contract tests, route/page tests, README. Create browser parity script and cutover report. Delete only verified duplicate legacy files.

**Interfaces:** PageRouteTable accepts frontend root; each clean route maps to one feature template; every page has one marker and one module entrypoint; browser parity script is read-only.

#### Commit 56: Update route template mappings

- [ ] Test: assert representative public/student/staff/guest routes resolve to feature templates and retain policies.
- [ ] Run: PageRouteTableTest; expected FAIL while routes point to frontend/pages.
- [ ] Implement: pass frontend root from ApplicationFactory and use an explicit feature template map in PageRouteTable.
- [ ] Verify: PageRouteTableTest, PageGatewayTest, and CSRF tests pass.
- [ ] Commit: git add backend/src/Http/Routing/PageRouteTable.php backend/src/Bootstrap/ApplicationFactory.php backend/tests/Feature/PageRouteTableTest.php; git commit -m "refactor: map routes to canonical feature templates".

#### Commit 57: Update all PHP page contracts

- [ ] Test: replace hardcoded frontend/pages reads with FrontendPagePaths::path and preserve every markup/style/icon/auth assertion.
- [ ] Run: filtered page, visual, auth, guest, borrower, staff, and API docs tests; expected FAIL for stale paths.
- [ ] Implement: update test path helpers and completeness lists without removing assertions or weakening parity.
- [ ] Verify: all frontend-related PHPUnit tests pass.
- [ ] Commit: git add backend/tests/Feature backend/tests/Support/FrontendPagePaths.php; git commit -m "test: retarget frontend contracts to feature templates".

#### Commit 58: Cut over HTML modules and protect sources

- [ ] Test: assert every canonical page has one data-app-page marker, one type=module bootstrap, required CSS/CDN assets, and no obsolete direct page/core script.
- [ ] Run: FrontendModuleLayoutTest and SourceAccessTest; expected FAIL for classic scripts and source rules.
- [ ] Implement: update all canonical auth/student/teacher/guest/staff templates, extend .htaccess denial to frontend/app and frontend/features, and keep frontend/assets directly served.
- [ ] Verify: module/layout/source/visual tests and JS syntax checks pass.
- [ ] Commit: git add frontend/features .htaccess backend/tests/Feature/FrontendModuleLayoutTest.php backend/tests/Feature/SourceAccessTest.php; git commit -m "security: cut pages to modules and protect source".

#### Commit 59: Add served browser parity and remove verified duplicates

- [ ] Test: create FrontendParityMatrixTest and tests/browser/frontend-module-parity.ps1 for public/protected routes, module markup, source denial, and public assets.
- [ ] Run: phpunit filtered to FrontendParityMatrixTest and the PowerShell browser script; expected FAIL until served pages are verified.
- [ ] Implement: add explicit status/redirect checks and remove only legacy files proven unused by the matrix and route table.
- [ ] Verify: full PHPUnit, Node tests, syntax checks, browser parity when Apache is available, and git diff --check pass.
- [ ] Commit: git add -A frontend backend/tests tests/browser; git commit -m "refactor: remove verified legacy frontend entrypoints".

#### Commit 60: Final documentation and implementation audit

- [ ] Test: assert README and frontend/parity/cutover-report.md document native modules, Apache workflow, route protection, test commands, rollback, every route, and parity evidence.
- [ ] Run: full Node/PHP/PHPStan/browser gates; expected PASS with no failures, warnings, risky tests, or PHPStan errors.
- [ ] Implement: record exact command results, known environment limitations, final route matrix status, visual parity status, and the protected user modifications. Inspect git log to confirm 60 non-empty implementation commits after design commit 33a0626.
- [ ] Verify: git status --short, git diff --check, git rev-list --count 33a0626..HEAD, and final cutover report.
- [ ] Commit: git add README.md frontend/parity docs/superpowers/plans/2026-08-29-angular-like-vanilla-frontend-plan.md; git commit -m "chore: finalize frontend refactor verification".

## Final quality gates

Run from C:\xampp\htdocs\scan2borrow:

    npm test
    Get-ChildItem frontend -Recurse -Filter *.js | ForEach-Object { node --check $_.FullName }
    C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml --testdox
    C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon
    powershell -ExecutionPolicy Bypass -File tests/browser/frontend-module-parity.ps1 -BaseUrl http://localhost/scan2borrow
    git diff --check
    git status --short

Expected final evidence: all applicable tests pass; all canonical routes have explicit policies and feature templates; protected pages do not expose HTML before authorization; public assets remain reachable; no intentional UI/UX drift is recorded; the implementation history contains exactly 60 substantive implementation commits; and the two pre-existing user modifications were preserved or explicitly migrated in their own reviewable slices.

## Plan self-review

- Coverage: native modules, app/shared/features boundaries, API/session/layout infrastructure, reusable components, all auth/student/teacher/guest/staff pages, route delivery, source protection, CSS compatibility, parity tests, browser checks, rollback, documentation, and the required commit range are all assigned to tasks.
- Placeholder scan: no TBD, TODO, FIXME, or vague implement-later steps are present. Every commit names files, a test, a command, an implementation boundary, and a commit message.
- Interface consistency: ApiClient precedes service tasks; SessionService precedes bootstraps; component lifecycle is consistent; feature services are injected into page classes; PageRoute signatures remain unchanged.
- Scope: no backend business-rule redesign or intentional UI/UX change is included.
- Safety: deletion is delayed until Commit 59 parity is green; direct source access remains denied; existing user changes are protected.
