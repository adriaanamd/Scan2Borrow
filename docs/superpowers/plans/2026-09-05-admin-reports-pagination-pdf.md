# Admin Reports Pagination and Direct PDF Export Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the admin/staff Reports page fetch its complete filtered dataset, show 10-row client-side pagination with a `1–x of y` summary, and download all filtered records directly as a PDF without opening another tab.

**Architecture:** Keep the existing `/api/staff/reports` response unchanged and store its complete `headers`/`data` result in `ReportsPage`. Render a page slice for the screen while using the complete stored rows for PDF generation and legacy `?print=1` browser printing. Load pinned jsPDF and jsPDF-AutoTable browser assets only on the Reports template.

**Tech Stack:** Vanilla ES modules, native Node test runner, Bootstrap-compatible HTML/CSS, existing PHP JSON report API, jsPDF 2.5.2, jsPDF-AutoTable 3.8.4.

## Global Constraints

- Preserve existing report types (`borrowed`, `returned`, `overdue`, `inventory`) and `from`/`to` filters.
- Keep the backend report response and SQL queries unchanged.
- The screen page size is exactly 10 rows.
- PDF output and legacy print mode must include every fetched row, not only the visible page.
- Preserve text-safe rendering with `textContent`; do not introduce HTML interpolation for report values.
- Preserve existing print-readiness behavior and all existing frontend coverage.
- Do not stage or modify unrelated user files.

---

### Task 1: Add failing report pagination and PDF behavior tests

**Files:**
- Modify: `frontend/tests/staff-utils.test.js`

**Interfaces:**
- Consumes: `ReportsPage.load()`, the existing report fixture helpers, and injected service/window/document doubles.
- Produces: Regression coverage for full-data fetching, 10-row pagination, range status, filter reset, and all-row PDF generation.

- [ ] **Step 1: Extend the report fixture nodes with event and PDF-test support**

Update the existing `makeNode` test helper so nodes expose `addEventListener`, `click`, `disabled`, and `hidden` state while preserving the existing query and child behavior. Add selectors for `#download-report-pdf`, `#staff-report-pagination`, `#staff-report-range`, `#staff-report-previous`, and `#staff-report-next`.

- [ ] **Step 2: Write the failing pagination test**

Add a test with 25 fetched rows that loads the ReportsPage and asserts:

```js
assert.equal(nodes.get('tbody').children.length, 10);
assert.equal(nodes.get('#staff-report-range').textContent, '1–10 of 25');
assert.equal(nodes.get('#staff-report-previous').disabled, true);
assert.equal(nodes.get('#staff-report-next').disabled, false);
assert.equal(nodes.get('#staff-report-count').textContent, '25 records');
```

Click the next control and assert the table still has 10 rows, the range becomes `11–20 of 25`, and the service call count remains 1. Click next again and assert the range becomes `21–25 of 25` and the next control is disabled.

- [ ] **Step 3: Write the failing filter-reset test**

Use a service that returns two different report datasets for two `ReportsPage.load()` calls. Move the first dataset to page 2, change the window query to a new date filter, load again, and assert the range resets to `1–10 of <new total>` and the new API call includes the updated filters.

- [ ] **Step 4: Write the failing all-row PDF test**

Inject a `window.jspdf.jsPDF` fake whose `autoTable` records its `body` and whose `save` records the filename. Load 25 rows, move the screen to page 2, call `downloadPdf()`, and assert:

```js
assert.equal(pdfTableBody.length, 25);
assert.match(savedFilename, /overdue/);
```

This proves PDF export does not use the current 10-row screen slice.

- [ ] **Step 5: Run the focused tests to verify the new tests fail for missing behavior**

Run:

```powershell
node --test frontend/tests/staff-utils.test.js
```

Expected: the existing tests pass, while the new pagination/PDF assertions fail because the ReportsPage has no pagination state, controls, or direct PDF method yet.

### Task 2: Implement report state, pagination, and direct PDF generation

**Files:**
- Modify: `frontend/features/staff/pages/reports/reports.page.js`

**Interfaces:**
- Consumes: Existing `StaffReportService.load()` response `{ data: { report: { label, headers, data } } }`, DOM report controls, and `window.jspdf.jsPDF`.
- Produces: `ReportsPage.pageSize = 10`, `ReportsPage.currentPage`, `ReportsPage.reportRows`, `ReportsPage.renderPage()`, `ReportsPage.goToPage(page)`, `ReportsPage.downloadPdf()`, and complete-data print compatibility.

- [ ] **Step 1: Add bounded page and report state**

Initialize in the constructor:

```js
this.pageSize = 10;
this.currentPage = 1;
this.report = { label: 'Library Report', headers: [], data: [] };
this.filters = { type: 'borrowed', from: '', to: '' };
this.isPrintMode = false;
this.bindEvents = this.bindEvents.bind(this);
this.renderPage = this.renderPage.bind(this);
this.downloadPdf = this.downloadPdf.bind(this);
```

Make `start()` call `bindEvents()` before `load()`.

- [ ] **Step 2: Make loading retain the complete fetched report and reset the page**

In `load()`, derive the current filters as today, set `this.filters`, set `this.currentPage = 1`, set `this.isPrintMode = query.has('print')`, and call the existing service. On success, assign the complete `response.data?.report || {}` to `this.report`, call `render(this.report, filters.from, filters.to)`, update export links, enable the PDF button, and retain the existing `?print=1` readiness flow.

Wrap the service call in `try/catch`. On failure, clear `this.report.data`, render an empty table state, disable PDF, set `staff-report-status` to `Unable to load report data.`, and return `null` without invoking print.

- [ ] **Step 3: Split full-report metadata from paginated table rendering**

Keep `render(report, from, to)` as the public rendering boundary, but have it normalize headers and all rows into `this.report`, update the title/period/generated/count metadata from all rows, and call `renderPage()`.

Implement `renderPage()` to select:

```js
const rows = this.isPrintMode
  ? this.report.data
  : this.report.data.slice((this.currentPage - 1) * this.pageSize, this.currentPage * this.pageSize);
```

Render the selected rows using the existing text-safe cell creation. Keep the empty-state row when no rows exist. Update the range summary to `0–0 of 0`, `1–10 of 25`, or the appropriate final partial range. Hide pagination in print mode; otherwise show it whenever a report has loaded, disable Previous on page 1, disable Next on the last page, and update `aria-live` range text.

- [ ] **Step 4: Add event-bound pagination methods**

Bind `#staff-report-previous` and `#staff-report-next` click handlers to `goToPage(this.currentPage - 1)` and `goToPage(this.currentPage + 1)`. Bind `#download-report-pdf` to `downloadPdf()`.

Implement `goToPage(page)` with clamping between 1 and `pageCount()`, then call `renderPage()` without refetching. Add `pageCount()` returning `Math.max(1, Math.ceil(this.report.data.length / this.pageSize))`.

- [ ] **Step 5: Add direct all-row PDF generation**

Implement `downloadPdf()` with these exact behaviors:

```js
const JsPdf = this.window?.jspdf?.jsPDF;
if (typeof JsPdf !== 'function' || typeof JsPdf.prototype.autoTable !== 'function') {
  this.text('staff-report-status', 'PDF export is unavailable.');
  return;
}
```

Create a landscape A4 document, use `this.report.headers` and `this.report.data` as the AutoTable head/body, add the report label and period, and call `doc.save()` with a sanitized filename such as `scan2borrow-overdue-2026-01-01-to-2026-08-29.pdf`. Set a temporary loading status while generating, restore the button state in `finally`, and surface generation failures through `staff-report-status`.

- [ ] **Step 6: Run the focused tests to verify the implementation turns them green**

Run:

```powershell
node --test frontend/tests/staff-utils.test.js
```

Expected: all staff utility tests pass, including the new pagination and all-row PDF assertions.

### Task 3: Update Reports markup and presentation

**Files:**
- Modify: `frontend/features/staff/pages/reports/reports.html`
- Modify: `frontend/assets/css/staff-reports.css`

**Interfaces:**
- Consumes: The IDs and state transitions produced by `ReportsPage`.
- Produces: Direct PDF button, accessible pagination footer, pinned browser PDF assets, and print-hidden pagination.

- [ ] **Step 1: Replace the new-tab print link with a direct PDF button**

Replace the `#generate-report-link` anchor with:

```html
<button id="download-report-pdf" type="button" class="btn btn-gradient" disabled>
  Download PDF
</button>
```

Keep the CSV export link and its active-filter URL behavior.

- [ ] **Step 2: Add pinned PDF library scripts**

Load these deferred scripts before the Reports entry module:

```html
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.4/dist/jspdf.plugin.autotable.min.js" defer></script>
```

Keep the existing Bootstrap, confirmation, navbar, media, entry, and icon assets intact.

- [ ] **Step 3: Add the pagination footer markup**

Place this below the report table:

```html
<div id="staff-report-pagination" class="report-pagination" aria-label="Report pagination">
  <span id="staff-report-range" aria-live="polite">0–0 of 0</span>
  <div class="report-pagination__controls">
    <button id="staff-report-previous" type="button" class="btn btn-outline-secondary" aria-label="Previous report page" disabled>Previous</button>
    <button id="staff-report-next" type="button" class="btn btn-outline-secondary" aria-label="Next report page" disabled>Next</button>
  </div>
</div>
```

- [ ] **Step 4: Style the controls and hide them for print**

Add scoped rules to `staff-reports.css` for a separated footer, readable range text, aligned controls, narrow-screen stacking, and:

```css
@media print {
  .report-pagination { display: none !important; }
}
```

- [ ] **Step 5: Add markup assertions before the full gate**

Extend `frontend/tests/staff-utils.test.js` to assert the Reports template contains `download-report-pdf`, both pagination control IDs, `aria-live="polite"`, and both pinned PDF asset URLs.

Run:

```powershell
node --test frontend/tests/staff-utils.test.js
```

Expected: focused staff utility tests pass.

### Task 4: Verify the complete frontend gate and inspect the final change

**Files:**
- Verify: `frontend/features/staff/pages/reports/reports.page.js`
- Verify: `frontend/features/staff/pages/reports/reports.html`
- Verify: `frontend/assets/css/staff-reports.css`
- Verify: `frontend/tests/staff-utils.test.js`

**Interfaces:**
- Consumes: Completed implementation and regression tests.
- Produces: Verified working tree ready for user review/commit.

- [ ] **Step 1: Run the complete frontend CI-equivalent test command**

Run:

```powershell
npm.cmd test
```

Expected: zero failures and all frontend tests passing in under 110 seconds.

- [ ] **Step 2: Check the patch for whitespace errors and scope**

Run:

```powershell
git diff --check
git diff --stat
git status --short
```

Confirm only the Reports page, its stylesheet, the focused frontend test, and any implementation-plan/spec files are changed; do not stage unrelated user files.

- [ ] **Step 3: Perform the PDF-specific verification**

Use the PDF skill’s render/inspection workflow if a generated sample PDF is produced. Confirm the sample contains the report title, headers, and rows beyond the first 10, and confirm the screen pagination remains absent from the PDF.

- [ ] **Step 4: Report the exact verification state**

Report the passing test count, changed files, whether the spec/plan commits were blocked by the environment’s `.git` write restriction, and whether the implementation was committed or pushed.
