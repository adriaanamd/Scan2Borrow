# Barcode Print Page Fetch Binding Implementation Plan

> **For agentic workers:** Execute this plan task-by-task with verification checkpoints.

**Goal:** Make the barcode print page load saved batches without the browser `Illegal invocation` error.

**Architecture:** Keep the existing injectable `fetcher` seam for tests and callers. When no fetcher is supplied, bind the browser window’s native `fetch` to its owning window before storing it on the page object.

**Tech Stack:** Browser JavaScript modules, Node’s built-in test runner.

## Global Constraints

- Preserve the irreversible batch and PDF print workflow.
- Do not change API routes or batch data.
- Preserve the unrelated existing frontend edit.

### Task 1: Add the regression test

**Files:**
- Modify: `frontend/tests/barcode-printing.test.js`

- [ ] **Step 1: Import `BarcodePrintPage` and add a fake DOM/window test**

The test must install a fake global `fetch` that asserts it is called with the fake window as `this`, instantiate `BarcodePrintPage` without an explicit fetcher, load a valid token, and assert the saved batch is returned. Restore globals in `finally`.

- [ ] **Step 2: Run the focused test and verify it fails**

Run `node --test frontend/tests/barcode-printing.test.js`. Before the production fix, the test must fail because the fetch method is invoked with the `BarcodePrintPage` instance instead of the browser window.

### Task 2: Bind the browser fetch method

**Files:**
- Modify: `frontend/features/staff/pages/barcodes/barcodes.page.js`

- [ ] **Step 1: Use nullable injection defaults**

Change the constructor defaults to `fetcher = null` and `printer = null`, assign `this.windowObject` first, then set `this.fetcher` to the injected fetcher or `this.windowObject.fetch.bind(this.windowObject)`, and set the default printer to `() => this.windowObject.print()`.

- [ ] **Step 2: Run the focused test and verify it passes**

Run `node --test frontend/tests/barcode-printing.test.js`; all tests must pass with no warnings.

### Task 3: Run full verification

**Files:**
- Read: `frontend/features/staff/pages/barcodes/barcodes.page.js`
- Read: `frontend/tests/barcode-printing.test.js`

- [ ] **Step 1: Run the complete frontend suite**

Run `npm test`; require zero failed tests.

- [ ] **Step 2: Check the final diff**

Run `git diff --check` and `git status --short`; confirm only the regression test, print-page binding fix, and plan documentation are new changes, plus the pre-existing unrelated edit.
