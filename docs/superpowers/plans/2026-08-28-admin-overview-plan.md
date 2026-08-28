# Admin Dashboard Overview Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add real borrowing activity, current loan-status indicators, and a Top 10 Borrowers ranking to the authenticated `/staff/dashboard` Overview only.

**Architecture:** Preserve `GET /api/staff/dashboard` and extend its existing response with an `overview` object. `PdoStaffRepository` owns parameterized aggregate queries and twelve-month normalization. `StaffPageController` renders the new dashboard-only containers with vanilla DOM/CSS. Existing KPIs, recent transactions, approvals, polling, SQL schema, and every non-dashboard page remain unchanged.

**Tech Stack:** PHP 8.2+, PDO/MySQL with SQLite tests, PHPUnit 11, PHPStan level 9, vanilla JavaScript, HTML, CSS, and existing Bootstrap-compatible templates.

## Global Constraints

- Improve only the authenticated `/staff/dashboard` Overview surface.
- Existing staff pages, admin staff management, reports, navigation, authentication, SQL schema, and approval workflows remain behaviorally unchanged.
- Use native HTML/CSS and vanilla JavaScript only; add no chart library or fabricated data.
- Keep existing `stats`, `recent`, and `pending` response fields intact.
- Keep PHP strictly typed, OOP, PSR-12, parameterized, and dependency-injected.
- Escape every database-backed value rendered into HTML.
- Run PHPUnit, PHPStan level 9, and `node --check` before completion.

---

### Task 1: Define backend and API contracts with failing tests

**Files:**
- Modify: `backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php`
- Create: `backend/tests/Feature/StaffDashboardContractTest.php`

**Interfaces:**
- Consumes: `PdoStaffRepository::dashboard()` and `StaffController::dashboard(ServerRequest)`.
- Produces: the expected `overview` shape without removing legacy dashboard keys.

- [ ] **Step 1: Write repository tests first.** Extend the existing SQLite fixture with a second student and borrowing rows where Grace has three records and another borrower has one. Assert that `dashboard()['overview']` has 12 buckets, each bucket has `month`, `label`, and `count`, `loan_status` has `available`, `borrowed`, `overdue`, and `pending`, and Grace is the first Top 10 row with count 3. Add an empty fixture assertion for twelve zero buckets and an empty ranking.

- [ ] **Step 2: Run the tests and verify the expected red failure.**

```powershell
& 'C:\xampp\php\php.exe' backend/vendor/bin/phpunit -c backend/phpunit.xml backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php --filter Dashboard
```

Expected: failure because `overview` is not present.

- [ ] **Step 3: Write the API contract test.** With the existing session/repository doubles, assert that an admin or librarian receives HTTP 200 with `ok`, `stats`, `recent`, `pending`, and `overview`; assert that no identity receives HTTP 401 with the existing staff-authentication error.

- [ ] **Step 4: Run and commit the red contract tests.**

```powershell
& 'C:\xampp\php\php.exe' backend/vendor/bin/phpunit -c backend/phpunit.xml backend/tests/Feature/StaffDashboardContractTest.php
git add backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php backend/tests/Feature/StaffDashboardContractTest.php
git commit -m "test: define admin overview dashboard contract"
```

### Task 2: Add repository-owned overview aggregates

**Files:**
- Modify: `backend/src/Infrastructure/Persistence/PdoStaffRepository.php`
- Modify: `backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php`

**Interfaces:**
- Consumes: existing PDO connection and `books`, `users`, and `borrowing` tables.
- Produces `overview` with `borrowing_activity: list<array{month:string,label:string,count:int}>`, `loan_status: array{available:int,borrowed:int,overdue:int,pending:int}`, and `top_borrowers: list<array{id:int,name:string,barcode:string,borrowing_count:int}>`.

- [ ] **Step 1: Add private `dashboardOverview(): array`.** Use `new DateTimeImmutable('first day of this month')`, walk back eleven months, initialize oldest-to-newest `Y-m` buckets, query `borrow_date` at or after the first bucket with bound `:start_date`, and increment valid dates in PHP.

- [ ] **Step 2: Add the four status counts through the existing `count()` helper.** Use Available and Borrowed non-deleted books, active Overdue borrowing rows, and active pending approval rows. Keep these meanings explicit in the returned keys.

- [ ] **Step 3: Add the portable Top 10 query.** Join `users` to `borrowing`, restrict roles to student/teacher, group by id/barcode/name, order by `COUNT(br.id) DESC` then last/first name, and `LIMIT 10`. Normalize ids/counts to integers and name/barcode to strings.

- [ ] **Step 4: Add only `'overview' => $this->dashboardOverview(),` to the existing dashboard return array.** Do not rename, remove, or recalculate legacy fields.

- [ ] **Step 5: Run green and commit.**

```powershell
& 'C:\xampp\php\php.exe' backend/vendor/bin/phpunit -c backend/phpunit.xml backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php
git add backend/src/Infrastructure/Persistence/PdoStaffRepository.php backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php
git commit -m "feat: add admin overview aggregates"
```

### Task 3: Add Overview-only markup and Swiss styling

**Files:**
- Modify: `frontend/pages/staff-dashboard.html`
- Create: `frontend/assets/css/admin-overview.css`
- Create: `backend/tests/Feature/StaffDashboardMarkupTest.php`

- [ ] **Step 1: Write the failing markup contract.** Assert the dashboard contains `data-overview`, `overview-activity`, `overview-status`, `overview-status-ring`, `overview-status-legend`, `overview-borrowers-list`, and `admin-overview.css`; assert staff books, reports, and admin-staff pages do not contain `data-overview`.

- [ ] **Step 2: Run it red.**

```powershell
& 'C:\xampp\php\php.exe' backend/vendor/bin/phpunit -c backend/phpunit.xml backend/tests/Feature/StaffDashboardMarkupTest.php
```

Expected: failure because the hooks do not exist.

- [ ] **Step 3: Add only to `staff-dashboard.html` a semantic Overview section with an activity host, status ring/legend host, and Top 10 ordered-list host.** Use the exact ids `overview-activity`, `overview-status-ring`, `overview-status-legend`, and `overview-borrowers-list`.

- [ ] **Step 4: Create `admin-overview.css` using the approved Swiss tokens: `#F7F7F8`/white surfaces, `#002FA7` blue, `#D9DDE5` hairline rules, Inter/Helvetica-compatible sans typography, tabular numerals, left alignment, and an offset ranking rail. Use only the data-driven status `conic-gradient`; include responsive collapse, focus-visible states, and reduced-motion rules.

- [ ] **Step 5: Include `<link href="/scan2borrow/frontend/assets/css/admin-overview.css" rel="stylesheet" />` in `staff-dashboard.html` only. Run the markup test green and commit.**

```powershell
& 'C:\xampp\php\php.exe' backend/vendor/bin/phpunit -c backend/phpunit.xml backend/tests/Feature/StaffDashboardMarkupTest.php
git add frontend/pages/staff-dashboard.html frontend/assets/css/admin-overview.css backend/tests/Feature/StaffDashboardMarkupTest.php
git commit -m "feat: add admin overview layout"
```

### Task 4: Render the Overview with vanilla JavaScript

**Files:**
- Modify: `frontend/assets/js/pages/staff.js`
- Create: `backend/tests/Feature/StaffDashboardFrontendContractTest.php`

- [ ] **Step 1: Write the failing frontend contract.** Assert `staff.js` contains `renderOverview`, `renderActivity`, `renderStatus`, `renderTopBorrowers`, all three overview keys, and `this.renderOverview(data.overview || {})`; assert no chart-library import/CDN exists.

- [ ] **Step 2: Run it red.**

```powershell
& 'C:\xampp\php\php.exe' backend/vendor/bin/phpunit -c backend/phpunit.xml backend/tests/Feature/StaffDashboardFrontendContractTest.php
```

Expected: failure because the renderer methods do not exist.

- [ ] **Step 3: Wire `dashboard()` with `this.renderOverview(data.overview || {});`.** Implement `renderActivity()` with one accessible DOM bar per bucket, proportional heights, total count, and `No borrowing activity recorded.` for zero data. Implement `renderStatus()` with nonnegative integer values in the order available/borrowed/overdue/pending, a data-driven conic gradient, escaped legend rows, and `No current status data.` for zero. Implement `renderTopBorrowers()` with at most ten escaped ordered rows and `No borrowing records yet.` for empty data. Default malformed arrays/objects so partial payloads cannot break existing tables.

- [ ] **Step 4: Run green, syntax-check, and commit.**

```powershell
& 'C:\xampp\php\php.exe' backend/vendor/bin/phpunit -c backend/phpunit.xml backend/tests/Feature/StaffDashboardFrontendContractTest.php
& node --check frontend/assets/js/pages/staff.js
git add frontend/assets/js/pages/staff.js backend/tests/Feature/StaffDashboardFrontendContractTest.php
git commit -m "feat: render admin overview analytics"
```

### Task 5: Complete verification and Apache smoke test

**Files:** Inspect completed changes, local Apache dashboard/API, and git status.

- [ ] **Step 1: Run the complete test suite.**

```powershell
& 'C:\xampp\php\php.exe' backend/vendor/bin/phpunit -c backend/phpunit.xml backend/tests
& 'C:\xampp\php\php.exe' backend/vendor/bin/phpstan analyse -c backend/phpstan.neon --level=9 --no-progress backend/src backend/tests
```

Expected: all tests pass and PHPStan reports `[OK] No errors`.

- [ ] **Step 2: Check all JavaScript.**

```powershell
$failed = @(); Get-ChildItem frontend/assets/js -Recurse -Filter *.js | ForEach-Object { & node --check $_.FullName; if ($LASTEXITCODE -ne 0) { $failed += $_.FullName } }; if ($failed.Count) { $failed; exit 1 }; 'All JavaScript files passed node --check.'
```

- [ ] **Step 3: Smoke-test Apache with the existing authenticated admin session.** Confirm `/scan2borrow/staff/dashboard` is HTML 200, `/scan2borrow/api/staff/dashboard` includes `overview`, `borrowing_activity`, `loan_status`, and `top_borrowers`, and unauthenticated access retains existing authorization behavior.

- [ ] **Step 4: Confirm scope and clean worktree.**

```powershell
git status --short
git diff HEAD~4 --name-only
```

Expected: no uncommitted files and no non-Overview page changes.
