# All-Category Dashboard Series Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the admin dashboard's category borrowing trend include every active inventory category, with twelve zero values for categories that have no borrowing activity.

**Architecture:** Keep `PdoStaffRepository::dashboardOverview()` as the single source of the dashboard aggregate. Initialize its category map from the existing `reportCategories()` inventory query, then overlay the existing schema-specific borrowing aggregation. The frontend already renders arbitrary category series from the existing response shape, so no frontend contract or markup changes are required.

**Tech Stack:** PHP 8.2+, PDO with SQLite/MySQL-compatible SQL, PHPUnit 11, PHPStan, Node.js native test runner.

## Global Constraints

- Preserve the existing `category_borrowing_activity` response shape and twelve-month ordering.
- Include categories from active inventory only; keep blank category names normalized to `Uncategorized`.
- Preserve legacy `books` and normalized `book_titles`/`book_copies` compatibility.
- Keep all existing test coverage and do not use focused or skipped tests.
- Preserve unrelated user changes already present in the working tree.
- Run the focused test first, then `composer run quality` in `backend`, then `npm test` from the repository root.

---

### Task 1: Add failing regression coverage for zero-activity inventory categories

**Files:**
- Modify: `backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php:66-145`

**Interfaces:**
- Consumes: `PdoStaffRepository::dashboard()` and the existing legacy SQLite fixture.
- Produces: assertions proving that dashboard category series are inventory-driven rather than borrowing-driven.

- [ ] **Step 1: Write the failing legacy-schema test**

Add this test after `testDashboardIncludesOverviewAggregatesAndTopBorrowers()`:

```php
public function testDashboardCategoryTrendIncludesInventoryCategoriesWithoutBorrowing(): void
{
    $this->pdo->exec("INSERT INTO books (id, barcode, title, author, category_name, status, floor_no, section_name, shelf_no, created_at) VALUES (2, 'BK-2', 'Mathematics', 'Euler', 'Mathematics', 'Available', '2', 'Math', 'B1', '2026-08-01')");

    $series = (new PdoStaffRepository($this->pdo))->dashboard()['overview']['category_borrowing_activity']['series'];

    self::assertCount(2, $series);
    self::assertSame('Computer Science', $series[0]['name']);
    self::assertSame('Mathematics', $series[1]['name']);
    self::assertCount(12, $series[1]['counts']);
    self::assertSame(0, array_sum($series[1]['counts']));
}
```

Also extend `testDashboardOverviewHandlesEmptyBorrowingData()` after the existing loan-status assertions:

```php
self::assertSame([
    [
        'name' => 'Computer Science',
        'counts' => array_fill(0, 12, 0),
    ],
], $overview['category_borrowing_activity']['series']);
```

- [ ] **Step 2: Run the focused tests and verify they fail for the missing behavior**

Run from the repository root:

```powershell
php backend/vendor/bin/phpunit --configuration=backend/phpunit.xml backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php --filter "Dashboard(CategoryTrendIncludesInventoryCategoriesWithoutBorrowing|OverviewHandlesEmptyBorrowingData)"
```

Expected result: the new test fails because `Mathematics` is absent, and the empty-borrowing assertion fails because the current series is empty. Do not change production code until this failure is observed.

- [ ] **Step 3: Commit the failing test changes**

```powershell
git add -- backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php
git commit -m "test: require all inventory categories in dashboard trend"
```

### Task 2: Seed dashboard category series from active inventory

**Files:**
- Modify: `backend/src/Infrastructure/Persistence/PdoStaffRepository.php:699-735`

**Interfaces:**
- Consumes: `reportCategories(): list<string>`, the existing twelve-month `$months` map, and schema-specific category activity rows.
- Produces: `overview.category_borrowing_activity.series`, with one `{name: string, counts: list<int>}` entry per active inventory category.

- [ ] **Step 1: Initialize every active category before reading borrowing rows**

Immediately before the existing `$categoryActivityStatement = $this->pdo->prepare(...)` assignment, initialize the map with:

```php
/** @var array<string, array<string, int>> $categoryCounts */
$categoryCounts = [];
foreach ($this->reportCategories() as $category) {
    $categoryCounts[$category] = [];
}
```

Keep the existing borrowing query and row loop unchanged so it continues to populate `$categoryCounts[$category][$monthKey]` for categories that have activity.

- [ ] **Step 2: Run the focused tests and verify the implementation passes**

Run:

```powershell
php backend/vendor/bin/phpunit --configuration=backend/phpunit.xml backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php --filter "Dashboard(CategoryTrendIncludesInventoryCategoriesWithoutBorrowing|OverviewIncludesAggregatesAndTopBorrowers|OverviewHandlesEmptyBorrowingData|NormalizedDashboardUsesCopyQuantitiesAcrossStaffMetrics)"
```

Expected result: all selected tests pass, including the existing aggregate and normalized-schema coverage. The zero-activity category is sorted after the borrowed category because the existing total-descending/name-ascending sort remains in place.

- [ ] **Step 3: Run the complete backend unit file**

Run:

```powershell
php backend/vendor/bin/phpunit --configuration=backend/phpunit.xml backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php
```

Expected result: the complete `PdoStaffRepositoryTest` file passes without warnings or risky-test output.

- [ ] **Step 4: Commit the production implementation**

```powershell
git add -- backend/src/Infrastructure/Persistence/PdoStaffRepository.php
git commit -m "fix: show all inventory categories in dashboard trend"
```

### Task 3: Run the repository quality gates

**Files:**
- No source changes expected.

**Interfaces:**
- Consumes: the committed test and repository changes from Tasks 1 and 2.
- Produces: fresh local verification for the documented CI jobs and frontend regression suite.

- [ ] **Step 1: Run the backend CI-equivalent quality gate**

Run from the repository root:

```powershell
Push-Location backend
composer validate --no-check-publish
composer install --prefer-dist --no-interaction --no-progress
composer run quality
Pop-Location
```

Expected result: Composer validation succeeds, dependencies remain locked, PHPUnit passes, and PHPStan reports no errors.

- [ ] **Step 2: Run the frontend test suite**

Run:

```powershell
npm test
```

Expected result: all frontend tests pass. No frontend file should need modification because the chart already consumes the existing series structure.

- [ ] **Step 3: Inspect the final diff and status**

Run:

```powershell
git diff HEAD~2..HEAD --stat
git status --short
```

Expected result: the two task commits contain only the scoped backend test and implementation changes; unrelated pre-existing modifications and untracked user files remain untouched.

