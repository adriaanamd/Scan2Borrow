# Manual Barcode Entry for Student and Teacher Borrowing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make manual barcode entry and camera scanning separate, reliable actions in every student and teacher borrow modal.

**Architecture:** Preserve the existing role-specific lookup APIs, `BulkBorrowCart`, scanner helper, and bulk-borrow payload. Update only the four borrower modal templates so the text input is a normal input, `Add copy` owns manual lookup, and a separate `Scan with camera` button owns scanner activation; add a focused frontend contract test for all four surfaces.

**Tech Stack:** Vanilla HTML, CSS classes already supplied by Bootstrap and Scan2Borrow role surfaces, ES modules, Node’s built-in test runner, PHP Composer quality scripts.

## Global Constraints

- The barcode input is a normal text input with no scanner target.
- `Add copy` performs the existing lookup-and-add path.
- Enter in the input performs the same add action.
- A separate `Scan with camera` button carries `data-scan-target="bulk-scan-barcode"`.
- A successful scan fills the input and returns focus to it; the user then confirms with `Add copy`.
- Preserve the existing role-specific modal styling, spacing, and button classes.
- Do not add an API endpoint, database migration, dependency, scanner-library change, or workflow change.
- Keep all existing test coverage; do not use focused test declarations or filters (`test.only`, skipped tests, weakened assertions, or disabled quality gates) to obtain a green result.
- Treat `npm test` and the backend `composer run quality` command as blocking verification gates.

---

## File map

- Create: `frontend/tests/manual-barcode-borrow.test.js` — contract tests for the four student/teacher borrow modals and their existing manual lookup/API wiring.
- Modify: `frontend/features/student/pages/dashboard/dashboard.html` — split the student dashboard’s manual-add and camera buttons.
- Modify: `frontend/features/student/pages/search/search.html` — move the scanner target from the student catalog input to a dedicated camera button.
- Modify: `frontend/features/teacher/pages/dashboard/dashboard.html` — split the teacher dashboard’s manual-add and camera buttons.
- Modify: `frontend/features/teacher/pages/borrow/borrow.html` — move the scanner target from the teacher catalog input to a dedicated camera button.
- Do not modify: `frontend/assets/js/core/scanner.js`, `frontend/app/shared/pages/borrower-search.page.js`, role page controllers, backend routes, or backend services; their existing behavior already supports the desired flow.

## Interfaces

- The four templates continue to expose `id="bulk-scan-barcode"`, `id="bulk-scan-add"`, and `id="bulkBorrowItems"` for the existing controllers.
- The new camera control in each template exposes `id="bulk-scan-camera"` and `data-scan-target="bulk-scan-barcode"` for `frontend/assets/js/core/scanner.js`.
- Student lookup paths remain `/scan2borrow/api/student/borrow/lookup`; teacher lookup paths remain `/scan2borrow/api/teacher/borrow/lookup`.

---

### Task 1: Separate manual barcode entry from camera scanning

**Files:**
- Create: `frontend/tests/manual-barcode-borrow.test.js`
- Modify: `frontend/features/student/pages/dashboard/dashboard.html`
- Modify: `frontend/features/student/pages/search/search.html`
- Modify: `frontend/features/teacher/pages/dashboard/dashboard.html`
- Modify: `frontend/features/teacher/pages/borrow/borrow.html`

**Interfaces:**
- Consumes: existing `bulk-scan-barcode` input, `bulk-scan-add` manual action, `BulkBorrowCart`, shared scanner helper, and role-specific lookup endpoints.
- Produces: each borrower modal has a typeable `bulk-scan-barcode` input with no `data-scan-target`, a manual `bulk-scan-add` button labeled `Add copy`, and a separate `bulk-scan-camera` button labeled `Scan with camera` targeting the input.

- [ ] **Step 1: Write the failing regression test**

Create `frontend/tests/manual-barcode-borrow.test.js` with this exact content:

```js
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

const surfaces = [
  {
    name: 'student dashboard',
    template: 'features/student/pages/dashboard/dashboard.html',
    controller: 'features/student/pages/dashboard/student-dashboard.page.js',
    lookupApi: '/scan2borrow/api/student/borrow/lookup',
  },
  {
    name: 'student catalog',
    template: 'features/student/pages/search/search.html',
    controller: 'features/student/pages/search/student-search.page.js',
    lookupApi: '/scan2borrow/api/student/borrow/lookup',
  },
  {
    name: 'teacher dashboard',
    template: 'features/teacher/pages/dashboard/dashboard.html',
    controller: 'features/teacher/pages/dashboard/teacher-dashboard.page.js',
    lookupApi: '/scan2borrow/api/teacher/borrow/lookup',
  },
  {
    name: 'teacher catalog',
    template: 'features/teacher/pages/borrow/borrow.html',
    controller: 'features/teacher/pages/borrow/teacher-borrow.page.js',
    lookupApi: '/scan2borrow/api/teacher/borrow/lookup',
  },
];

function elementBlock(source, element, id) {
  const match = source.match(
    new RegExp(`<${element}\\b[^>]*\\bid="${id}"[^>]*>[\\s\\S]*?<\\/${element}>`),
  );
  assert.ok(match, `Expected ${element}#${id} to exist`);
  return match[0];
}

function elementTag(source, element, id) {
  const match = source.match(
    new RegExp(`<${element}\\b[^>]*\\bid="${id}"[^>]*>`),
  );
  assert.ok(match, `Expected ${element}#${id} to exist`);
  return match[0];
}

test('borrow barcode inputs remain typeable and use a separate camera action', () => {
  for (const surface of surfaces) {
    const source = read(surface.template);
    const input = elementTag(source, 'input', 'bulk-scan-barcode');
    const addButton = elementBlock(source, 'button', 'bulk-scan-add');
    const cameraButton = elementBlock(source, 'button', 'bulk-scan-camera');

    assert.doesNotMatch(input, /data-scan-target/, `${surface.name} input must remain typeable`);
    assert.doesNotMatch(addButton, /data-scan-target/, `${surface.name} manual action must not open the scanner`);
    assert.match(addButton, /Add copy/, `${surface.name} must expose a manual add action`);
    assert.match(cameraButton, /data-scan-target="bulk-scan-barcode"/);
    assert.match(cameraButton, /aria-label="Scan with camera"/);
    assert.match(cameraButton, /Scan with camera/);
  }
});

test('borrow controllers keep Enter handling and role-specific lookup APIs', () => {
  const sharedCatalogController = read('app/shared/pages/borrower-search.page.js');
  assert.match(sharedCatalogController, /event\.key === "Enter"/);
  assert.match(sharedCatalogController, /lookupAndAdd\(input\.value\.trim\(\)\)/);

  for (const surface of surfaces) {
    const controller = read(surface.controller);
    assert.ok(controller.includes(surface.lookupApi), surface.name);
  }

  for (const controllerPath of [
    'features/student/pages/dashboard/student-dashboard.page.js',
    'features/teacher/pages/dashboard/teacher-dashboard.page.js',
  ]) {
    const controller = read(controllerPath);
    assert.match(controller, /event\.key === "Enter"/);
    assert.match(controller, /lookupAndAdd\(input\.value\.trim\(\)\)/);
  }
});
```

- [ ] **Step 2: Run the new test to verify it fails for the current markup**

Run from the repository root:

```powershell
node --test frontend/tests/manual-barcode-borrow.test.js
```

Expected: FAIL because the current student/teacher catalog text inputs carry `data-scan-target`, and the dashboard modals do not yet expose `id="bulk-scan-camera"`.

- [ ] **Step 3: Update the student dashboard borrow modal**

In `frontend/features/student/pages/dashboard/dashboard.html`, keep the hidden `book_barcode` input unchanged and replace the visible barcode label/input group with:

```html
<label class="form-label fw-semibold" for="bulk-scan-barcode">Scan or Enter Book Barcode</label>
<div class="input-group mb-3">
  <input
    type="text"
    id="bulk-scan-barcode"
    class="form-control"
    placeholder="Scan barcode or type here..."
    autofocus
  />
  <button
    type="button"
    id="bulk-scan-add"
    class="btn btn-outline-secondary"
  >
    Add copy
  </button>
  <button
    type="button"
    id="bulk-scan-camera"
    class="btn btn-outline-secondary"
    data-scan-target="bulk-scan-barcode"
    aria-label="Scan with camera"
  >
    Scan with camera
  </button>
</div>
```

- [ ] **Step 4: Update the student catalog borrow modal**

In `frontend/features/student/pages/search/search.html`, replace the existing `Scan a copy` input group with:

```html
<label class="form-label fw-semibold" for="bulk-scan-barcode">Scan a copy</label>
<div class="input-group mb-3">
  <input
    id="bulk-scan-barcode"
    class="form-control"
    placeholder="Scan barcode to add a copy"
  />
  <button type="button" id="bulk-scan-add" class="btn btn-outline-secondary">
    Add copy
  </button>
  <button
    type="button"
    id="bulk-scan-camera"
    class="btn btn-outline-secondary"
    data-scan-target="bulk-scan-barcode"
    aria-label="Scan with camera"
  >
    Scan with camera
  </button>
</div>
```

- [ ] **Step 5: Update the teacher dashboard borrow modal**

In `frontend/features/teacher/pages/dashboard/dashboard.html`, keep the hidden `book_barcode` input unchanged and replace the visible barcode input group with:

```html
<label class="form-label fw-semibold" for="bulk-scan-barcode">Scan or Enter Book Barcode</label>
<div class="input-group mb-3 teacher-borrow-modal__scan">
  <input
    type="text"
    id="bulk-scan-barcode"
    class="form-control"
    placeholder="Scan barcode or type here..."
    autofocus
  />
  <button
    type="button"
    class="btn btn-outline-secondary"
    id="bulk-scan-add"
  >
    Add copy
  </button>
  <button
    type="button"
    class="btn btn-outline-secondary"
    id="bulk-scan-camera"
    data-scan-target="bulk-scan-barcode"
    aria-label="Scan with camera"
  >
    Scan with camera
  </button>
</div>
```

- [ ] **Step 6: Update the teacher catalog borrow modal**

In `frontend/features/teacher/pages/borrow/borrow.html`, replace the existing `Add a copy` input group with:

```html
<label class="form-label fw-semibold" for="bulk-scan-barcode">Add a copy</label>
<div class="input-group mb-3">
  <input
    id="bulk-scan-barcode"
    class="form-control"
    placeholder="Scan or enter a barcode"
    autocomplete="off"
  />
  <button type="button" id="bulk-scan-add" class="btn btn-outline-secondary">
    Add copy
  </button>
  <button
    type="button"
    id="bulk-scan-camera"
    class="btn btn-outline-secondary"
    data-scan-target="bulk-scan-barcode"
    aria-label="Scan with camera"
  >
    Scan with camera
  </button>
</div>
```

Do not change the return-modal scanner targets, the hidden form barcode inputs, the cart IDs, or any role-specific endpoint/controller markup.

- [ ] **Step 7: Run the focused test to verify the implementation passes**

Run:

```powershell
node --test frontend/tests/manual-barcode-borrow.test.js
```

Expected: PASS with both tests passing and no warnings or errors.

- [ ] **Step 8: Run the full local quality gates**

Run the full frontend suite from the repository root:

```powershell
npm test
```

Expected: PASS with all existing frontend tests and the new regression tests passing.

Run the backend CI-equivalent quality gate from `backend`:

```powershell
composer validate --no-check-publish
composer run quality
```

Expected: Composer validation succeeds, PHPUnit passes, and PHPStan reports no errors. This feature does not change backend code, but the repository CI workflow treats the backend quality command as blocking.

- [ ] **Step 9: Check the final diff and commit the implementation**

Run:

```powershell
git diff --check
git status --short
git diff --stat
```

Expected: only the new frontend regression test and four borrower templates are modified; no whitespace errors are reported; no backend, workflow, dependency, or unrelated user files are changed.

Commit the implementation:

```powershell
git add -- frontend/tests/manual-barcode-borrow.test.js frontend/features/student/pages/dashboard/dashboard.html frontend/features/student/pages/search/search.html frontend/features/teacher/pages/dashboard/dashboard.html frontend/features/teacher/pages/borrow/borrow.html
git commit -m "fix: separate manual and scanned borrow barcodes"
```

Expected: one non-empty implementation commit is created after all focused and full verification commands pass.
