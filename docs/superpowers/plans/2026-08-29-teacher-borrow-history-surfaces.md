# Teacher Borrow and History Surface Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Improve the teacher dashboard Borrow Books modal and the shared teacher-facing history view with the existing teacher dashboard’s Swiss/data-oriented visual language.

**Architecture:** Keep the existing teacher dashboard route and borrow controller. Add teacher-scoped modal hooks/CSS there, and add a role-aware class to the shared history controller so teacher-specific ledger rules layer over the existing student history treatment without duplicating routes or touching the sidebar.

**Tech Stack:** Existing HTML, scoped CSS, browser JavaScript modules, Node’s native `node:test` suite, Bootstrap 5.3.3 already loaded by the templates.

## Global Constraints

- Preserve existing teacher Borrow modal IDs, form fields, bulk cart behavior, scanner behavior, API paths, error handling, and confirmation behavior.
- Preserve `/student/history`, `history-body`, all eight history columns, `/api/student/history`, escaped dynamic values, and student styling.
- Use the teacher dashboard’s existing white/navy/blue/gold palette, sans-serif data typography, hairline rules, and tabular numerals.
- Do not modify `frontend/app/shared/components/app-navbar/app-navbar.component.js`, sidebar markup, backend code, route tables, database schema, or global palette tokens.
- Keep new presentation selectors scoped to `.borrower-dashboard--teacher` or `.teacher-history-page`; do not add navigation selectors.
- Produce 15–30 meaningful commits across the implementation, tests, documentation, and reviewable style/controller checkpoints.

## File Map

- Modify `frontend/features/teacher/pages/dashboard/dashboard.html` for teacher Borrow modal presentation hooks only.
- Modify `frontend/features/teacher/pages/dashboard/teacher-dashboard.page.js` for cart/state presentation hooks and role-preserving behavior only.
- Modify `frontend/assets/css/borrower-dashboards.css` for teacher Borrow modal rules that reuse existing teacher dashboard tokens.
- Modify `frontend/features/student/pages/history/history.html` to load teacher history styling while retaining the shared page structure.
- Modify `frontend/features/student/pages/history/student-history.page.js` to resolve the existing session role and apply the teacher history scope.
- Create `frontend/assets/css/teacher-history.css` for teacher-only history ledger rules.
- Modify `frontend/tests/teacher-borrow-history-surfaces.test.js` with markup, controller, CSS scope, and behavior-boundary contracts.
- Create `docs/superpowers/specs/2026-08-29-teacher-borrow-history-surfaces-design.md` and this implementation plan.

### Task 1: Lock the teacher Borrow surface boundary

**Files:** Modify `frontend/tests/teacher-borrow-history-surfaces.test.js`.

- [ ] Write a test that reads the teacher dashboard and asserts `data-app-page="teacher-dashboard"`, `id="borrowModal"`, `id="borrowForm"`, `id="bulk-scan-barcode"`, `id="bulkBorrowItems"`, `id="bulkBorrowCount"`, and `teacher-dashboard.page.js` remain present.
- [ ] Run `npm test -- --test-name-pattern="teacher Borrow surface boundary"` and confirm the new test fails because the new presentation hooks do not exist.
- [ ] Commit as `test: define teacher borrow surface boundary`.

### Task 2: Add Borrow modal presentation hooks

**Files:** Modify `frontend/features/teacher/pages/dashboard/dashboard.html`.

- [ ] Add classes to the existing modal only: `teacher-borrow-modal`, `teacher-borrow-modal__header`, `teacher-borrow-modal__scan`, `teacher-borrow-modal__cart`, and `teacher-borrow-modal__footer`.
- [ ] Keep all existing IDs, labels, form fields, data attributes, buttons, scanner controls, due-date copy, error/message hosts, and submit behavior unchanged.
- [ ] Run the focused Task 1 test and confirm it passes.
- [ ] Commit as `feat: structure teacher borrow modal surface`.

### Task 3: Add teacher Borrow modal styling contract

**Files:** Modify `frontend/tests/teacher-borrow-history-surfaces.test.js`, `frontend/assets/css/borrower-dashboards.css`, `frontend/features/teacher/pages/dashboard/dashboard.html`.

- [ ] Add a test requiring the dashboard stylesheet and modal template to expose `teacher-borrow-modal`, `teacher-borrow-modal__scan`, `teacher-borrow-modal__cart`, `teacher-borrow-modal__footer`, and `prefers-reduced-motion`.
- [ ] Run the focused test and confirm it fails because the stylesheet does not define the modal hooks.
- [ ] Add rules scoped beneath `.borrower-dashboard--teacher .teacher-borrow-modal` using the teacher dashboard palette: squared four-pixel corners, `#FFFFFF`, `#D4E0E8`, `#002FA7`, `#102F52`, `#AEBFCB`, compact headings, hairline section rules, visible scan/cart grouping, and responsive modal padding.
- [ ] Add reduced-motion rules scoped to the teacher dashboard.
- [ ] Run the focused test and confirm it passes.
- [ ] Commit test and style changes separately as `test: lock teacher borrow modal presentation` and `style: refine teacher borrow modal surface`.

### Task 4: Add Borrow cart presentation hooks without behavior changes

**Files:** Modify `frontend/features/teacher/pages/dashboard/teacher-dashboard.page.js`, `frontend/tests/teacher-borrow-history-surfaces.test.js`.

- [ ] Add a failing controller assertion for `teacher-borrow-cart-row`, `teacher-borrow-cart-actions`, and `teacher-borrow-cart-count`.
- [ ] Run the focused test and confirm it fails.
- [ ] Add those classes to the existing cart row, action group, and count host while retaining current `BulkBorrowCart`, barcode arrays, increment/decrement/remove actions, and `bulkBorrowCount` updates.
- [ ] Run the focused test and confirm it passes.
- [ ] Commit test and controller changes separately as `test: define teacher borrow cart hooks` and `feat: expose teacher borrow cart presentation`.

### Task 5: Lock shared history role behavior

**Files:** Modify `frontend/tests/teacher-borrow-history-surfaces.test.js`.

- [ ] Add a test requiring the shared history template to retain `data-navbar-role="session"`, `history-body`, all eight headers, and `student-history.page.js`.
- [ ] Add a controller-source assertion for `/api/auth/session`, `teacher-history-page`, `student-history-page`, and `/api/student/history`.
- [ ] Run the focused test and confirm it fails because role scoping is not yet present.
- [ ] Commit as `test: define teacher history role boundary`.

### Task 6: Apply a runtime role scope to shared history

**Files:** Modify `frontend/features/student/pages/history/student-history.page.js`, `frontend/features/student/pages/history/history.html`.

- [ ] Add `resolveRole()` that first reads the existing `scan2borrow.nav.role` session-storage value and otherwise fetches `/scan2borrow/api/auth/session`; on failure, fall back to `student` without blocking history rendering.
- [ ] Add `applyRole(role)` that toggles `teacher-history-page` and `student-history-page` on `document.body`, updates `#current-user-role`, and preserves the existing history request/render flow.
- [ ] Start role resolution before history loading, but keep history rendering available if the session request fails.
- [ ] Add the `teacher-history.css` link after the existing student history stylesheet link; do not change the sidebar, route, or table IDs.
- [ ] Run the focused role test and confirm it passes.
- [ ] Commit as `feat: scope shared history by borrower role`.

### Task 7: Add teacher history ledger styling contract

**Files:** Modify `frontend/tests/teacher-borrow-history-surfaces.test.js`, create `frontend/assets/css/teacher-history.css`.

- [ ] Add a test requiring `.teacher-history-page`, hairline borders, `font-variant-numeric: tabular-nums`, teacher status/fine selectors, and a reduced-motion media query.
- [ ] Run the focused test and confirm it fails because `teacher-history.css` does not exist.
- [ ] Create scoped teacher rules for the existing ledger: white panel, four-pixel radius, left-aligned headings, compact uppercase table headers, hairline row rules, fixed transaction-code treatment, quantity/date alignment, status pills, fine emphasis, overdue left rule, and `min-width` horizontal overflow below 900px.
- [ ] Add responsive rules below 680px and reduced-motion rules.
- [ ] Run the focused test and confirm it passes.
- [ ] Commit test and CSS separately as `test: lock teacher history ledger contract` and `style: add teacher history ledger surface`.

### Task 8: Add history row presentation hooks

**Files:** Modify `frontend/features/student/pages/history/student-history.page.js`, `frontend/tests/teacher-borrow-history-surfaces.test.js`.

- [ ] Add a failing source assertion for `teacher-history-row`, `teacher-history-status`, and `teacher-history-fine`.
- [ ] Run the focused test and confirm it fails.
- [ ] Add classes to generated rows/cells while retaining the existing `row-overdue` class, quantity values, status strings, date formatting, fine formatting, and escaped fields.
- [ ] Add teacher status class mapping only for presentation; do not change status values returned by the API.
- [ ] Run the focused test and confirm it passes.
- [ ] Commit test and controller changes separately as `test: define teacher history row hooks` and `feat: expose teacher history row hierarchy`.

### Task 9: Add teacher history empty/error states

**Files:** Modify `frontend/features/student/pages/history/student-history.page.js`, `frontend/assets/css/teacher-history.css`, `frontend/tests/teacher-borrow-history-surfaces.test.js`.

- [ ] Add a failing test requiring `teacher-history-state`, `teacher-history-state--empty`, and `teacher-history-state--error` in the controller and stylesheet.
- [ ] Run the focused test and confirm it fails.
- [ ] Add those classes to the existing empty/error rows without changing their text or colspan.
- [ ] Style them as bounded, readable ledger states using existing teacher tokens.
- [ ] Run the focused test and confirm it passes.
- [ ] Commit test and implementation separately as `test: define teacher history states` and `feat: style teacher history states`.

### Task 10: Add cross-surface isolation and parity checks

**Files:** Modify `frontend/tests/teacher-borrow-history-surfaces.test.js`.

- [ ] Assert new CSS sources do not contain `.sidebar`, `[data-app-navbar]`, or global `body` selectors other than the explicit role scope required for history.
- [ ] Assert teacher dashboard Borrow still contains `teacher-dashboard.page.js`, `/api/teacher/dashboard`, `/api/teacher/borrow/lookup`, and `BulkBorrowCart`.
- [ ] Assert shared history still contains `/api/student/history`, `history-body`, and all eight original headers.
- [ ] Run the focused test and confirm it passes.
- [ ] Commit as `test: enforce teacher surface isolation`.

### Task 11: Run full verification and inspect the final diff

**Files:** No production changes unless verification finds a concrete issue.

- [ ] Run `npm test` and require all tests to pass.
- [ ] Run `git diff --check` against the branch base.
- [ ] Review `git diff adbdf87..HEAD --stat` and confirm only the documented spec/plan, teacher dashboard Borrow surface, shared history surface/controller, teacher CSS, and tests changed.
- [ ] If a concrete issue is found, add a failing regression test before fixing it and commit the fix separately.
- [ ] Commit any required final verification fix as `fix: preserve teacher surface parity`.

### Task 12: Review and integrate

**Files:** No new files expected.

- [ ] Request a targeted code review against base `adbdf87` and the final feature SHA.
- [ ] Resolve all critical/important findings and rerun `npm test`.
- [ ] Merge locally into `master` with `--no-ff`.
- [ ] Run `npm test` on the merged `master`.
- [ ] Remove only `.worktrees/teacher-borrow-history-surfaces` and delete `feature/teacher-borrow-history-surfaces` after successful merge.
- [ ] Confirm the pre-existing `frontend/features/guest/pages/profile/guest-profile.page.js` modification remains untouched.
