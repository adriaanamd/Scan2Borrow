# Angular-Like Vanilla Frontend Architecture Design

## Status

Approved in conversation on 2026-08-29. This document describes the frontend-only architecture refactor. It does not authorize a visual redesign or a change to user-facing behavior.

## Goal

Reorganize the Scan2Borrow frontend so its structure and dependency boundaries resemble an Angular application while remaining vanilla HTML, CSS, and JavaScript. The result must be easier to maintain through feature folders, native ES modules, reusable components, focused services, and page-level composition roots.

The refactor changes internal organization only. Existing UI, UX, routes, authentication, authorization, API contracts, and workflows remain the compatibility baseline.

## Non-negotiable constraints

- Remain framework-free: vanilla HTML, CSS, and JavaScript only.
- Use native browser ES modules with `import` and `export`; no bundler is required.
- Preserve all existing clean URLs, redirects, session behavior, CSRF behavior, API payloads, response shapes, and user-facing messages.
- Preserve existing UI/UX: layout, typography, colors, spacing, responsive behavior, Bootstrap integration, copy, icons, forms, modals, drawers, alerts, toasts, loading states, empty states, printing, camera, barcode, and upload flows.
- Preserve existing DOM IDs, form names, query parameters, `data-*` attributes, and accessibility affordances wherever current behavior or tests depend on them.
- Do not convert the application into a client-side SPA or replace server-side page authorization with browser logic.
- Do not redesign the database or change backend business rules as part of this frontend refactor.
- Existing uncommitted user changes in `frontend/assets/js/pages/registration.js` and `frontend/assets/js/pages/student-search.js` must remain intact unless a later migration slice explicitly includes them.
- The implementation must contain at least 50 and at most 70 meaningful, non-empty implementation commits. Artificial progress, empty commits, unrelated formatting-only commits, and commit splitting without a reviewable boundary are not allowed.

## Current context

The repository already contains:

- Static HTML templates under `frontend/pages`.
- Shared `app-navbar.js`, `auth-brand.js`, icon, media, scanner, and camera utilities.
- Feature behavior distributed across `frontend/assets/js/pages`, `frontend/assets/js/guest`, and `frontend/assets/js/core`.
- Large mixed-responsibility controllers, especially `staff.js`, `inventory.js`, and `borrower-dashboard.js`.
- Contract and parity tests under `backend/tests/Feature` that assert routes, markup, shared styles, icons, auth brand, navbar behavior, and page completeness.
- Existing clean route delivery through the PHP backend page gateway.

The refactor must build on these working shared pieces and migrate them incrementally rather than restarting the frontend.

## Target directory structure

```text
frontend/
  app/
    bootstrap/
      auth-page.js
      student-page.js
      teacher-page.js
      staff-page.js
      guest-page.js
    core/
      api/
        api-client.js
        api-error.js
      auth/
        session.service.js
        session.guard.js
      layout/
        app-shell.component.js
      services/
        modal.service.js
        notification.service.js
        toast.service.js
      utils/
        dom.js
        formatters.js
        security.js
    shared/
      components/
        app-navbar/
        auth-brand/
        barcode-scanner/
        camera-capture/
        data-table/
        empty-state/
        loading-state/
        toast-host/
      models/
  features/
    auth/
      pages/
        login/
        register/
        borrower-otp/
        guest-register/
        guest-otp/
        staff-login/
      components/
      services/
    student/
      pages/
        dashboard/
        search/
        history/
        settings/
        receipt/
      components/
      services/
    teacher/
      pages/
        dashboard/
        settings/
      components/
      services/
    staff/
      pages/
        dashboard/
        inventory/
        borrowers/
        borrower-detail/
        overdue/
        reports/
        notifications/
        staff-management/
        guest-requests/
        api-docs/
      components/
      services/
    guest/
      pages/
        dashboard/
        profile/
        profile-otp/
        browse/
        borrowed/
        history/
        borrow-request/
        return-book/
        pass/
        receipt/
      components/
      services/
  assets/
    css/
    images/
```

Each page directory owns its page template, page module, and page-specific styles when styles can be moved without changing specificity or rendering. `frontend/assets/css/style.css` remains the global compatibility stylesheet until the complete visual system has been proven equivalent. Existing specialized stylesheets can be moved only with parity coverage.

The backend page route table will be updated to resolve canonical templates from the new feature locations. Clean URLs and access policies remain unchanged. There will be one canonical template per page; duplicate legacy templates will not become a second source of truth. Vanilla browser modules are necessarily public resources, so the server protects HTML template delivery and protected routes rather than pretending client JavaScript can be secret.

## Module and dependency boundaries

The browser layer uses one native module entrypoint per page. A page entrypoint imports only the feature page and the shared/core dependencies it needs.

Responsibilities are defined as follows:

- `app/core`: application-wide infrastructure with no feature-specific business rules.
- `app/shared`: reusable UI primitives and data helpers that are safe for multiple features.
- `features/*`: domain-area behavior and page composition for auth, student, teacher, staff, or guest workflows.
- `pages/*`: page-level composition, DOM root selection, lifecycle ownership, and event wiring.
- `components/*`: focused DOM behavior with a bounded root element and explicit inputs/callbacks.
- `services/*`: API workflows, normalization, and feature use cases; components do not own raw endpoint details.
- `models/*`: documented data shapes and normalization helpers for API responses; JavaScript remains untyped at runtime and uses JSDoc where helpful.

Reusable components expose a consistent lifecycle:

```text
constructor(root, options)
start()
destroy()
```

Components must not query or mutate unrelated page roots. Component communication uses callbacks or scoped `CustomEvent`s instead of global mutable state. Event listeners must be removable so repeated initialization cannot create duplicate handlers.

The existing navbar and auth-brand implementations become shared components. Other extractions will include toast, modal, loading, empty, table, camera, and scanner behavior only where the current pages demonstrate the same contract.

## Data flow

```text
Page module
    -> feature page controller
        -> feature service
            -> shared ApiClient
                -> existing backend API
```

`ApiClient` centralizes same-origin requests, CSRF handling, JSON parsing, API envelope handling, and normalized errors while retaining compatibility keys and messages. `SessionService` owns session retrieval and cached identity data. `SessionGuard` handles expired sessions after delivery; it never replaces server-side authorization.

Feature services return normalized data to page controllers. Controllers pass values to components. Rendering preserves current IDs and DOM contracts, and safe DOM helpers are preferred over unescaped HTML interpolation.

## Page bootstrap convention

Each page will declare a stable page marker and load one module entrypoint:

```html
<body data-app-page="student-dashboard">
  <script type="module" src="/scan2borrow/frontend/app/bootstrap/student-page.js"></script>
</body>
```

The bootstrap module selects the page registry entry, constructs the page with its dependencies, and starts it after module evaluation. Page-specific modules remain isolated; a staff page must not load student or guest feature modules.

Existing CDN dependencies such as Bootstrap, JsBarcode, and html5-qrcode remain at their current versions and loading behavior unless a compatibility test proves an equivalent replacement is necessary.

## Migration strategy

The migration is incremental and behavior-first:

1. Characterize current page markup, scripts, API calls, routes, and interactions with focused tests.
2. Add the module runtime and shared infrastructure without changing page output.
3. Migrate the shared navbar, auth brand, API, session, toast, modal, media, scanner, and camera boundaries.
4. Migrate auth pages, then student and teacher pages, then guest pages, then staff pages.
5. Split oversized controllers by responsibility while keeping their public behavior and DOM contracts unchanged.
6. Update backend template mappings only after each canonical page passes its parity checks.
7. Remove duplicate legacy frontend files only after route, markup, interaction, and visual parity is verified.

No step may combine an architectural move with an intentional UI change. If an existing bug is discovered, it is recorded separately and fixed only with explicit scope and regression coverage.

## Verification and parity

Every migrated surface must preserve:

- Static page completeness and clean-route delivery.
- Role and guest access policy behavior.
- Required HTML structure, IDs, names, labels, copy, links, and data attributes.
- Successful and failed API flows, including exact compatibility response keys and user-visible messages.
- Bootstrap modal/drawer behavior, form submission behavior, and loading/error/empty states.
- Responsive layout and representative screenshot output.

Each migration slice follows test-first verification:

```text
write focused failing test
-> run and observe expected failure
-> implement smallest structural change
-> run focused and relevant regression tests
-> run browser/visual parity check
-> commit the meaningful slice
```

Final quality gates include PHP syntax checks, PHPUnit, PHPStan, frontend contract tests, clean-route access checks, representative browser flows, `git diff --check`, and inspection of the final status. Existing visual-system tests remain authoritative for shared palette, shell, icons, auth layout, and favicon contracts.

## Commit strategy

The implementation will target approximately 60 meaningful commits, within the required 50–70 range. The design/specification commit is additional and does not count toward the implementation minimum.

Expected implementation groups:

```text
Module foundation and page bootstrap       6–8 commits
Core API, session, auth, and layout        8–10 commits
Shared components and utilities            8–10 commits
Auth, student, and teacher migration       10–12 commits
Guest migration and camera workflows       8–10 commits
Staff migration and inventory workflows    10–12 commits
Parity, cleanup, and documentation         6–8 commits
```

Each commit must contain a test, implementation boundary, migration boundary, or verified documentation change that a reviewer can understand independently. Commit messages will describe the behavior or boundary delivered, not merely the files touched.

## Rollback and safety

Migration slices remain independently reversible while parity is being established. The backend route table will retain explicit page-to-template mappings so a failed page can temporarily point to its previous canonical template without deleting source or resetting data. No destructive cleanup occurs until the replacement page has passed its relevant verification gates.

## Success criteria

The refactor is complete when:

- The frontend is organized into `app`, `shared`, and feature folders with native ES module imports.
- Every page has a focused page module and only the dependencies it needs.
- Shared navbar/auth-brand and repeated UI behavior are reusable components.
- Oversized mixed-responsibility controllers have been split into focused page, component, and service units.
- Existing routes, API contracts, authentication, workflows, UI, UX, and responsive behavior remain equivalent.
- All parity and quality gates pass.
- The implementation history contains 50–70 meaningful, non-empty implementation commits.
