# Teacher History UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give teacher borrowing history a standalone Swiss ledger UI with no student presentation dependencies.

**Architecture:** Keep `BorrowerHistoryPage` as the neutral data renderer. Add a configurable state-class prefix, configure the teacher entry with `teacher-history`, and replace the teacher history template/style surface with teacher-owned markup and Swiss rules.

**Tech Stack:** Native ES modules, vanilla DOM APIs, CSS, Node test runner, PHPUnit 11.

## Global Constraints

- Preserve `/scan2borrow/api/teacher/history`, `history-body`, all eight table columns, quantities, dates, statuses, fines, empty/error states, and teacher navigation.
- Teacher history HTML/CSS/controller must contain no `student-history`, `student-library`, or student stylesheet references.
- Student history HTML/CSS/controller must continue using its existing student surface.
- Use the existing neutral history renderer; do not duplicate API or table data logic.

### Task 1: Add the failing teacher-history isolation contract

**Files:**
- Modify: `frontend/tests/teacher-borrow-history-surfaces.test.js`
- Modify: `frontend/tests/role-specific-tabs.test.js`
- Modify: `backend/tests/Feature/BorrowerPagesParityTest.php`

- [ ] **Step 1: Add assertions for a standalone teacher history surface.**

Require `teacher-history.css`, reject `student-history.css`, `student-library-surfaces.css`, and `teacher-library-surfaces.css`, and reject student-prefixed presentation classes in the teacher history template/style.

- [ ] **Step 2: Run the focused contract tests and verify the expected failure.**

Run `node --test frontend/tests/teacher-borrow-history-surfaces.test.js frontend/tests/role-specific-tabs.test.js` and the matching PHPUnit parity test. The tests must fail because the current teacher history template still loads `teacher-library-surfaces.css` and uses `teacher-library-*` classes.

- [ ] **Step 3: Commit the failing contract checkpoint.**

```powershell
git add frontend/tests/teacher-borrow-history-surfaces.test.js frontend/tests/role-specific-tabs.test.js backend/tests/Feature/BorrowerPagesParityTest.php
git commit -m "test: require standalone teacher history surface"
```

### Task 2: Make neutral history state classes configurable

**Files:**
- Modify: `frontend/app/shared/pages/borrower-history.page.js`
- Modify: `frontend/features/student/pages/history/student-history.page.js`
- Modify: `frontend/features/teacher/pages/history/teacher-history.page.js`

- [ ] **Step 1: Extend the renderer configuration.**

Use `surfacePrefix` when emitting empty/error state classes, so the existing student configuration emits `student-library-state` and the teacher configuration emits `teacher-history-library-state`.

- [ ] **Step 2: Run the shared history and role tests.**

Run `node --test frontend/tests/student-pages.test.js frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js`. The tests must pass without changing API calls or table data handling.

- [ ] **Step 3: Commit the renderer boundary.**

```powershell
git add frontend/app/shared/pages/borrower-history.page.js frontend/features/student/pages/history/student-history.page.js frontend/features/teacher/pages/history/teacher-history.page.js
git commit -m "refactor: scope borrower history state classes"
```

### Task 3: Replace the teacher history presentation

**Files:**
- Modify: `frontend/features/teacher/pages/history/history.html`
- Modify: `frontend/assets/css/teacher-history.css`
- Delete from template: `/scan2borrow/frontend/assets/css/teacher-library-surfaces.css`

- [ ] **Step 1: Replace the teacher template classes with teacher-history-owned classes.**

Use `teacher-history-page__content`, `teacher-history-masthead`, `teacher-history-eyebrow`, `teacher-history-ledger`, `teacher-history-table`, and `teacher-history-library-state`; keep the existing IDs, headings, table headers, module entry, and teacher navbar marker.

- [ ] **Step 2: Implement the standalone Swiss rules.**

Define all layout, masthead, ledger, table, state, responsive, focus, and reduced-motion rules under `.teacher-history-page` in `teacher-history.css`. Use white/`#F7F7F8` surfaces, `#002FA7` accent, Helvetica Neue/system sans, hairline borders, square corners, compact spacing, and tabular numerals. Do not include Fraunces, grain, student selectors, or teacher library-surface selectors.

- [ ] **Step 3: Run focused tests and inspect ownership scans.**

Run the focused frontend and backend parity tests, then run `rg -n "student-|teacher-library" frontend/features/teacher/pages/history frontend/assets/css/teacher-history.css` and confirm no matches.

- [ ] **Step 4: Commit the dedicated teacher UI.**

```powershell
git add frontend/features/teacher/pages/history/history.html frontend/assets/css/teacher-history.css
git commit -m "fix: give teacher history a standalone ledger UI"
```

### Task 4: Verify the merged feature

**Files:**
- No source changes expected.

- [ ] **Step 1: Run frontend syntax checks and the complete frontend suite.**

Run `node --check frontend/app/shared/pages/borrower-history.page.js` and `npm test`; expect zero failures.

- [ ] **Step 2: Run the complete backend suite and changed-file lint.**

Run `C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml` and `C:\xampp\php\php.exe -l backend/tests/Feature/BorrowerPagesParityTest.php`; expect 311 passing tests and no syntax errors.

- [ ] **Step 3: Review the final ownership boundary.**

Run `git diff --check`, confirm teacher history has its own stylesheet and classes, and confirm student history still has its own stylesheet and classes.

- [ ] **Step 4: Commit any final documentation-only adjustment.**

If verification requires no source adjustment, retain the implementation commits and do not create an empty commit.
