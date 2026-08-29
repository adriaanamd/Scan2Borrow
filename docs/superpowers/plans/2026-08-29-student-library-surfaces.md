# Student Library Surfaces Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the student Search Books and My History pages to match the existing student dashboard without changing content, behavior, palette, route, API, or sidenav.

**Architecture:** Add a shared student-scoped library-surface stylesheet for common masthead, panel, focus, responsive, and state treatments. Keep page-specific rules in `student-search.css` and a new `student-history.css`; retain the current controllers and IDs, adding only presentation hooks where the existing DOM needs a stable styling boundary.

**Tech Stack:** Vanilla HTML/CSS/JavaScript modules, Bootstrap 5.3.3 already used by the pages, existing `style.css` and `borrower-dashboards.css` tokens, Node native contract tests.

## Global Constraints

- “Do not change the sidenav markup, navbar scripts, route paths, API endpoints, or backend.”
- “Do not invent statistics, book records, labels, or user data.”
- “Do not add new colors outside the existing system tokens.”
- “Preserve existing destructive-action confirmation behavior and borrow-cart behavior.”
- “Respect reduced-motion preferences and keyboard focus visibility.”
- The result must contain 15–30 meaningful commits.

---

### Task 1: Record the approved design

**Files:** Create `docs/superpowers/specs/2026-08-29-student-library-surfaces-design.md`.

- [ ] Commit the approved design with `git add docs/superpowers/specs/2026-08-29-student-library-surfaces-design.md; git commit -m "docs: define student library surface direction"`.

### Task 2: Add failing frontend scope contracts

**Files:** Create `frontend/tests/student-library-surfaces.test.js`; test both student templates.

- [ ] Assert both page markers, existing controller entries, page-specific stylesheet links, and the unchanged `.sidebar[data-app-navbar]` boundary.
- [ ] Run `npm test -- --test-name-pattern="student library surfaces"`; expect failure for missing new contracts.
- [ ] Commit the failing contract with `git add frontend/tests/student-library-surfaces.test.js; git commit -m "test: define student library surface scope"`.

### Task 3: Add shared student library-surface styling

**Files:** Create `frontend/assets/css/student-library-surfaces.css`; modify both student templates.

- [ ] Add shared masthead, panel, focus, reduced-motion, responsive spacing, and state rules using only existing `--app-bg`, `--card`, `--navy`, `--primary`, `--accent`, `--border`, `--border-strong`, and `--shadow` tokens.
- [ ] Link the stylesheet after the existing page styles and add a shared page class to the content roots without editing the sidenav.
- [ ] Run the focused contract and commit: `git add frontend/assets/css/student-library-surfaces.css frontend/features/student/pages/search/search.html frontend/features/student/pages/history/history.html; git commit -m "feat: add shared student library surfaces"`.

### Task 4: Structure Search Books presentation hooks

**Files:** Modify `frontend/features/student/pages/search/search.html`; update the contract test.

- [ ] Add stable classes around the page head, filter panel, result panel, result header, and result state while retaining every current field ID/name.
- [ ] Assert `searchForm`, `book-results`, `book-count`, `active-filters`, and `borrowModal` remain present.
- [ ] Run `npm test -- --test-name-pattern="student library surfaces"` and commit with `git add frontend/features/student/pages/search/search.html frontend/tests/student-library-surfaces.test.js; git commit -m "feat: structure student search reading surface"`.

### Task 5: Restyle Search Books masthead and filter desk

**Files:** Modify `frontend/features/student/pages/search/search.html`, `frontend/assets/css/student-library-surfaces.css`, and `frontend/features/student/pages/search/student-search.page.js`.

- [ ] Style the existing page heading and filter form with the shared rounded student surfaces, clear grouping, and current palette.
- [ ] Preserve current query serialization, filter names, options, active-filter values, and search behavior; add only presentation classes to generated filter tags if needed.
- [ ] Run `npm test -- --test-name-pattern="student library surfaces|student services"` and commit: `git add frontend/features/student/pages/search; git add frontend/assets/css/student-library-surfaces.css; git add frontend/tests/student-library-surfaces.test.js; git commit -m "feat: polish student search filters"`.

### Task 6: Refine Search Books cards

**Files:** Modify `frontend/assets/css/student-search.css` and `frontend/features/student/pages/search/student-search.page.js`; test the card contract.

- [ ] Improve cover treatment, back-face hierarchy, radius, quantity/availability line, focus behavior, and hover transition.
- [ ] Preserve `bookCard`, `badge`, `BulkBorrowCart`, all current data fields, the borrow modal trigger, and add-to-cart action.
- [ ] Add reduced-motion behavior that disables the flip transform while leaving both faces readable.
- [ ] Run `npm test -- --test-name-pattern="student library surfaces|bulk-borrow|quantity"` and commit: `git add frontend/assets/css/student-search.css frontend/features/student/pages/search/student-search.page.js frontend/tests/student-library-surfaces.test.js; git commit -m "feat: refine student catalog cards"`.

### Task 7: Make Search Books responsive and state-complete

**Files:** Modify `frontend/assets/css/student-search.css` and `frontend/assets/css/student-library-surfaces.css`; test scope.

- [ ] Add mobile filter stacking, card sizing, empty-result, error, loading, visible-focus, and reduced-motion presentation inside the student content scope.
- [ ] Assert new CSS does not target `.sidebar`, `[data-app-navbar]`, or global topbar rules.
- [ ] Run the focused test and commit: `git add frontend/assets/css/student-search.css frontend/assets/css/student-library-surfaces.css frontend/tests/student-library-surfaces.test.js; git commit -m "feat: make student search responsive"`.

### Task 8: Add My History presentation contracts

**Files:** Modify `frontend/tests/student-library-surfaces.test.js`; test `history.html` and `student-history.page.js`.

- [ ] Assert the eight existing headers, `history-body`, `StudentHistoryPage`, status rendering, overdue row handling, fine rendering, and the unchanged API path.
- [ ] Run `npm test -- --test-name-pattern="student library surfaces"` and commit: `git add frontend/tests/student-library-surfaces.test.js; git commit -m "test: define student history surface contract"`.

### Task 9: Structure My History ledger

**Files:** Modify `frontend/features/student/pages/history/history.html`; create `frontend/assets/css/student-history.css`.

- [ ] Add the shared page class, history masthead, ledger panel, responsive wrapper, and state classes without changing table column text or data IDs.
- [ ] Style ledger density, code cells, status/fine emphasis, overdue rows, loading, empty, and error states with existing system tokens.
- [ ] Run the focused test and commit: `git add frontend/features/student/pages/history/history.html frontend/assets/css/student-history.css; git commit -m "feat: structure student history ledger"`.

### Task 10: Refine My History row presentation

**Files:** Modify `frontend/features/student/pages/history/student-history.page.js` and `frontend/assets/css/student-history.css`; test the rendering contract.

- [ ] Preserve API loading, date formatting, escaping, row data, overdue detection, status strings, and fine calculations.
- [ ] Add only semantic classes/data attributes to generated rows and status/fine cells so CSS distinguishes active, returned, overdue, and fined records.
- [ ] Keep error and empty text safe and inside the styled state panel.
- [ ] Run `npm test -- --test-name-pattern="student library surfaces|student services|history"` and commit: `git add frontend/features/student/pages/history/student-history.page.js frontend/assets/css/student-history.css frontend/tests/student-library-surfaces.test.js; git commit -m "feat: improve student history readability"`.

### Task 11: Lock cross-page accessibility and navigation scope

**Files:** Modify `frontend/tests/student-library-surfaces.test.js`, `frontend/assets/css/student-library-surfaces.css`, `frontend/assets/css/student-search.css`, and `frontend/assets/css/student-history.css`.

- [ ] Assert each page has reduced-motion, visible-focus, responsive, and page-scoped rules.
- [ ] Assert both templates retain the same sidebar placeholder, `data-app-navbar`, navbar role, and core navbar script path.
- [ ] Run `npm test -- --test-name-pattern="student library surfaces"` and commit: `git add frontend/tests/student-library-surfaces.test.js frontend/assets/css; git commit -m "test: protect student surface accessibility and sidenav"`.

### Task 12: Lock content and controller parity

**Files:** Modify `frontend/tests/student-library-surfaces.test.js`.

- [ ] Assert search and history retain their route paths, API endpoint strings, required IDs, field names, and module entry paths.
- [ ] Assert borrowing/cart and history controller method boundaries remain present.
- [ ] Run `npm test -- --test-name-pattern="student|borrower|quantity|bulk-borrow"` and commit: `git add frontend/tests/student-library-surfaces.test.js; git commit -m "test: protect student page behavior parity"`.

### Task 13: Run full frontend verification

**Files:** Any student search/history files required by test evidence.

- [ ] Run `npm test`; expected result is all frontend tests passing.
- [ ] Run `git diff --check`; expected result is no whitespace errors.
- [ ] Fix only defects evidenced by the checks and commit each real fix separately.

### Task 14: Review and integrate

**Files:** No new files unless review identifies a concrete defect.

- [ ] Review the final diff for palette drift, fabricated content, global/sidenav selectors, renamed IDs, and behavior changes.
- [ ] Run `npm test` again.
- [ ] Merge with `git merge --no-ff feature/student-library-surfaces -m "feat: beautify student search and history"`.
- [ ] Run `npm test` on merged `master`, then remove the merged worktree and branch.
