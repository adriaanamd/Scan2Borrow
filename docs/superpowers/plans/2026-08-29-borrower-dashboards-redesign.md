# Borrower Dashboard Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the student and teacher dashboards with the same existing content and workflows, using distinct Organic and Swiss visual systems without changing backend behavior.

**Architecture:** Keep the existing feature-owned HTML templates and page controllers. Add one focused stylesheet containing shared borrower-dashboard layout primitives plus strictly scoped student and teacher tokens, then add role classes to each template while preserving every JavaScript DOM ID and API contract. Use source-contract tests for markup/style guarantees and the existing full frontend suite for regression coverage.

**Tech Stack:** HTML, CSS, vanilla ES modules, Bootstrap 5.3.3 already used by the pages, Node.js native test runner, existing SVG icon system.

## Global Constraints

- This is a frontend-only redesign; do not add database fields, migrations, or backend endpoints.
- Keep all current dashboard content and actions; do not invent data or add unrelated modules.
- Preserve existing DOM IDs used by JavaScript controllers, including profile fields, statistics, loan tables, borrow/return forms, cart hosts, and modal targets.
- Preserve bulk borrowing, return, receipt, due-date, fine, overdue, recommendation, capacity, and achievement behavior.
- Student tokens: sand `#E8DCC7`, oat `#D4B895`, sage `#8B9D83`, moss `#606C38`, clay `#B08B6E`, terracotta `#C66B3D`, ochre `#C08E3A`.
- Teacher tokens: white or neutral `#F7F7F8` surfaces and Yves Klein blue `#002FA7`.
- Student uses warm humanist typography and 16-24px rounded panels with restrained 1-3% grain.
- Teacher uses Helvetica Neue/Arial-style sans typography, visible 1px rules, compact panels, and tabular numerals with no grain.
- Existing semantic danger, warning, success, and availability colors remain readable and are not replaced by decorative colors.
- Preserve labels, semantic headings, keyboard focus, visible focus rings, sufficient contrast, accessible button names, and `prefers-reduced-motion` support.
- Do not use emoji glyphs as dashboard icon substitutes; reuse the existing SVG icon system where icons are needed.
- Every implementation slice must end in a real, non-empty commit. The plan contains 22 implementation commits, exceeding the requested minimum of 20.

## File Map

- Create: `frontend/assets/css/borrower-dashboards.css` — shared borrower layout primitives and scoped student/teacher visual tokens.
- Create: `frontend/tests/borrower-dashboard-redesign.test.js` — dashboard markup, stylesheet, content-preservation, and accessibility source contracts.
- Modify: `frontend/features/student/pages/dashboard/dashboard.html` — add student scope classes, stylesheet link, semantic wrappers, and remove dashboard-specific inline visual styling without changing IDs or actions.
- Modify: `frontend/features/teacher/pages/dashboard/dashboard.html` — add teacher scope classes, stylesheet link, semantic wrappers, and remove dashboard-specific inline visual styling without changing IDs or actions.
- Do not modify: `frontend/features/student/pages/dashboard/student-dashboard.page.js` and `frontend/features/teacher/pages/dashboard/teacher-dashboard.page.js` unless a presentation-only class hook is proven necessary by a failing test.
- Do not modify: backend files, migrations, API routes, or shared staff/admin/guest styles except for selectors explicitly scoped to `.borrower-dashboard`.

---

### Task 1: Add the dashboard redesign contract tests

**Files:**
- Create: `frontend/tests/borrower-dashboard-redesign.test.js`

**Interfaces:**
- Produces source-contract tests for the two dashboard templates and the future stylesheet.
- Consumes the existing `fs`, `path`, and `node:test` patterns used by `frontend/tests/quantity-display.test.js`.

- [ ] **Step 1: Write the failing tests**

Create tests that assert the student template contains `borrower-dashboard borrower-dashboard--student`, the teacher template contains `borrower-dashboard borrower-dashboard--teacher`, both templates link `/scan2borrow/frontend/assets/css/borrower-dashboards.css`, both retain `borrowForm`, `returnForm`, `current-loans`, `bulkBorrowItems`, `bulkBorrowCount`, `borrowModal`, and `returnModal`, and the stylesheet contains the approved palette tokens.

```js
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

test('student and teacher dashboards expose their redesign scopes and shared stylesheet', () => {
  const student = read('features/student/pages/dashboard/dashboard.html');
  const teacher = read('features/teacher/pages/dashboard/dashboard.html');
  const href = '/scan2borrow/frontend/assets/css/borrower-dashboards.css';

  assert.match(student, /class="[^"]*borrower-dashboard[^"]*borrower-dashboard--student/);
  assert.match(teacher, /class="[^"]*borrower-dashboard[^"]*borrower-dashboard--teacher/);
  assert.equal(student.includes(href), true);
  assert.equal(teacher.includes(href), true);
});

test('borrower dashboard content and interaction IDs remain intact', () => {
  for (const relative of [
    'features/student/pages/dashboard/dashboard.html',
    'features/teacher/pages/dashboard/dashboard.html',
  ]) {
    const source = read(relative);
    for (const id of ['borrowForm', 'returnForm', 'current-loans', 'bulkBorrowItems', 'bulkBorrowCount', 'borrowModal', 'returnModal']) {
      assert.match(source, new RegExp(`(?:id|data-bs-target)="[^"#]*#?${id}`), `${relative} must retain ${id}`);
    }
  }
});

test('approved student and teacher design tokens are present and role-scoped', () => {
  const css = read('assets/css/borrower-dashboards.css');
  for (const token of ['#E8DCC7', '#D4B895', '#8B9D83', '#606C38', '#B08B6E', '#C66B3D', '#C08E3A', '#F7F7F8', '#002FA7']) {
    assert.match(css, new RegExp(token.replace('#', '\\#')));
  }
  assert.match(css, /\.borrower-dashboard--student/);
  assert.match(css, /\.borrower-dashboard--teacher/);
});
```

- [ ] **Step 2: Run the contract tests and verify they fail for the missing implementation**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL because the new stylesheet, dashboard scope classes, and stylesheet links do not yet exist. Do not change production files in this step.

- [ ] **Step 3: Commit the red contract test**

```bash
git add frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "test: define borrower dashboard redesign contracts"
```

### Task 2: Add the shared borrower-dashboard stylesheet shell

**Files:**
- Create: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Produces `.borrower-dashboard` layout and role token variables consumed by later template classes.

- [ ] **Step 1: Implement shared shell and token declarations**

Start the stylesheet with the shared scope and exact role tokens:

```css
.borrower-dashboard {
  min-height: 100vh;
}

.borrower-dashboard--student {
  --borrower-surface: #e8dcc7;
  --borrower-panel: #d4b895;
  --borrower-accent: #8b9d83;
  --borrower-deep: #606c38;
  --borrower-clay: #b08b6e;
  --borrower-terracotta: #c66b3d;
  --borrower-ochre: #c08e3a;
  --borrower-radius: 22px;
}

.borrower-dashboard--teacher {
  --borrower-surface: #f7f7f8;
  --borrower-panel: #ffffff;
  --borrower-accent: #002fa7;
  --borrower-deep: #002fa7;
  --borrower-radius: 4px;
}

.borrower-dashboard .content {
  background: var(--borrower-surface);
  min-height: calc(100vh - 76px);
}
```

- [ ] **Step 2: Run the contract tests and verify they pass**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: the token test passes; scope/link tests remain failing until the templates are updated.

- [ ] **Step 3: Commit the stylesheet shell**

```bash
git add frontend/assets/css/borrower-dashboards.css
git commit -m "style: add borrower dashboard visual tokens"
```

### Task 3: Add shared layout primitives

**Files:**
- Modify: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Produces shared selectors `.borrower-dashboard__hero`, `.borrower-dashboard__stats`, `.borrower-dashboard__work-grid`, `.borrower-dashboard__panel`, and `.borrower-dashboard__table`.

- [ ] **Step 1: Extend the failing contract test**

Add assertions for `.borrower-dashboard__hero`, `.borrower-dashboard__stats`, `.borrower-dashboard__work-grid`, `.borrower-dashboard__panel`, and `.borrower-dashboard__table` in the stylesheet.

- [ ] **Step 2: Run the focused test and verify the new assertions fail**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL on the missing shared selectors.

- [ ] **Step 3: Implement the shared primitives**

Use grid/flex layout, no role-specific colors, and keep the existing `.main`, `.topbar`, and `.content` geometry compatible with the shared navigation. Panels must use `var(--borrower-panel)`, `var(--borrower-accent)`, and `var(--borrower-radius)` rather than redefining role colors.

- [ ] **Step 4: Run the focused test and verify it passes**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: all current contract assertions for tokens and primitives pass.

- [ ] **Step 5: Commit the shared primitives**

```bash
git add frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "style: add shared borrower dashboard layout"
```

### Task 4: Add the stylesheet links and role scope classes

**Files:**
- Modify: `frontend/features/student/pages/dashboard/dashboard.html`
- Modify: `frontend/features/teacher/pages/dashboard/dashboard.html`

**Interfaces:**
- Produces the body classes and stylesheet link required by all subsequent dashboard styles.

- [ ] **Step 1: Extend the contract test for body classes and stylesheet order**

Assert that each dashboard links `style.css` before `borrower-dashboards.css` and that the body has its expected role class.

- [ ] **Step 2: Run the test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL because neither body has the new class or link.

- [ ] **Step 3: Update both templates**

Change the student body to `class="borrower-dashboard borrower-dashboard--student"` and the teacher body to `class="borrower-dashboard borrower-dashboard--teacher"`. Add the shared stylesheet link after `style.css` and before page-specific scripts. Do not remove the existing Bootstrap, font, confirmation, navbar, or module links.

- [ ] **Step 4: Run the focused test and the existing page tests**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/student-pages.test.js frontend/tests/teacher-services.test.js`

Expected: all tests pass.

- [ ] **Step 5: Commit the role scopes**

```bash
git add frontend/features/student/pages/dashboard/dashboard.html frontend/features/teacher/pages/dashboard/dashboard.html frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "feat: scope borrower dashboards by role"
```

### Task 5: Add the student Organic surface and typography

**Files:**
- Modify: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Produces student-only surface, type, grain, link, and focus rules under `.borrower-dashboard--student`.

- [ ] **Step 1: Add student style assertions**

Assert the stylesheet includes the student background, `Fraunces`, `border-radius: 22px`, and a `prefers-reduced-motion` block.

- [ ] **Step 2: Run the focused test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL on the student Organic declarations.

- [ ] **Step 3: Implement the student surface**

Use `background: var(--borrower-surface)`, `font-family: Fraunces, Georgia, serif` for student display headings, a warm system sans fallback for controls, 22px rounded panels, and a low-opacity decorative SVG grain on `.borrower-dashboard--student .content::before`. Set `pointer-events: none`, `aria-hidden` is not needed on CSS, and keep grain opacity between `.01` and `.03`.

- [ ] **Step 4: Run the focused test and verify it passes**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: all student surface assertions pass.

- [ ] **Step 5: Commit the student surface**

```bash
git add frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "style: establish student organic dashboard surface"
```

### Task 6: Add the teacher Swiss surface and typography

**Files:**
- Modify: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Produces teacher-only surface, type, hairline, and tabular-number rules under `.borrower-dashboard--teacher`.

- [ ] **Step 1: Add teacher style assertions**

Assert the stylesheet includes `#f7f7f8`, `#002fa7`, `Helvetica Neue`, `font-variant-numeric: tabular-nums`, and `border: 1px solid` inside a teacher-scoped rule.

- [ ] **Step 2: Run the focused test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL on the missing teacher declarations.

- [ ] **Step 3: Implement the teacher surface**

Use `font-family: "Helvetica Neue", Arial, sans-serif`, neutral `#f7f7f8` surfaces, visible 1px rules, and `font-variant-numeric: tabular-nums` on statistics, dates, and quantities. Do not add grain or warm student colors to teacher selectors.

- [ ] **Step 4: Run the focused test and verify it passes**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: all teacher surface assertions pass.

- [ ] **Step 5: Commit the teacher surface**

```bash
git add frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "style: establish teacher Swiss dashboard surface"
```

### Task 7: Restructure the student hero/profile block

**Files:**
- Modify: `frontend/features/student/pages/dashboard/dashboard.html`
- Modify: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Keeps `borrower-name`, `borrower-meta`, `borrower-barcode`, `lib-barcode`, and existing borrow/return button targets unchanged.
- Produces `.student-dashboard__hero`, `.student-dashboard__identity`, and `.student-dashboard__library-card` presentation hooks.

- [ ] **Step 1: Add failing student hero structure assertions**

Assert the student template contains the new hero, identity, and library-card classes while retaining the existing profile IDs and both modal targets.

- [ ] **Step 2: Run the focused test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL because the new classes do not exist.

- [ ] **Step 3: Add the student hero classes and CSS**

Add classes to the existing wrapper elements without changing their text, IDs, `data-bs-toggle`, or `data-bs-target` values. Style the hero as a warm layered reading surface, keep the library barcode visible, and use the existing SVG barcode output.

- [ ] **Step 4: Run focused student tests**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/student-pages.test.js`

Expected: PASS with unchanged student controller boundaries.

- [ ] **Step 5: Commit the student hero**

```bash
git add frontend/features/student/pages/dashboard/dashboard.html frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "style: redesign student profile hero"
```

### Task 8: Restructure the teacher hero/profile block

**Files:**
- Modify: `frontend/features/teacher/pages/dashboard/dashboard.html`
- Modify: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Keeps `teacher-name`, `teacher-meta`, `teacher-barcode`, `lib-barcode`, and existing action targets unchanged.
- Produces `.teacher-dashboard__hero`, `.teacher-dashboard__identity`, and `.teacher-dashboard__library-card` hooks.

- [ ] **Step 1: Add failing teacher hero structure assertions**

Assert the teacher template contains the new hero, identity, and library-card classes while retaining profile IDs and borrow/return targets.

- [ ] **Step 2: Run the focused test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL on the missing teacher classes.

- [ ] **Step 3: Add teacher hero classes and CSS**

Add classes to existing wrappers only. Use a ruled white/neutral hero with a blue folio marker, keep library-card content and barcode unchanged, and leave the teacher controller untouched.

- [ ] **Step 4: Run focused teacher tests**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/teacher-services.test.js`

Expected: PASS.

- [ ] **Step 5: Commit the teacher hero**

```bash
git add frontend/features/teacher/pages/dashboard/dashboard.html frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "style: redesign teacher profile hero"
```

### Task 9: Redesign the student statistics strip

**Files:**
- Modify: `frontend/features/student/pages/dashboard/dashboard.html`
- Modify: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Keeps `active-count`, `overdue-count`, `fine-total`, and `on-time-rate` unchanged.
- Produces `.student-dashboard__stat` hooks and student-specific statistic styling.

- [ ] **Step 1: Add failing student stat assertions**

Assert the four existing statistic IDs remain and that each stat wrapper has `.student-dashboard__stat`.

- [ ] **Step 2: Run the focused test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL on the missing stat hook.

- [ ] **Step 3: Add the student stat hook and CSS**

Use the approved sage/terracotta/ochre semantic accents, enlarge the numerical values, and keep labels readable. Do not change statistic text or data rendering.

- [ ] **Step 4: Run focused student tests**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/student-pages.test.js`

Expected: PASS.

- [ ] **Step 5: Commit the student stats**

```bash
git add frontend/features/student/pages/dashboard/dashboard.html frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "style: redesign student dashboard statistics"
```

### Task 10: Redesign the teacher statistics strip

**Files:**
- Modify: `frontend/features/teacher/pages/dashboard/dashboard.html`
- Modify: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Keeps `active-count`, `overdue-count`, `fine-total`, and `on-time-rate` unchanged.
- Produces `.teacher-dashboard__stat` hooks and teacher-specific statistic styling.

- [ ] **Step 1: Add failing teacher stat assertions**

Assert the four existing statistic IDs remain and that each stat wrapper has `.teacher-dashboard__stat`.

- [ ] **Step 2: Run the focused test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL on the missing stat hook.

- [ ] **Step 3: Add the teacher stat hook and CSS**

Use blue rules, hairline separators, and tabular numerals. Keep danger, warning, and success meanings intact.

- [ ] **Step 4: Run focused teacher tests**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/teacher-services.test.js`

Expected: PASS.

- [ ] **Step 5: Commit the teacher stats**

```bash
git add frontend/features/teacher/pages/dashboard/dashboard.html frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "style: redesign teacher dashboard statistics"
```

### Task 11: Build the student work-area composition

**Files:**
- Modify: `frontend/features/student/pages/dashboard/dashboard.html`
- Modify: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Keeps `capacity-ring`, `capacity-value`, `capacity-remaining`, `capacity-limit`, `due-soon`, `recommendation-reason`, `recommendations`, `achievement-count`, and `achievements` unchanged.
- Produces `.student-dashboard__work-grid`, `.student-dashboard__panel`, `.student-dashboard__shelf`, and `.student-dashboard__achievement-grid` hooks.

- [ ] **Step 1: Add failing student work-area assertions**

Assert the existing content IDs are inside the new work-grid and panel hooks.

- [ ] **Step 2: Run the focused test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL on the new composition hooks.

- [ ] **Step 3: Add student work-area hooks and CSS**

Use an asymmetric two-column grid at desktop widths. Treat recommendations as the shelf motif, use the existing dynamic recommendation nodes, and keep capacity/due-soon and achievements as distinct panels. Do not add sample books or fake achievement values.

- [ ] **Step 4: Run focused student tests**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/student-pages.test.js frontend/tests/quantity-display.test.js`

Expected: PASS.

- [ ] **Step 5: Commit the student work area**

```bash
git add frontend/features/student/pages/dashboard/dashboard.html frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "style: compose student dashboard work area"
```

### Task 12: Build the teacher work-area composition

**Files:**
- Modify: `frontend/features/teacher/pages/dashboard/dashboard.html`
- Modify: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Keeps teacher borrow/return forms, cart IDs, due-date fields, error/message regions, and current activity IDs unchanged.
- Produces `.teacher-dashboard__work-grid`, `.teacher-dashboard__panel`, `.teacher-dashboard__desk-rail`, and `.teacher-dashboard__activity` hooks.

- [ ] **Step 1: Add failing teacher work-area assertions**

Assert the teacher borrow and return forms plus `bulkBorrowItems`, `bulkBorrowCount`, `borrow-error`, and `borrow-message` are inside the new work-area hooks.

- [ ] **Step 2: Run the focused test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL on the new teacher composition hooks.

- [ ] **Step 3: Add teacher work-area hooks and CSS**

Use the desk rail for actions and the larger activity area for current status. Keep all current form fields, due-date behavior, cart controls, and error messages unchanged.

- [ ] **Step 4: Run focused teacher tests**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/teacher-services.test.js`

Expected: PASS.

- [ ] **Step 5: Commit the teacher work area**

```bash
git add frontend/features/teacher/pages/dashboard/dashboard.html frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "style: compose teacher dashboard work area"
```

### Task 13: Redesign the student active-loans table

**Files:**
- Modify: `frontend/features/student/pages/dashboard/dashboard.html`
- Modify: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Keeps `current-loans`, six table columns, quantities, statuses, and receipt links unchanged.
- Produces `.student-dashboard__loans` and `.student-dashboard__table` hooks.

- [ ] **Step 1: Add failing student table assertions**

Assert the student table retains headers `Book`, `Quantity`, `Borrowed`, `Due`, `Status`, and `Receipt`, plus `current-loans`.

- [ ] **Step 2: Run the focused test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL on the new table hooks.

- [ ] **Step 3: Add student table hooks and CSS**

Use a shelf-edge title treatment, readable quantity numerals, clear status badges, and horizontal scrolling below tablet width. Do not hide quantity or receipt columns.

- [ ] **Step 4: Run focused student tests**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/quantity-display.test.js`

Expected: PASS.

- [ ] **Step 5: Commit the student loans table**

```bash
git add frontend/features/student/pages/dashboard/dashboard.html frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "style: redesign student active loans table"
```

### Task 14: Redesign the teacher active-loans table

**Files:**
- Modify: `frontend/features/teacher/pages/dashboard/dashboard.html`
- Modify: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Keeps `current-loans`, six table columns, quantities, statuses, and receipt links unchanged.
- Produces `.teacher-dashboard__loans` and `.teacher-dashboard__table` hooks.

- [ ] **Step 1: Add failing teacher table assertions**

Assert the teacher table retains the same six headers and `current-loans`.

- [ ] **Step 2: Run the focused test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL on the new teacher table hooks.

- [ ] **Step 3: Add teacher table hooks and CSS**

Use visible hairline rules, blue folio accents, tabular numerals, and a strong header baseline. Keep all six columns readable through horizontal scrolling on small screens.

- [ ] **Step 4: Run focused teacher tests**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/teacher-services.test.js frontend/tests/quantity-display.test.js`

Expected: PASS.

- [ ] **Step 5: Commit the teacher loans table**

```bash
git add frontend/features/teacher/pages/dashboard/dashboard.html frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "style: redesign teacher active loans table"
```

### Task 15: Restyle the student borrow and return modals

**Files:**
- Modify: `frontend/features/student/pages/dashboard/dashboard.html`
- Modify: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Keeps `borrowModal`, `returnModal`, `borrowForm`, `returnForm`, `bulk-scan-barcode`, `bulk-scan-add`, `bulkBorrowItems`, `bulkBorrowCount`, and submit actions unchanged.

- [ ] **Step 1: Add failing student modal contract assertions**

Assert the student template retains both form IDs, modal IDs, scanner/cart IDs, `data-bs-dismiss`, `data-bs-toggle`, and `data-bs-target` attributes.

- [ ] **Step 2: Run the focused test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL on the student modal scope hook.

- [ ] **Step 3: Add student modal hooks and CSS**

Add `.student-dashboard__modal` and `.student-dashboard__cart` classes to existing modal surfaces. Use warm panel colors, rounded 22px surfaces, clear scan input focus, and an obvious cart count. Do not alter form method, fields, or submit behavior.

- [ ] **Step 4: Run focused student tests**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/student-pages.test.js frontend/tests/confirmation.test.js`

Expected: PASS.

- [ ] **Step 5: Commit the student modals**

```bash
git add frontend/features/student/pages/dashboard/dashboard.html frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "style: restyle student borrow return modals"
```

### Task 16: Restyle the teacher borrow and return modals

**Files:**
- Modify: `frontend/features/teacher/pages/dashboard/dashboard.html`
- Modify: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Keeps `borrowModal`, `returnModal`, `borrowForm`, `returnForm`, scanner/cart IDs, `due_date`, error/message regions, and submit actions unchanged.

- [ ] **Step 1: Add failing teacher modal contract assertions**

Assert the teacher template retains both form IDs, modal IDs, scanner/cart IDs, `due_date`, `borrow-error`, and `borrow-message`.

- [ ] **Step 2: Run the focused test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL on the teacher modal scope hook.

- [ ] **Step 3: Add teacher modal hooks and CSS**

Use white/neutral surfaces, blue rules, compact form spacing, and visible focus states. Keep teacher due-date input and return workflow unchanged.

- [ ] **Step 4: Run focused teacher tests**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/teacher-services.test.js frontend/tests/confirmation.test.js`

Expected: PASS.

- [ ] **Step 5: Commit the teacher modals**

```bash
git add frontend/features/teacher/pages/dashboard/dashboard.html frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "style: restyle teacher borrow return modals"
```

### Task 17: Replace dashboard-specific inline styling with scoped classes

**Files:**
- Modify: `frontend/features/student/pages/dashboard/dashboard.html`
- Modify: `frontend/features/teacher/pages/dashboard/dashboard.html`
- Modify: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Produces a maintainable CSS boundary without changing content or controller selectors.

- [ ] **Step 1: Add a failing inline-style contract**

Assert the dashboard templates contain no inline `style` attributes on hero headings, modal headers, or dashboard layout wrappers, while allowing the dynamic ring custom-property declaration and required SVG attributes.

- [ ] **Step 2: Run the focused test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL on existing dashboard inline styling.

- [ ] **Step 3: Move the affected declarations into role-scoped CSS**

Replace visual inline declarations such as hero padding/background and modal header backgrounds with the new scoped classes. Preserve inline `style` used by dynamic ring CSS custom properties if the controller or existing markup requires it; the test must exclude `--val` and `--ring-color` cases explicitly.

- [ ] **Step 4: Run focused tests**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/student-pages.test.js frontend/tests/teacher-services.test.js`

Expected: PASS.

- [ ] **Step 5: Commit the CSS boundary cleanup**

```bash
git add frontend/features/student/pages/dashboard/dashboard.html frontend/features/teacher/pages/dashboard/dashboard.html frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "refactor: move borrower dashboard styling into scoped css"
```

### Task 18: Add accessibility and motion states

**Files:**
- Modify: `frontend/assets/css/borrower-dashboards.css`
- Modify: `frontend/tests/borrower-dashboard-redesign.test.js`

**Interfaces:**
- Produces role-scoped focus, reduced-motion, status, and decorative texture rules.

- [ ] **Step 1: Add failing accessibility/style assertions**

Assert the stylesheet includes `:focus-visible`, `prefers-reduced-motion: reduce`, status text selectors, and a selector that marks decorative grain as `pointer-events: none`.

- [ ] **Step 2: Run the focused test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL on any missing accessibility rule.

- [ ] **Step 3: Implement the states**

Add visible focus outlines for links, buttons, inputs, and selects; disable transitions under reduced motion; ensure overdue/fine/availability styles include text or border treatment in addition to color; keep the grain pseudo-element non-interactive.

- [ ] **Step 4: Run focused tests**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/shared-components.test.js frontend/tests/confirmation.test.js`

Expected: PASS.

- [ ] **Step 5: Commit accessibility states**

```bash
git add frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "a11y: add borrower dashboard focus and motion states"
```

### Task 19: Add responsive dashboard layouts

**Files:**
- Modify: `frontend/assets/css/borrower-dashboards.css`
- Modify: `frontend/tests/borrower-dashboard-redesign.test.js`

**Interfaces:**
- Produces responsive rules for the shared dashboard shell, student work area, teacher desk rail, statistics, library card, modals, and tables.

- [ ] **Step 1: Add failing responsive assertions**

Assert the stylesheet includes media queries at `980px`, `768px`, and `576px`, plus `overflow-x: auto` for both loan tables.

- [ ] **Step 2: Run the focused test and verify it fails**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js`

Expected: FAIL on the missing responsive rules.

- [ ] **Step 3: Implement responsive rules**

At 980px, collapse the work grids to one column. At 768px, stack hero identity/library-card content and make primary actions full-width when necessary. At 576px, use a two-column stat grid, reduce panel padding, preserve readable heading sizes, and set `.student-dashboard__table` and `.teacher-dashboard__table` wrappers to horizontal scrolling.

- [ ] **Step 4: Run focused tests**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/served-parity.test.js`

Expected: PASS.

- [ ] **Step 5: Commit responsive layouts**

```bash
git add frontend/assets/css/borrower-dashboards.css frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "style: add responsive borrower dashboard layouts"
```

### Task 20: Assert content parity and controller boundaries

**Files:**
- Modify: `frontend/tests/borrower-dashboard-redesign.test.js`

**Interfaces:**
- Produces regression contracts proving the redesign did not remove required content or JavaScript boundaries.

- [ ] **Step 1: Add parity assertions**

Assert the student template retains profile, four stats, capacity, due-soon, recommendations, achievements, current loans, borrow modal, return modal, and its module entry. Assert the teacher template retains profile, four stats, current loans, borrow modal, return modal, due date field, cart IDs, and its module entry. Assert both controllers still contain `render`, `renderLoans`, `renderCart`, and `submitCart`.

- [ ] **Step 2: Run the focused tests and verify they pass**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/student-pages.test.js frontend/tests/teacher-services.test.js frontend/tests/quantity-display.test.js`

Expected: PASS with no controller changes.

- [ ] **Step 3: Commit content parity contracts**

```bash
git add frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "test: lock borrower dashboard content parity"
```

### Task 21: Assert icon and stylesheet integration

**Files:**
- Modify: `frontend/tests/borrower-dashboard-redesign.test.js`

**Interfaces:**
- Produces a source contract ensuring the redesign does not introduce emoji icon markup or an admin stylesheet dependency.

- [ ] **Step 1: Add integration assertions**

Assert neither dashboard links `/frontend/assets/css/admin-overview.css`, and assert newly added dashboard action labels do not contain numeric character references for emoji icons. Preserve existing labels and allow the shared `icons.js` integration to supply SVG icons at runtime.

- [ ] **Step 2: Run focused tests and verify they pass**

Run: `node --test frontend/tests/borrower-dashboard-redesign.test.js frontend/tests/layout-components.test.js`

Expected: PASS.

- [ ] **Step 3: Commit icon integration contracts**

```bash
git add frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "test: protect borrower dashboard icon integration"
```

### Task 22: Run full verification and prepare merge

**Files:**
- No source changes expected.

**Interfaces:**
- Verifies all dashboard redesign contracts, existing frontend behavior, repository formatting, and rendered page entry parity.

- [ ] **Step 1: Run the complete frontend suite**

Run: `npm test`

Expected: all tests pass with zero failures.

- [ ] **Step 2: Run source hygiene checks**

Run: `git diff --check`

Expected: no output and exit code 0.

- [ ] **Step 3: Inspect the final diff and commit graph**

Run: `git diff master...HEAD --stat; git log --oneline --decorate -24`

Expected: only the approved dashboard templates, borrower stylesheet, tests, design spec, and implementation plan are changed; the graph contains at least 20 non-empty implementation commits after the spec commit.

- [ ] **Step 4: Commit the verification record if test contract wording changed**

```bash
git add frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "test: finalize borrower dashboard verification contracts"
```

Only create this final commit when Step 1-3 required a real test-contract correction; do not create an empty commit.

- [ ] **Step 5: Review and merge**

Request a read-only code review for the full implementation range. After Critical and Important findings are resolved, merge the feature branch into `master` with a non-fast-forward merge, run `npm test` again on merged `master`, verify `git diff --check`, and remove only the temporary dashboard worktree and its feature branch.

## Self-Review Checklist

- Spec coverage: Tasks 2-6 cover both exact visual systems; Tasks 7-17 cover shared structure, both heroes, stats, work areas, tables, modals, and inline-style cleanup; Tasks 18-19 cover accessibility and responsive behavior; Tasks 20-21 cover content, icons, and integration; Task 22 covers full verification and merge.
- Placeholder scan: the plan contains no unfinished implementation markers, fake data, or unspecified design tokens.
- Type/selector consistency: all later tasks consume the selectors and IDs produced in earlier tasks; no controller method names or API fields are renamed.
- Commit requirement: Tasks 1-21 each define a non-empty commit, yielding 21 implementation commits before the final verification step, exceeding the requested 20.
