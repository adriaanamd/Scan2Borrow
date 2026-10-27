# 290px Mobile Responsiveness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the existing Scan2Borrow frontend usable at a 290px viewport without changing its visual language or information architecture.

**Architecture:** Keep the shared `frontend/assets/css/style.css` as the source of truth for the application shell, then apply only targeted responsive overrides to feature CSS files that currently declare hard minimum widths or fixed grids. Add source-level regression tests beside the existing Node test suite; preserve horizontal scrolling only inside dense table containers.

**Tech Stack:** Vanilla HTML/CSS/JavaScript, Bootstrap 5.3.3 templates, Node's built-in test runner, PHP/Composer backend quality checks.

## Global Constraints

- Support viewport width `290px`.
- Preserve current navy, blue, gold, white, and ruled library-card styling.
- Do not change user-facing labels, navigation structure, backend behavior, or dependencies.
- Keep existing tests and CI gates intact.
- Use Margaret's commit identity: `REuhLys04 <kaleanfreix@gmail.com>`; push only to `https://github.com/adriaanamd/scan2borrow-copy.git` on `master`.
- Produce at least 30 non-empty, coherent commits.

## Files and responsibilities

- `frontend/tests/responsive-layout.test.js`: static contracts for the shared 290px responsive behavior and feature CSS audits.
- `frontend/assets/css/style.css`: root width, shell, topbar, content, page-head, card, form, modal, and auth responsiveness.
- `frontend/assets/css/borrower-dashboards.css`: dashboard grids, hero actions, chart/table containment, and compact mobile layout.
- `frontend/assets/css/student-history.css`: history table containment and compact metadata flow.
- `frontend/assets/css/teacher-history.css`: teacher history table containment and compact metadata flow.
- `frontend/assets/css/student-search.css`: search toolbar, results grid, and book-card sizing.
- `frontend/assets/css/teacher-search.css`: teacher search toolbar, result cards, and borrow modal sizing.
- `frontend/assets/css/student-library-surfaces.css`: shared student library card/card-grid constraints.
- `frontend/assets/css/teacher-library-surfaces.css`: shared teacher library card/card-grid constraints.
- `frontend/assets/css/staff-management.css`: staff management toolbar and responsive card layout.
- `frontend/assets/css/guest-requests.css`: guest request toolbar/list layout.
- `frontend/assets/css/api-docs.css`: API documentation navigation/content split.
- `frontend/assets/css/staff-reports.css`: report metadata and pagination flow.
- `frontend/features/staff/pages/dashboard/dashboard.html`: only if the inline dashboard rule is needed to preserve 290px flow.
- `docs/superpowers/specs/2026-10-27-mobile-290px-responsive-design.md`: approved scope and acceptance criteria.

## Commit-by-commit execution

Each task ends with a focused test command and a commit. Use the commit identity from the global constraints with `git -c user.name=... -c user.email=... commit`. The first design commit already exists; this plan is commit 2.

### Task 1: Add the responsive test harness

**Files:** Create `frontend/tests/responsive-layout.test.js`.

- [ ] Add a small `read()` helper and tests that load CSS as plain text from the repository.
- [ ] Run `node --test frontend/tests/responsive-layout.test.js`; expect the file's initial contract test to fail because the root still has the 320px floor.
- [ ] Commit `test: add responsive layout contract harness`.

### Task 2: Remove the global minimum width

**Files:** Modify `frontend/assets/css/style.css` near the root `html` rule.

- [ ] Change `html { min-width: 320px; }` to `html { min-width: 0; }`.
- [ ] Add the test assertion that the stylesheet does not declare a minimum width above 290px on `html` or `body`.
- [ ] Run the focused responsive test and commit `fix: allow the document to shrink to 290px`.

### Task 3: Make the shared shell shrink-safe

**Files:** Modify `frontend/assets/css/style.css` for `.app`, `.main`, `.topbar`, `.topbar-title`, and `.topbar-user`.

- [ ] Add `min-width: 0` to the flex shell and topbar children where text can force overflow.
- [ ] Add a test for shrink-safe shell selectors.
- [ ] Run the focused test and commit `fix: make the shared shell shrink-safe`.

### Task 4: Add the 290px content inset contract

**Files:** Modify `frontend/assets/css/style.css` in the mobile media rules and the responsive test.

- [ ] Add a `max-width: 320px` rule with `content`, `topbar`, and `.auth-wrap` padding that leaves usable width at 290px.
- [ ] Keep the existing larger breakpoints unchanged.
- [ ] Run the focused test and commit `fix: preserve content insets on narrow phones`.

### Task 5: Make page headers wrap safely

**Files:** Modify `frontend/assets/css/style.css` for `.page-head`, `.page-head > *`, and `.page-head .btn`.

- [ ] Add `min-width: 0` to page-head children and allow action groups to wrap without forcing the header wider than its card.
- [ ] Add a contract test for page-head shrink/wrap behavior.
- [ ] Run the focused test and commit `fix: wrap page headers below phone width`.

### Task 6: Make shared cards and text flow safely

**Files:** Modify `frontend/assets/css/style.css` for `.card`, `.soft-card`, `.table-card`, `.hero-card`, `.stat-card`, and common text children.

- [ ] Add `min-width: 0` and `overflow-wrap: anywhere` only to layout children that can contain long real data.
- [ ] Add a test for card shrink behavior.
- [ ] Run the focused test and commit `fix: keep shared cards within the viewport`.

### Task 7: Make shared form rows fit 290px

**Files:** Modify `frontend/assets/css/style.css` for `.input-group`, `.form-control`, `.form-select`, and button groups.

- [ ] Ensure controls can shrink and text inputs do not keep a Bootstrap intrinsic minimum that exceeds their parent.
- [ ] Stack compact button groups only at the narrow breakpoint; preserve the current visual button styling.
- [ ] Add and run the focused contract test; commit `fix: fit shared form controls on narrow phones`.

### Task 8: Make fallback modals and modal footers safe

**Files:** Modify `frontend/assets/css/style.css` for fallback modal/dialog rules and the narrow breakpoint.

- [ ] Keep modal dialogs at `width: 100%` within their existing inset and allow long titles/body text to wrap.
- [ ] Preserve the existing full-width footer button behavior at small widths.
- [ ] Add the modal contract test and commit `fix: contain modal surfaces at 290px`.

### Task 9: Make auth surfaces fluid

**Files:** Modify `frontend/assets/css/style.css` for `.auth-card`, `.auth-split`, `.auth-brand-panel`, `.auth-panel`, and the 480px/320px media rules.

- [ ] Reduce only the narrow-screen padding and logo sizing enough to avoid overflow while preserving the existing split-card composition.
- [ ] Add an auth source contract test and commit `fix: keep authentication usable at 290px`.

### Task 10: Contain shared table overflow

**Files:** Modify `frontend/assets/css/style.css` and the responsive test.

- [ ] Ensure `.table-card` and `.table-responsive` contain horizontal overflow inside the card.
- [ ] Keep table semantics and readability; do not hide columns or reduce text to unreadable sizes.
- [ ] Run the focused test and commit `fix: contain dense tables inside their cards`.

### Task 11: Make dashboard stat grids collapse

**Files:** Modify `frontend/assets/css/borrower-dashboards.css` and the responsive test.

- [ ] Add a narrow breakpoint that collapses the dashboard stat grid and any action grid with fixed minimum columns.
- [ ] Preserve existing spacing and card styling.
- [ ] Run the focused test and commit `fix: collapse dashboard stats on narrow phones`.

### Task 12: Make dashboard hero actions wrap

**Files:** Modify `frontend/assets/css/borrower-dashboards.css`.

- [ ] Allow hero content and action rows to shrink/wrap; keep existing button ordering and labels.
- [ ] Add the matching source assertion and commit `fix: wrap dashboard hero actions on mobile`.

### Task 13: Contain dashboard charts and data rails

**Files:** Modify `frontend/assets/css/borrower-dashboards.css`.

- [ ] Keep chart/table internals inside their existing scroll or clipped surface without widening the main document.
- [ ] Add a test for the dashboard data-rail containment and commit `fix: contain dashboard data rails`.

### Task 14: Keep student history tables usable

**Files:** Modify `frontend/assets/css/student-history.css`.

- [ ] Keep the existing wide table readable inside its scroll container; make surrounding metadata and actions wrap at 290px.
- [ ] Add a test proving the wide minimum applies to the inner table surface, not the page shell.
- [ ] Commit `fix: isolate student history table width`.

### Task 15: Keep teacher history tables usable

**Files:** Modify `frontend/assets/css/teacher-history.css`.

- [ ] Apply the same containment boundary to teacher history without changing columns or copy.
- [ ] Add the matching test and commit `fix: isolate teacher history table width`.

### Task 16: Make student search controls wrap

**Files:** Modify `frontend/assets/css/student-search.css`.

- [ ] Collapse the search filter/result grid at narrow widths and allow controls to use full available width.
- [ ] Add a test for the narrow search grid and commit `fix: wrap student search controls`.

### Task 17: Keep student book cards within the viewport

**Files:** Modify `frontend/assets/css/student-search.css` and `student-library-surfaces.css`.

- [ ] Remove or scope any card minimum that can widen the page; keep book covers and card text proportional.
- [ ] Add the source assertion and commit `fix: contain student library cards`.

### Task 18: Make teacher search controls wrap

**Files:** Modify `frontend/assets/css/teacher-search.css`.

- [ ] Stack the teacher search result grid and action controls at narrow widths while preserving the existing card interaction.
- [ ] Add a test and commit `fix: wrap teacher search controls`.

### Task 19: Keep the teacher borrow modal fluid

**Files:** Modify `frontend/assets/css/teacher-search.css`.

- [ ] Ensure modal body, cart rows, and footer buttons wrap within the viewport; keep the existing modal style and action order.
- [ ] Add a test and commit `fix: contain teacher borrow modal on mobile`.

### Task 20: Keep teacher library surfaces shrink-safe

**Files:** Modify `frontend/assets/css/teacher-library-surfaces.css`.

- [ ] Scope any card minimum width to the card rail/container and allow content text to wrap.
- [ ] Add a test and commit `fix: contain teacher library cards`.

### Task 21: Make staff management responsive

**Files:** Modify `frontend/assets/css/staff-management.css`.

- [ ] Collapse management grids/toolbars at narrow widths and allow action controls to occupy the available row.
- [ ] Add a test and commit `fix: stack staff management controls on mobile`.

### Task 22: Make guest requests responsive

**Files:** Modify `frontend/assets/css/guest-requests.css`.

- [ ] Allow request metadata/actions to wrap and keep long names/statuses from widening the page.
- [ ] Add a test and commit `fix: wrap guest request surfaces on mobile`.

### Task 23: Make API docs responsive

**Files:** Modify `frontend/assets/css/api-docs.css`.

- [ ] Collapse the documentation navigation/content split below its existing breakpoint and keep code blocks scrollable inside their own surface.
- [ ] Add a test and commit `fix: collapse API docs layout on mobile`.

### Task 24: Make staff reports responsive

**Files:** Modify `frontend/assets/css/staff-reports.css`.

- [ ] Stack report metadata and pagination controls at 290px without changing report content.
- [ ] Add a test and commit `fix: stack staff report controls on mobile`.

### Task 25: Audit inline dashboard rules

**Files:** Inspect and, only if required, modify `frontend/features/staff/pages/dashboard/dashboard.html`.

- [ ] Move no markup unless the inline rule itself creates unavoidable overflow; prefer a scoped stylesheet override.
- [ ] Add a test for the final inline rule state and commit `fix: keep staff dashboard inline surfaces fluid` only if a source change is required.

### Task 26: Add a full feature CSS audit test

**Files:** Modify `frontend/tests/responsive-layout.test.js`.

- [ ] Enumerate each audited stylesheet and assert it contains a narrow-width rule or a documented inner scroll boundary where its source has a wide layout.
- [ ] Run the focused test and commit `test: audit feature styles for 290px support`.

### Task 27: Add a viewport contract fixture

**Files:** Modify `frontend/tests/responsive-layout.test.js`.

- [ ] Add a minimal HTML/CSS fixture string that validates the shared shell's 290px width arithmetic: viewport width, border-box card, and content padding must not exceed the viewport.
- [ ] Run the focused test and commit `test: cover 290px shell width arithmetic`.

### Task 28: Document the supported narrow viewport

**Files:** Modify `README.md` in the frontend/operations section.

- [ ] Document the supported minimum viewport as 290px and the intentional inner scrolling behavior for dense tables/code.
- [ ] Keep the documentation concise and commit `docs: document narrow viewport support`.

### Task 29: Run the complete frontend gate

**Files:** No source changes; use the existing test suite.

- [ ] Run `npm test` from the repository root and record the output.
- [ ] If a regression appears, add the smallest test-first fix as a new commit rather than weakening a test.
- [ ] Commit only if a real regression fix is required; otherwise continue without an empty commit.

### Task 30: Run backend quality checks

**Files:** No source changes unless the check exposes a directly related issue.

- [ ] Run `composer run quality` from `backend` with the CI timeout discipline.
- [ ] If backend dependencies are unavailable, record the exact blocker and do not alter backend behavior to bypass it.

### Task 31: Review the final diff and responsive contract

**Files:** All changed files.

- [ ] Run `git diff --check`, inspect `git diff --stat`, and verify no unrelated files are staged.
- [ ] Re-run `node --test frontend/tests/responsive-layout.test.js` and `npm test`.
- [ ] Commit any narrowly scoped review correction with Margaret's identity.

### Task 32: Push and verify `master`

**Files:** Git metadata only.

- [ ] Confirm `origin` is `https://github.com/adriaanamd/scan2borrow-copy.git`.
- [ ] Push `master` with the Margaret-authenticated GitHub credential path.
- [ ] Verify the remote branch head and, when available, the corresponding GitHub Actions run.
