# Dashboard Modal Stacking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Keep student, teacher, and staff/admin dashboard modals centered above their backdrops and interactive instead of placing them beneath the page content or at the bottom of the document.

**Architecture:** Preserve the existing dashboard content-layer stacking for ordinary direct children, but exclude modal roots from that selector. Bootstrap and the teacher custom fixed layer then retain responsibility for modal positioning and z-index without changing markup or borrowing behavior.

**Tech Stack:** Vanilla HTML/CSS, Bootstrap 5.3.3 modal behavior, Node.js built-in test runner, npm.

## Global Constraints

- Do not change modal markup, event handlers, API requests, or borrowing behavior.
- Ordinary borrower dashboard content must retain `position: relative; z-index: 1`.
- Modal roots must remain viewport-layered, centered, interactive, and outside normal document flow.
- Preserve existing responsive sizing, scrolling, and mobile footer behavior.
- Keep unrelated user changes untouched: `docs/superpowers/plans/2026-08-31-copy-history-audit-trail.md`, `problem-css/d9e501fa-277d-4a29-80cb-42125c2808c7.png`, and `uploads/112299-81e92dfebf90d638.jpg`.

---

### Task 1: Add a failing modal stacking regression test

**Files:**
- Create: `frontend/tests/modal-stacking.test.js`
- Read: `frontend/assets/css/borrower-dashboards.css`

**Interfaces:**
- Consumes: the shared borrower dashboard stylesheet as UTF-8 text.
- Produces: a regression test that requires the ordinary content-layer selector to exclude `.modal` roots.

- [ ] **Step 1: Write the failing test**

Create `frontend/tests/modal-stacking.test.js`:

```js
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const testDirectory = path.dirname(fileURLToPath(import.meta.url));
const stylesheetPath = path.resolve(
  testDirectory,
  '..',
  'assets',
  'css',
  'borrower-dashboards.css',
);

const stylesheet = fs.readFileSync(stylesheetPath, 'utf8');

test('borrower dashboard content layering excludes modal roots', () => {
  assert.match(
    stylesheet,
    /\.borrower-dashboard \.content > :not\(\.modal\)\s*\{\s*position:\s*relative;\s*z-index:\s*1;\s*\}/s,
  );
  assert.doesNotMatch(stylesheet, /\.borrower-dashboard \.content > \*\s*\{/s);
});
```

- [ ] **Step 2: Run the test to verify it fails for the expected reason**

Run:

```powershell
node --test frontend/tests/modal-stacking.test.js
```

Expected: `FAIL` because the stylesheet still contains `.borrower-dashboard .content > *` and does not contain the modal-excluding selector. This confirms the test detects the current bug rather than passing against existing behavior.

- [ ] **Step 3: Commit the failing test**

```powershell
git add frontend/tests/modal-stacking.test.js
git commit -m "test: cover dashboard modal stacking contract"
```

### Task 2: Exclude modal roots from the dashboard content layer

**Files:**
- Modify: `frontend/assets/css/borrower-dashboards.css:706-709`

**Interfaces:**
- Consumes: the failing selector contract from Task 1.
- Produces: CSS that layers ordinary dashboard children while leaving Bootstrap/custom modal roots to their modal styles.

- [ ] **Step 1: Replace the blanket direct-child selector**

Change only the selector in the existing rule:

```css
.borrower-dashboard .content > :not(.modal) {
  position: relative;
  z-index: 1;
}
```

Do not add a second high-specificity modal override and do not modify modal markup or JavaScript.

- [ ] **Step 2: Run the targeted regression test**

Run:

```powershell
node --test frontend/tests/modal-stacking.test.js
```

Expected: `PASS` with the modal-excluding selector present and the blanket selector absent.

- [ ] **Step 3: Commit the minimal CSS fix**

```powershell
git add frontend/assets/css/borrower-dashboards.css
git commit -m "fix: keep dashboard modals above page content"
```

### Task 3: Run the complete automated verification

**Files:**
- Read: all tests matched by `package.json`'s `test` script.

**Interfaces:**
- Consumes: the passing modal stacking regression test and existing frontend test suite.
- Produces: evidence that the CSS change introduces no frontend regressions.

- [ ] **Step 1: Run the full frontend test suite**

Run:

```powershell
npm test
```

Expected: all tests pass with no failures or unhandled errors.

- [ ] **Step 2: Check the final diff and worktree**

Run:

```powershell
git diff --check HEAD~1..HEAD
git status --short
```

Expected: no whitespace errors; only the intended test/CSS commits are present alongside the pre-existing unrelated untracked files.

### Task 4: Verify modal behavior in the browser

**Files:**
- Read: `frontend/features/student/pages/dashboard/dashboard.html`
- Read: `frontend/features/teacher/pages/dashboard/dashboard.html`
- Read: `frontend/features/staff/pages/dashboard/dashboard.html`

**Interfaces:**
- Consumes: the modal stacking fix from Task 2 and the running local Scan2Borrow application.
- Produces: manual confirmation of clickability, centering, viewport layering, and responsive behavior across roles.

- [ ] **Step 1: Verify the student dashboard**

Open `/scan2borrow/student/dashboard`, activate Borrow a Book, click into the barcode input, click Scan/Close, and then open Return a Book and Borrowing Complete. Confirm each dialog is centered in the viewport, above the backdrop, and not rendered at the page bottom.

- [ ] **Step 2: Verify the teacher dashboard**

Open `/scan2borrow/teacher/dashboard`, activate Borrow Books and Return a Book, and exercise the custom borrow layer's input, cart controls, close button, and submit controls. Confirm the fixed custom layer remains above the backdrop.

- [ ] **Step 3: Verify representative staff/admin dialogs**

Open `/scan2borrow/staff/dashboard` and activate the borrower overview, approval, and notification dialogs that are available. Confirm each remains centered and interactive.

- [ ] **Step 4: Verify a narrow viewport**

Repeat one student and one teacher dialog at a narrow mobile viewport. Confirm the dialog stays within the viewport, can scroll internally when needed, and the existing mobile footer layout remains intact.

- [ ] **Step 5: Commit any required verification-only documentation change**

No documentation or code changes are expected from this task. If browser verification exposes a new code defect, stop and create a separate failing test before making any additional fix.
