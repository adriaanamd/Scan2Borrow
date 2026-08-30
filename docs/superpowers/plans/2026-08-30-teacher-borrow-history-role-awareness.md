# Teacher Borrow and History Role-Awareness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the teacher account’s Borrow and History tabs use teacher-canonical routes, teacher-aware copy/styles, and teacher-named API paths while preserving student behavior and backward compatibility.

**Architecture:** Keep one shared catalog/history implementation where the data and table contracts are already equivalent, but give teachers explicit /teacher/borrow and /teacher/history page aliases and role-specific runtime configuration. Add teacher-named backend API aliases to the existing authorized handlers, then select those aliases from the role-aware frontend controller. Update the shared navbar to emit role-correct destinations and labels.

**Tech Stack:** Framework-free PHP route tables/controllers, static HTML, scoped CSS, browser JavaScript modules, Bootstrap 5.3.3, and Node’s native node:test suite with PHPUnit feature tests.

## Global Constraints

- Preserve student /student/search, /student/history, /api/student/books, /api/student/borrow, /api/student/borrow/lookup, and /api/student/history behavior.
- Add teacher routes and API aliases by reusing the existing authorization and service handlers; do not duplicate borrowing or history business logic.
- Keep teacher presentation scoped to .teacher-search-page, .teacher-history-page, or other explicit teacher content scopes; do not style the shared sidebar globally.
- Keep existing IDs, form fields, cart payloads, table columns, escaped dynamic values, status/date/quantity formatting, and CSRF behavior intact.
- Existing teacher Borrow modal behavior on /teacher/dashboard remains unchanged; this plan addresses the Borrow/catalog tab currently routed through the student search page and the History tab.
- Retain the legacy student paths as compatibility paths; the shared pages must still resolve the authenticated role so a teacher visiting an old bookmarked path does not receive student presentation.
- No database, dependency, or framework changes.

## Root Cause Evidence

- frontend/assets/js/core/app-navbar.js hardcodes /student/search and /student/history for both student and teacher roles.
- backend/src/Http/Routing/PageRouteTable.php has no teacher Borrow or History page paths; the student templates are allowed to serve both roles.
- frontend/features/student/pages/search/student-search.page.js hardcodes the student books, lookup, borrow, and redirect endpoints.
- frontend/features/student/pages/history/student-history.page.js already has a partial teacher CSS scope, but the page begins as a student page and always requests /api/student/history.
- backend/src/Http/Routing/BorrowerRouteTable.php and BookRouteTable.php already route to handlers that authorize both students and teachers, so teacher-named aliases can share those handlers safely.

## File Map

- Modify frontend/assets/js/core/app-navbar.js for role-specific Borrow and History destinations and labels.
- Modify backend/src/Http/Routing/PageRouteTable.php to add /teacher/borrow and /teacher/history aliases mapped to the existing feature-owned templates.
- Modify backend/src/Http/Routing/BookRouteTable.php to add the teacher-named catalog API alias and retain its existing teacher lookup alias.
- Modify backend/src/Http/Routing/BorrowerRouteTable.php to add teacher-named borrow and history API aliases.
- Modify frontend/features/student/pages/search/search.html to expose neutral base hooks and load teacher search styling.
- Modify frontend/features/student/pages/search/student-search.page.js to resolve the role before loading, select teacher/student endpoints, and apply role-aware copy/classes.
- Create frontend/assets/css/teacher-search.css for the teacher catalog/Borrow surface using the existing Swiss teacher tokens.
- Modify frontend/features/student/pages/history/history.html to expose neutral base state and role-aware content hooks.
- Modify frontend/features/student/pages/history/student-history.page.js to resolve route/session role synchronously where possible, select the role-specific history endpoint, and preserve teacher/student rendering boundaries.
- Modify frontend/assets/css/teacher-history.css only where needed to complete the teacher role scope; preserve the existing Swiss ledger rules.
- Modify frontend/parity/page-matrix.md to document the canonical teacher routes and shared implementation ownership.
- Modify frontend/tests/navbar-cache.test.js, frontend/tests/student-library-surfaces.test.js, and frontend/tests/teacher-borrow-history-surfaces.test.js.
- Modify backend/tests/Feature/CleanRouteMatrixTest.php and backend/tests/Feature/PageRouteTableTest.php.
- Create backend/tests/Feature/TeacherBorrowHistoryRoutingTest.php for source-level route alias contracts.

## Task 1: Lock the failing teacher navigation contract

**Files:**
- Modify: frontend/tests/navbar-cache.test.js
- Modify: frontend/tests/teacher-borrow-history-surfaces.test.js

**Interfaces:**
- Consumes: Existing AppNavbar VM harness and teacher surface source tests.
- Produces: Regression coverage requiring teacher navigation to expose /teacher/borrow and /teacher/history while student navigation keeps /student/search and /student/history.

- [ ] **Step 1: Write the failing tests**

Add an AppNavbar render test that creates a teacher navbar root, calls navbar.render('teacher'), and asserts the rendered HTML contains href="/scan2borrow/teacher/borrow" and href="/scan2borrow/teacher/history", while it does not contain the student Search/History hrefs. Add source contracts for teacher route/API markers in teacher-borrow-history-surfaces.test.js.

- [ ] **Step 2: Run the focused tests to verify they fail**

~~~powershell
node --test frontend/tests/navbar-cache.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
~~~

Expected: FAIL because renderBorrower('teacher') currently emits the student Search/History paths.

- [ ] **Step 3: Commit the failing tests**

~~~powershell
git add frontend/tests/navbar-cache.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
git commit -m "test: define teacher borrower navigation contract"
~~~

## Task 2: Add canonical teacher page and API route aliases

**Files:**
- Modify: backend/src/Http/Routing/PageRouteTable.php
- Modify: backend/src/Http/Routing/BookRouteTable.php
- Modify: backend/src/Http/Routing/BorrowerRouteTable.php
- Modify: backend/tests/Feature/CleanRouteMatrixTest.php
- Modify: backend/tests/Feature/PageRouteTableTest.php
- Create: backend/tests/Feature/TeacherBorrowHistoryRoutingTest.php

**Interfaces:**
- Consumes: Existing studentSearch, borrowLookup, BorrowerController::history, and BorrowerController::change handlers.
- Produces: /teacher/borrow, /teacher/history, /api/teacher/books, /api/teacher/borrow, and /api/teacher/history, with the existing /api/teacher/borrow/lookup alias retained; all are protected by existing role checks.

- [ ] **Step 1: Write route-policy and alias tests**

Extend the page route tests with:

~~~php
self::assertSame(['teacher'], $table->forPath('/teacher/borrow')->allowedRoles());
self::assertSame(['teacher'], $table->forPath('/teacher/history')->allowedRoles());
~~~

Add source-level assertions in TeacherBorrowHistoryRoutingTest.php that BookRouteTable contains /api/teacher/books and its existing /api/teacher/borrow/lookup alias, and BorrowerRouteTable contains /api/teacher/borrow and /api/teacher/history. Add the teacher page paths to the route list and frontend matrix assertions in CleanRouteMatrixTest.php.

- [ ] **Step 2: Run the backend route tests to verify they fail**

~~~powershell
vendor/bin/phpunit backend/tests/Feature/CleanRouteMatrixTest.php backend/tests/Feature/PageRouteTableTest.php backend/tests/Feature/TeacherBorrowHistoryRoutingTest.php
~~~

Expected: FAIL because the teacher page/API aliases are not registered.

- [ ] **Step 3: Add the page aliases**

Register these entries in PageRouteTable:

~~~php
'/teacher/borrow' => new PageRoute(
    '/teacher/borrow',
    $featurePath('student/pages/search/search.html'),
    ['teacher'],
),
'/teacher/history' => new PageRoute(
    '/teacher/history',
    $featurePath('student/pages/history/history.html'),
    ['teacher'],
),
~~~

The aliases intentionally reuse the shared templates; the frontend controller selects the role presentation from route/session context.

- [ ] **Step 4: Add the API aliases**

Register these routes without creating new controller methods:

~~~php
// In BookRouteTable::routes(BookController $controller, BookCopyController $copyController):
Route::create('GET', '/api/teacher/books', [$controller, 'studentSearch']),
// Keep the existing teacher lookup route in place:
Route::create('GET', '/api/teacher/borrow/lookup', [$controller, 'borrowLookup']),

// In BorrowerRouteTable::routes(BorrowerController $controller):
Route::create('POST', '/api/teacher/borrow', [$controller, 'change']),
Route::create('GET', '/api/teacher/history', [$controller, 'history']),
~~~

Use the existing route-table constructor variables. The handlers continue to authorize against the authenticated session role, so the aliases do not grant cross-role access.

- [ ] **Step 5: Run the route tests to verify they pass**

Run the same PHPUnit command from Step 2. Expected: PASS, including the existing student route assertions.

- [ ] **Step 6: Commit the route aliases**

~~~powershell
git add backend/src/Http/Routing/PageRouteTable.php backend/src/Http/Routing/BookRouteTable.php backend/src/Http/Routing/BorrowerRouteTable.php backend/tests/Feature/CleanRouteMatrixTest.php backend/tests/Feature/PageRouteTableTest.php backend/tests/Feature/TeacherBorrowHistoryRoutingTest.php
git commit -m "feat: add teacher borrower route aliases"
~~~

## Task 3: Make the shared navbar role-aware

**Files:**
- Modify: frontend/assets/js/core/app-navbar.js
- Modify: frontend/tests/navbar-cache.test.js
- Modify: backend/tests/Feature/RoleNavbarContractTest.php

**Interfaces:**
- Consumes: renderBorrower(role) and the route aliases from Task 2.
- Produces: A teacher sidebar with teacher-specific Borrow and History URLs/labels and a student sidebar with its current URLs/labels.

- [ ] **Step 1: Implement the smallest navigation change**

Replace the hardcoded borrower destinations with role-derived values:

~~~js
const teacher = role === "teacher";
const catalogPath = teacher
  ? "/scan2borrow/teacher/borrow"
  : "/scan2borrow/student/search";
const historyPath = teacher
  ? "/scan2borrow/teacher/history"
  : "/scan2borrow/student/history";
const catalogLabel = teacher ? "Borrow Books" : "Search Books";
const historyLabel = teacher ? "Borrowing History" : "My History";
~~~

Use these values for href and data-nav-path. Keep settings/dashboard destinations and logout metadata unchanged. Update roleMatchesCurrentPath so the new teacher aliases are recognized as teacher routes.

- [ ] **Step 2: Run the focused navigation tests**

~~~powershell
node --test frontend/tests/navbar-cache.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
~~~

Expected: PASS for teacher and student destination assertions.

- [ ] **Step 3: Commit the navbar change**

~~~powershell
git add frontend/assets/js/core/app-navbar.js frontend/tests/navbar-cache.test.js backend/tests/Feature/RoleNavbarContractTest.php
git commit -m "fix: route borrower navigation by role"
~~~

## Task 4: Make the Borrow/catalog page role-aware

**Files:**
- Modify: frontend/features/student/pages/search/search.html
- Modify: frontend/features/student/pages/search/student-search.page.js
- Create: frontend/assets/css/teacher-search.css
- Modify: frontend/tests/student-library-surfaces.test.js
- Modify: frontend/tests/teacher-borrow-history-surfaces.test.js

**Interfaces:**
- Consumes: The shared search template, BulkBorrowCart, and the teacher API aliases from Task 2.
- Produces: A controller that chooses role-specific catalog/lookup/borrow/redirect paths and classes while retaining the StudentSearchPage export and all existing DOM IDs.

- [ ] **Step 1: Write the failing role-aware catalog tests**

Require the search template to load teacher-search.css, expose neutral role-copy hooks such as data-role-copy="catalog-eyebrow", and retain searchForm, book-results, borrowFormModal, bulkBorrowItems, and bulkBorrowCount. Require the controller to contain teacher/student endpoint selection and teacher-search-page/student-search-page scopes.

- [ ] **Step 2: Run the focused tests to verify they fail**

~~~powershell
node --test frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
~~~

Expected: FAIL because the page currently has student-only copy/classes and hardcoded student endpoint strings.

- [ ] **Step 3: Add synchronous role selection and role configuration**

Keep StudentSearchPage as the exported compatibility class, but add these controller boundaries:

~~~js
roleFromPath() {
  return window.location.pathname.includes("/teacher/") ? "teacher" : "";
}

applyRole(role) {
  this.role = role === "teacher" ? "teacher" : "student";
  document.body.classList.toggle("teacher-search-page", this.role === "teacher");
  document.body.classList.toggle("student-search-page", this.role !== "teacher");
  this.api = this.role === "teacher"
    ? "/scan2borrow/api/teacher/books"
    : "/scan2borrow/api/student/books";
  this.lookupApi = this.role === "teacher"
    ? "/scan2borrow/api/teacher/borrow/lookup"
    : "/scan2borrow/api/student/borrow/lookup";
  this.borrowApi = this.role === "teacher"
    ? "/scan2borrow/api/teacher/borrow"
    : "/scan2borrow/api/student/borrow";
  this.dashboardPath = this.role === "teacher"
    ? "/scan2borrow/teacher/dashboard"
    : "/scan2borrow/student/dashboard";
}
~~~

Call applyRole with the path/cache role before binding/loading, then confirm the role through /api/auth/session when no reliable path/cache role exists. Update only copy and form/clear-filter destinations through explicit data hooks; do not change cart payload names or HTML IDs. Replace hardcoded lookup, submit, and redirect URLs with lookupApi, borrowApi, and dashboardPath.

- [ ] **Step 4: Add the teacher Swiss catalog stylesheet**

Create teacher-search.css with selectors scoped beneath .teacher-search-page, using the existing teacher tokens: #FFFFFF/#F7F7F8 surfaces, #002FA7 accent, #102F52 text, #D4E0E8/#AEBFCB rules, square 4px corners, compact Helvetica/Arial typography, tabular numerics, visible hairlines, and responsive overflow. Include a reduced-motion media query. Do not target .sidebar or [data-app-navbar].

- [ ] **Step 5: Run the focused tests to verify they pass**

~~~powershell
node --test frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
~~~

Expected: PASS, including the existing student search/card/cart contracts.

- [ ] **Step 6: Commit the Borrow/catalog role-awareness**

~~~powershell
git add frontend/features/student/pages/search/search.html frontend/features/student/pages/search/student-search.page.js frontend/assets/css/teacher-search.css frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
git commit -m "feat: make borrower catalog teacher-aware"
~~~

## Task 5: Make the History page role-aware end-to-end

**Files:**
- Modify: frontend/features/student/pages/history/history.html
- Modify: frontend/features/student/pages/history/student-history.page.js
- Modify: frontend/assets/css/teacher-history.css
- Modify: frontend/tests/student-library-surfaces.test.js
- Modify: frontend/tests/teacher-borrow-history-surfaces.test.js

**Interfaces:**
- Consumes: Shared history-body table, existing teacher ledger stylesheet, and /api/teacher/history alias.
- Produces: A history controller that selects teacher/student copy, class scope, and endpoint before rendering while preserving all eight columns and row formatting.

- [ ] **Step 1: Write failing endpoint and initial-render tests**

Require the history template to expose neutral role-copy hooks and retain the existing eight headers. Require the controller to contain /api/teacher/history, /api/student/history, path-aware role selection, teacher/student class toggles, and role-specific topbar/history copy.

- [ ] **Step 2: Run the focused tests to verify they fail**

~~~powershell
node --test frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
~~~

Expected: FAIL because the current history controller always loads /api/student/history and the static template begins with student copy.

- [ ] **Step 3: Add role-aware history configuration**

Extend the existing controller with a path-first role resolver and role application:

~~~js
roleFromPath() {
  return window.location.pathname.includes("/teacher/") ? "teacher" : "";
}

applyRole(role) {
  this.role = role === "teacher" ? "teacher" : "student";
  document.body.classList.toggle("teacher-history-page", this.role === "teacher");
  document.body.classList.toggle("student-history-page", this.role !== "teacher");
  this.historyApi = this.role === "teacher"
    ? "/scan2borrow/api/teacher/history"
    : "/scan2borrow/api/student/history";
  const roleHost = document.getElementById("current-user-role");
  if (roleHost) roleHost.textContent = this.role === "teacher" ? "Teacher" : "Student";
}
~~~

Apply the route/cache role synchronously in the constructor, then confirm through /api/auth/session if needed. Use historyApi in load(). Keep history-body, eight headers, escaped fields, row-overdue, teacher row/status/fine hooks, empty state, error state, and date formatting unchanged except for role-specific copy/classes.

- [ ] **Step 4: Finish the teacher history presentation boundary**

Keep teacher-history.css scoped beneath .teacher-history-page. Add or adjust only role-specific labels/layout rules needed to make the teacher view visibly Swiss and distinct from the student Organic view. Retain tabular numerics, hairline rules, status/fine hierarchy, overdue treatment, narrow-screen horizontal scrolling, and reduced-motion behavior.

- [ ] **Step 5: Run the focused history tests**

~~~powershell
node --test frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
~~~

Expected: PASS, with student and teacher contracts both preserved.

- [ ] **Step 6: Commit the History role-awareness**

~~~powershell
git add frontend/features/student/pages/history/history.html frontend/features/student/pages/history/student-history.page.js frontend/assets/css/teacher-history.css frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
git commit -m "feat: make borrower history teacher-aware"
~~~

## Task 6: Update the frontend route matrix and parity contracts

**Files:**
- Modify: frontend/parity/page-matrix.md
- Modify: backend/tests/Feature/CleanRouteMatrixTest.php
- Modify: backend/tests/Feature/PageRouteTableTest.php

**Interfaces:**
- Consumes: Canonical page aliases and shared feature templates from Tasks 2–5.
- Produces: Documentation and parity tests that identify /teacher/borrow and /teacher/history as teacher routes using the shared search/history implementation.

- [ ] **Step 1: Add the canonical teacher routes to the matrix**

Add these rows:

~~~text
| /teacher/borrow | teacher | features/student/pages/search/search.html | features/student/pages/search/student-search.page.js | canonical alias |
| /teacher/history | teacher | features/student/pages/history/history.html | features/student/pages/history/student-history.page.js | canonical alias |
~~~

Document that the implementation is shared but the runtime role scope and endpoint configuration are teacher-specific.

- [ ] **Step 2: Run parity tests**

~~~powershell
vendor/bin/phpunit backend/tests/Feature/CleanRouteMatrixTest.php backend/tests/Feature/PageRouteTableTest.php
~~~

Expected: PASS with the new route entries and all existing route ownership assertions intact.

- [ ] **Step 3: Commit parity documentation/tests**

~~~powershell
git add frontend/parity/page-matrix.md backend/tests/Feature/CleanRouteMatrixTest.php backend/tests/Feature/PageRouteTableTest.php
git commit -m "test: document teacher borrower route ownership"
~~~

## Task 7: Run full verification and inspect the result

**Files:** No production changes unless a verification failure produces a new regression test.

**Interfaces:**
- Consumes: All implementation and focused tests from Tasks 1–6.
- Produces: Evidence that student routes remain unchanged, teacher navigation no longer lands on student-only presentation, and the working tree contains only scoped changes.

- [ ] **Step 1: Run the complete frontend suite**

~~~powershell
npm test
~~~

Expected: all frontend tests pass.

- [ ] **Step 2: Run the complete backend suite**

~~~powershell
vendor/bin/phpunit
~~~

Expected: all backend tests pass.

- [ ] **Step 3: Run whitespace and status checks**

~~~powershell
git diff --check
git status --short
~~~

Expected: no whitespace errors; only the documented teacher role-awareness files are changed or committed.

- [ ] **Step 4: Review the final diff for role leakage**

Confirm the final diff shows teacher navbar destinations /teacher/borrow and /teacher/history; teacher page/API aliases protected by the teacher role; teacher catalog using teacher-named endpoints; teacher history using /api/teacher/history; student routes and endpoints intact; no teacher CSS selectors targeting .sidebar or [data-app-navbar]; and no duplicate dashboard/catalog/history business logic.
