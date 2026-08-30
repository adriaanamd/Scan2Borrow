# Student and Teacher Portal Pages Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make student search/history and teacher Borrow/History fully role-owned in markup, styles, controllers, and API configuration.

**Architecture:** Move role-neutral catalog and history mechanics into shared configurable page classes. Give student and teacher feature folders thin entry controllers with fixed API endpoints, copy, CSS prefix, and dashboard destinations. Keep each role’s HTML template explicit and load only its own visual styles.

**Tech Stack:** Native ES modules, vanilla DOM APIs, existing PHP page route table, Node test runner, PHPUnit 11.

## Global Constraints

- Student controllers must not contain teacher endpoints, labels, CSS classes, role detection, or session-storage role reads.
- Teacher controllers must not import student page controllers.
- Student templates load shared base styles plus student styles only; teacher templates load shared base styles plus teacher styles only.
- Each served template keeps exactly one browser-loadable module entry and one `data-app-page` marker.
- Preserve existing search filters, bulk borrow cart behavior, history table fields, empty/error states, and API payload contracts.

### Task 1: Add failing role-isolation contract tests

**Files:**
- Modify: `frontend/tests/student-library-surfaces.test.js`
- Modify: `frontend/tests/teacher-borrow-history-surfaces.test.js`
- Modify: `frontend/tests/role-specific-tabs.test.js`
- Modify: `backend/tests/Feature/BorrowerPagesParityTest.php`

**Interfaces:**
- Tests define the required student-only and teacher-only module/template boundaries for later tasks.

- [ ] **Step 1: Assert the student templates exclude faculty assets and use student entries.**

  Require `student-search.css`/`student-history.css`, reject `teacher-search.css`/`teacher-history.css`, and require the student page entry paths.

- [ ] **Step 2: Assert student controllers contain only student APIs and no role switching.**

  Reject `/api/teacher/`, `teacher-`, `roleFromPath`, and `sessionStorage` in student entry sources.

- [ ] **Step 3: Assert teacher templates use teacher entries and teacher-owned page markers.**

  Require `teacher-borrow.page.js` and `teacher-history.page.js`, and reject imports of student page entries.

- [ ] **Step 4: Run the focused tests and verify they fail against the current shared implementation.**

  Run `npm test -- --test-name-pattern="role|student|teacher"` and the focused PHPUnit parity tests. Expected: failures identifying teacher assets/branches in student sources and student entry imports in teacher templates.

- [ ] **Step 5: Commit the failing-test checkpoint.**

  ```powershell
  git add frontend/tests backend/tests/Feature/BorrowerPagesParityTest.php
  git commit -m "test: require isolated borrower portal modules"
  ```

### Task 2: Extract neutral catalog and history mechanics

**Files:**
- Create: `frontend/app/shared/pages/borrower-search.page.js`
- Create: `frontend/app/shared/pages/borrower-history.page.js`
- Modify: `frontend/tests/student-pages.test.js`

**Interfaces:**
- `BorrowerSearchPage` accepts fixed `api`, `lookupApi`, `borrowApi`, `dashboardPath`, `formAction`, and `copy` configuration and exposes the existing search/cart methods.
- `BorrowerHistoryPage` accepts fixed `historyApi`, `classPrefix`, and `copy` configuration and exposes the existing history loading/rendering methods.

- [ ] **Step 1: Move the existing catalog mechanics into `BorrowerSearchPage` without role conditionals.**

  Preserve filtering, safe HTML escaping, quantity display, barcode lookup, cart controls, CSRF submission, and redirect behavior. The class must use only the configuration passed by its role-owned entry.

- [ ] **Step 2: Move the existing history mechanics into `BorrowerHistoryPage` with configurable CSS prefixes.**

  Preserve the eight-column table, date formatting, status classes, fine rendering, and bounded error/empty states. Generate `student-history-*` or `teacher-history-*` classes from the configured prefix instead of rendering both sets.

- [ ] **Step 3: Run syntax checks on both new modules.**

  Run `node --check frontend/app/shared/pages/borrower-search.page.js` and `node --check frontend/app/shared/pages/borrower-history.page.js`. Expected: exit code 0.

- [ ] **Step 4: Commit the neutral mechanics.**

  ```powershell
  git add frontend/app/shared/pages frontend/tests/student-pages.test.js
  git commit -m "refactor: extract neutral borrower page mechanics"
  ```

### Task 3: Make student search and history genuinely student-owned

**Files:**
- Modify: `frontend/features/student/pages/search/student-search.page.js`
- Modify: `frontend/features/student/pages/history/student-history.page.js`
- Modify: `frontend/features/student/pages/search/search.html`
- Modify: `frontend/features/student/pages/history/history.html`

**Interfaces:**
- `StudentSearchPage` configures `BorrowerSearchPage` with only `/api/student/*` endpoints and student copy.
- `StudentHistoryPage` configures `BorrowerHistoryPage` with `/api/student/history`, the `student-history` prefix, and student copy.

- [ ] **Step 1: Replace the student search controller with a fixed student entry.**

  Import the neutral catalog class, configure student endpoints and `/student/dashboard`, export `StudentSearchPage`, and retain the DOMContentLoaded entrypoint.

- [ ] **Step 2: Replace the student history controller with a fixed student entry.**

  Import the neutral history class, configure the student endpoint and prefix, export `StudentHistoryPage`, and retain the DOMContentLoaded entrypoint.

- [ ] **Step 3: Remove faculty assets and faculty-only fallback markup from student templates.**

  Remove teacher CSS links/classes and keep `data-navbar-role="student"`, student page markers, student labels, and the student module entry.

- [ ] **Step 4: Run the focused student tests and verify they pass.**

  Run `npm test -- --test-name-pattern="student|role"`. Expected: all matching tests pass.

- [ ] **Step 5: Commit the student split.**

  ```powershell
  git add frontend/features/student
  git commit -m "fix: make student search and history role-owned"
  ```

### Task 4: Give teacher Borrow and History independent entry modules

**Files:**
- Create: `frontend/features/teacher/pages/borrow/teacher-borrow.page.js`
- Create: `frontend/features/teacher/pages/history/teacher-history.page.js`
- Modify: `frontend/features/teacher/pages/borrow/borrow.html`
- Modify: `frontend/features/teacher/pages/history/history.html`

**Interfaces:**
- `TeacherBorrowPage` configures `BorrowerSearchPage` with `/api/teacher/books`, `/api/teacher/borrow/lookup`, `/api/teacher/borrow`, and `/teacher/dashboard`.
- `TeacherHistoryPage` configures `BorrowerHistoryPage` with `/api/teacher/history`, the `teacher-history` prefix, and faculty copy.

- [ ] **Step 1: Add the teacher Borrow entry module with fixed teacher configuration.**

  Keep the teacher page’s DOMContentLoaded entrypoint and do not import any student feature page module.

- [ ] **Step 2: Add the teacher History entry module with fixed teacher configuration.**

  Keep the teacher page’s DOMContentLoaded entrypoint and do not import any student feature page module.

- [ ] **Step 3: Point the teacher templates to their teacher entry modules.**

  Preserve the teacher page markers, navigation role, teacher copy, and Swiss visual assets.

- [ ] **Step 4: Run the focused teacher tests and verify they pass.**

  Run `npm test -- --test-name-pattern="teacher|role"`. Expected: all matching tests pass.

- [ ] **Step 5: Commit the teacher split.**

  ```powershell
  git add frontend/features/teacher
  git commit -m "fix: make teacher circulation tabs role-owned"
  ```

### Task 5: Update parity documentation and verify the complete change

**Files:**
- Modify: `frontend/parity/page-matrix.md`
- Modify: `backend/tests/Feature/BorrowerPagesParityTest.php`
- Modify: `backend/tests/Feature/TeacherBorrowHistoryRoutingTest.php`

- [ ] **Step 1: Update the page matrix to list the final role-owned entry modules.**

  Student routes point to student entries; teacher routes point to teacher entries; shared neutral mechanics remain under `frontend/app/shared/pages`.

- [ ] **Step 2: Add backend parity assertions for student and teacher module ownership.**

  Assert each role’s template, `data-app-page`, navigation role, and module path.

- [ ] **Step 3: Run complete frontend verification.**

  Run `npm test`. Expected: all tests pass with zero failures.

- [ ] **Step 4: Run complete backend verification and PHP lint.**

  Run `C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml` and lint every changed PHP file. Expected: 310 PHPUnit tests pass and no changed PHP file has syntax errors.

- [ ] **Step 5: Run `git diff --check` and review the final ownership matrix.**

  Confirm student files contain no teacher CSS/API/module references and teacher files contain no student page controller references.

- [ ] **Step 6: Commit documentation and verification contracts.**

  ```powershell
  git add frontend/parity backend/tests
  git commit -m "test: document role-owned portal page boundaries"
  ```
