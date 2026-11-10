# Reports Category Filter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a database-backed book-category filter to the staff Reports page and apply the selected category consistently to JSON results, CSV export, and browser PDF export.

**Architecture:** Extend the existing staff report repository contract with a category argument and a category-options query. The repository will compose one parameterized category predicate with the current report-type/date predicates for both normalized and legacy schemas. The controller will expose category options in the JSON response and forward the category to all report/export calls; the existing ReportsPage and StaffReportService will preserve it in the URL and export links.

**Tech Stack:** PHP 8.3+, PDO/SQLite test fixtures, PHPUnit, PSR-7-style request/response classes, vanilla ES modules, Node built-in test runner, jsPDF/AutoTable already loaded by the Reports page.

## Global Constraints

- Use the book `category_name` field, not borrower course/program values.
- Empty category means `All Categories` and must preserve current unfiltered behavior.
- Category filtering must be server-side before rendering or export generation.
- Match trimmed category names exactly; invalid categories return zero records rather than all records.
- Preserve existing report types, date filters, pagination, CSV export, browser PDF export, and staff authorization.
- Do not add an Excel dependency or change unrelated dashboard, borrower, recommendation, or activity behavior.
- Treat the repository's existing full-suite failures as baseline; do not weaken or skip those tests.
- Keep unrelated working-tree edits unstaged and untouched.

---

### Task 1: Add the category-aware staff report repository contract and queries

**Files:**
- Modify: `backend/src/Infrastructure/Persistence/StaffRepositoryInterface.php`
- Modify: `backend/src/Infrastructure/Persistence/PdoStaffRepository.php:215-390`
- Modify: `backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php`
- Modify: `backend/tests/Feature/StaffDashboardContractTest.php`

**Interfaces:**
- `StaffRepositoryInterface::report(string $type, string $from, string $to, string $category): array` accepts the selected book category.
- `StaffRepositoryInterface::reportCategories(): array` returns `list<string>` category labels used by the filter.
- `PdoStaffRepository::reportCategories(): array` returns sorted, deduplicated labels from active normalized copies or active legacy books. Blank categories are returned as `Uncategorized`.

- [ ] **Step 1: Write the failing repository tests**

Add tests to `PdoStaffRepositoryTest` using the existing legacy SQLite fixture:

```php
public function testReportCategoriesAndLegacyReportsRespectCategoryAndDateFilters(): void
{
    $this->pdo->exec("INSERT INTO books (id, barcode, title, author, category_name, status, deleted_at, floor_no, section_name, shelf_no, created_at) VALUES
        (2, 'BK-2', 'English Grammar', 'Author Two', 'English', 'Available', NULL, '1', 'ENG', 'A2', '2026-08-01'),
        (3, 'BK-3', 'Math Basics', 'Author Three', 'Mathematics', 'Available', NULL, '1', 'MATH', 'A3', '2026-08-01')");
    $this->pdo->exec("INSERT INTO borrowing (id, transaction_code, user_id, book_id, approval_status, borrow_date, due_date, return_date, status, fine_amount) VALUES
        (2, 'TX-2', 2, 2, 'approved', '2026-08-21', '2026-08-28', NULL, 'Borrowed', 0),
        (3, 'TX-3', 2, 3, 'approved', '2026-09-01', '2026-09-08', NULL, 'Borrowed', 0)");

    self::assertSame(['Computer Science', 'English', 'Mathematics'], (new PdoStaffRepository($this->pdo))->reportCategories());
    $report = (new PdoStaffRepository($this->pdo))->report('borrowed', '2026-08-01', '2026-08-31', 'English');
    self::assertSame(['TX-2'], array_column($report['data'], 0));
    self::assertSame([], (new PdoStaffRepository($this->pdo))->report('borrowed', '', '', 'Does Not Exist')['data']);
    self::assertCount(3, (new PdoStaffRepository($this->pdo))->report('borrowed', '', '', '')['data']);
}
```

Add a normalized-schema test fixture with `book_titles`, `book_copies`, `borrowing_transactions`, and `borrowing_items`, then assert `reportCategories()` reads `book_titles.category_name`, inventory filtered by `Computer Science` excludes an `English` title, and an invalid category returns no rows. Use the same exact category predicate behavior for `borrowed`, `returned`, and `overdue` reports.

Update the anonymous `StaffRepositoryInterface` implementation in `StaffDashboardContractTest` with the new fourth `report` parameter and `reportCategories(): array { return []; }` so the contract test remains a valid unrelated repository double.

- [ ] **Step 2: Run the focused tests and verify the expected red failure**

Run:

```powershell
php vendor/bin/phpunit tests/Unit/Infrastructure/PdoStaffRepositoryTest.php tests/Feature/StaffDashboardContractTest.php
```

Expected: failure because the interface and repository do not yet expose the new category argument or category-options method, and the new assertions cannot pass.

- [ ] **Step 3: Implement the minimal repository contract and category predicates**

Change the interface signature and add `reportCategories()`. In `PdoStaffRepository`, add a `categoryPredicate(string $column, string $category, array &$parameters): string` helper that returns an empty string for `''`, otherwise appends `COALESCE(NULLIF(TRIM(<column>), ''), 'Uncategorized') = :category` and binds the trimmed category. Use a unique parameter key for each query because the report builds one statement per call.

Apply the returned predicate to:

- Normalized inventory: `t.category_name`, alongside `c.deleted_at IS NULL`.
- Legacy inventory: `b.category_name`, alongside `b.deleted_at IS NULL`.
- Normalized borrowing reports: `t.category_name`, alongside the existing returned/overdue/date predicates.
- Legacy borrowing reports: `b.category_name`, alongside the existing returned/overdue/date predicates.

Build `reportCategories()` from active report sources:

```sql
SELECT DISTINCT COALESCE(NULLIF(TRIM(t.category_name), ''), 'Uncategorized') AS category
FROM book_titles t
WHERE EXISTS (SELECT 1 FROM book_copies c WHERE c.title_id = t.id AND c.deleted_at IS NULL)
ORDER BY category ASC
```

Use the legacy equivalent against `books WHERE deleted_at IS NULL`, return `[]` when the source query is unavailable, and normalize the fetched values into `list<string>`.

- [ ] **Step 4: Run the repository tests and verify green**

Run the same focused PHPUnit command. Expected: all tests in the two files pass, including the new category, invalid-category, all-category, normalized-schema, and date-composition assertions.

- [ ] **Step 5: Commit the repository slice**

```powershell
git add backend/src/Infrastructure/Persistence/StaffRepositoryInterface.php backend/src/Infrastructure/Persistence/PdoStaffRepository.php backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php backend/tests/Feature/StaffDashboardContractTest.php
git commit -m "feat: filter staff reports by category"
```

---

### Task 2: Expose category options and filtered data through the staff controller

**Files:**
- Modify: `backend/src/Http/Controllers/StaffController.php:173-220`
- Create: `backend/tests/Feature/StaffReportControllerTest.php`

**Interfaces:**
- `StaffController::report()` returns `data.report` plus `data.categories`.
- `StaffController::exportReport()` forwards the `category` query parameter to `StaffRepositoryInterface::report()` and serializes only that returned data.

- [ ] **Step 1: Write failing controller tests**

Create a focused controller test with a staff session and a small `StaffRepositoryInterface` test double that records report arguments and returns a report with one row plus `reportCategories()` returning `['Computer Science', 'English']`. Assert:

```php
public function testReportForwardsCategoryAndReturnsCategoryOptions(): void
{
    $response = $this->controller($repository)->report($this->request('GET', '/api/staff/reports?type=borrowed&from=2026-08-01&to=2026-08-31&category=Computer%20Science'));

    self::assertSame(['borrowed', '2026-08-01', '2026-08-31', 'Computer Science'], $repository->reportArguments);
    self::assertSame(['Computer Science', 'English'], json_decode($response->toString(), true)['data']['categories']);
}
```

Add an export test that calls `/api/staff/reports/export?...&category=English`, asserts the repository receives `English`, and asserts the CSV body contains the selected row and the `text/csv` attachment header. Add an unauthenticated test that continues to return 401.

- [ ] **Step 2: Run the controller tests and verify red**

Run:

```powershell
php vendor/bin/phpunit tests/Feature/StaffReportControllerTest.php tests/Feature/StaffDashboardContractTest.php
```

Expected: failure because the controller does not pass `category`, does not return `categories`, and the repository test double is missing the new contract method until updated.

- [ ] **Step 3: Implement controller forwarding**

Read `category` with `queryString($request, 'category')`. Pass it as the fourth argument to the repository in both `report()` and `exportReport()`. Include `categories => $this->staff->reportCategories()` in the JSON `data` payload. Leave CSV formatting, staff authorization, and response headers unchanged.

- [ ] **Step 4: Run focused controller and contract tests**

Run the commands from Step 2. Expected: all tests pass, including existing dashboard contract coverage.

- [ ] **Step 5: Commit the controller slice**

```powershell
git add backend/src/Http/Controllers/StaffController.php backend/tests/Feature/StaffReportControllerTest.php backend/tests/Feature/StaffDashboardContractTest.php
git commit -m "feat: expose report category options"
```

---

### Task 3: Preserve category through the report service, page, and exports

**Files:**
- Modify: `frontend/features/staff/services/report.service.js`
- Modify: `frontend/features/staff/pages/reports/reports.html`
- Modify: `frontend/features/staff/pages/reports/reports.page.js`
- Modify: `frontend/tests/staff-utils.test.js`
- Modify: `backend/tests/Feature/StaffReportsContractTest.php`

**Interfaces:**
- `StaffReportService.load({ type, from, to, category })` sends all four filters.
- `StaffReportService.exportUrl({ type, from, to, category })` and `printUrl(...)` preserve category when non-empty.
- `ReportsPage.load()` reads `category` from the URL, renders backend `data.categories`, and passes the selected value to service/export methods.

- [ ] **Step 1: Write failing frontend tests**

Extend the existing report service test to assert:

```js
await reports.load({ type: 'overdue', from: '2026-01-01', to: '2026-08-29', category: 'English' });
assert.equal(
  reports.exportUrl({ type: 'overdue', from: '2026-01-01', category: 'English' }),
  '/scan2borrow/api/staff/reports/export?type=overdue&from=2026-01-01&category=English',
);
assert.equal(
  reports.printUrl({ type: 'overdue', category: 'English' }),
  '/scan2borrow/staff/reports?type=overdue&print=1&category=English',
);
assert.deepEqual(calls[0], {
  method: 'GET',
  path: '/scan2borrow/api/staff/reports',
  params: { type: 'overdue', from: '2026-01-01', to: '2026-08-29', category: 'English' },
});
```

Extend `reportsFixture()` with `select[name="category"]`, return `data.categories`, and add a page test for `?type=borrowed&category=Computer%20Science` that asserts the category option is selected, the API receives it, the export/print links include it, and the report metadata reads `Computer Science`.

- [ ] **Step 2: Run the focused frontend tests and verify red**

Run:

```powershell
node --test frontend/tests/staff-utils.test.js
```

Expected: failure because the service drops the category, the page has no category select/metadata, and the response category list is not rendered.

- [ ] **Step 3: Implement service and page behavior**

Update `StaffReportService` methods to accept `category = ''`, include it in API params, and add it to export/print `URLSearchParams` only when non-empty.

Reshape the existing filter row so the five controls fit the Bootstrap grid: report type `col-md-3`, category `col-md-3`, from `col-md-2`, to `col-md-2`, and the filter button `col-md-2`. Add the category control to `reports.html`:

```html
<div class="col-md-4">
  <label class="form-label" for="staff-report-category-filter">Category</label>
  <select id="staff-report-category-filter" name="category" class="form-select" onchange="this.form.submit()">
    <option value="">All Categories</option>
  </select>
</div>
```

Add `staff-report-category` to the report metadata. In `ReportsPage.load()`, read `category` from `URLSearchParams`, send it to `service.load`, render category options from `response.data.categories`, restore the selected value, set the metadata text to the selected category or `All Categories`, and pass category to `exportUrl` and `printUrl`. Keep the current pagination reset, error state, DOM `textContent` rendering, and PDF data source. Include the category label in the PDF heading/metadata and add it to `pdfFilename()` so filtered downloads are distinguishable.

- [ ] **Step 4: Run focused frontend tests and the full frontend lane**

Run:

```powershell
node --test frontend/tests/staff-utils.test.js
npm test
```

Expected: the focused report tests and the full existing frontend suite pass.

- [ ] **Step 5: Update the static report contract tests and run them**

Add `category` markers to `StaffReportsContractTest` for the select and service contract, then run:

```powershell
php vendor/bin/phpunit tests/Feature/StaffReportsContractTest.php
```

Expected: all report markup and service contract tests pass.

- [ ] **Step 6: Commit the frontend slice**

```powershell
git add frontend/features/staff/services/report.service.js frontend/features/staff/pages/reports/reports.html frontend/features/staff/pages/reports/reports.page.js frontend/tests/staff-utils.test.js backend/tests/Feature/StaffReportsContractTest.php
git commit -m "feat: add category filter to reports page"
```

---

### Task 4: Run the complete affected verification and finalize

**Files:**
- Modify: none unless a verification failure identifies a feature regression.

- [ ] **Step 1: Run focused backend verification**

```powershell
php vendor/bin/phpunit tests/Unit/Infrastructure/PdoStaffRepositoryTest.php tests/Feature/StaffDashboardContractTest.php tests/Feature/StaffReportControllerTest.php tests/Feature/StaffReportsContractTest.php
```

Expected: all affected tests pass.

- [ ] **Step 2: Run frontend verification**

```powershell
npm test
```

Expected: all frontend tests pass.

- [ ] **Step 3: Run repository quality commands without changing their gates**

```powershell
composer run quality
git diff --check HEAD~3..HEAD
```

Expected: report any existing baseline PHPUnit/PHPStan failures exactly; do not skip, weaken, or relabel them. `git diff --check` must be clean.

- [ ] **Step 4: Confirm only intended files are committed**

```powershell
git status --short
git log --oneline --max-count=5
```

Expected: unrelated pre-existing modifications and untracked files remain untouched, and the reports feature commits are on `master`.

- [ ] **Step 5: Push the inline master changes**

```powershell
git push origin master
```

Report the pushed commit SHA and the local/remote CI state. Do not claim the full gate is green if the repository baseline remains failing.
