# Top 5 Course Borrowers and Course Catalog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a database-backed course catalog with administrator course creation and a live Top 5 Course Borrowers ranking on the Scan2Borrow staff dashboard.

**Architecture:** Add a `courses` catalog without replacing the existing `users.course` text field. A typed course service and PDO repository will serve public registration reads and administrator writes, while `PdoStaffRepository` will compute distinct student borrower counts directly from approved borrowing records. The existing administrator page will gain a separate College Settings tab; the existing dashboard API and frontend model will gain `top_course_borrowers`.

**Tech Stack:** PHP 8.2+ / PDO / PHPUnit 11 / PHPStan 2, MySQL-compatible SQL, vanilla ES modules, Bootstrap 5, Node’s built-in test runner.

## Global Constraints

- Use `declare(strict_types=1)` and typed properties, parameters, and return values in new PHP files.
- Preserve the existing `users.course` column and all existing borrowing records.
- Count distinct student borrowers once per course from approved transactions with a non-null borrow date.
- Manage courses only through an administrator-only College Settings tab and CSRF-protected endpoints.
- Do not add course deletion behavior.
- Escape dynamic course names and numbers before frontend insertion; do not fabricate counts or rows.
- Use prepared statements for all user-controlled database writes and reads.
- Keep existing unrelated worktree changes untouched.
- Run targeted tests after each task, then run `npm test`, the complete PHPUnit suite, and PHPStan before completion.

## File map

### Database

- Modify: `sql/install.sql` — create and seed the catalog on fresh installs.
- Modify: `sql/database.sql` — keep the alternate schema dump aligned.
- Create: `sql/upgrade_courses.sql` — idempotent migration for existing databases.
- Modify: `README.md` — add the migration to the documented upgrade order.

### Backend domain, persistence, and HTTP

- Create: `backend/src/Domain/Course/Course.php` — readonly course entity.
- Create: `backend/src/Application/DTO/CreateCourseRequest.php` — typed course-create input.
- Create: `backend/src/Application/Services/CourseService.php` — trim, validate, list, and create catalog entries.
- Create: `backend/src/Infrastructure/Persistence/CourseRepositoryInterface.php` — repository contract.
- Create: `backend/src/Infrastructure/Persistence/PdoCourseRepository.php` — prepared PDO implementation.
- Create: `backend/src/Http/Controllers/CourseController.php` — public catalog and admin course endpoints.
- Create: `backend/src/Http/Routing/CourseRouteTable.php` — `/api/courses` and `/api/admin/courses` routes.
- Modify: `backend/src/Bootstrap/ApplicationFactory.php` — construct and wire the course repository/service/controller and registration validation.
- Modify: `backend/src/Application/Services/RegistrationService.php` — reject unknown student courses.
- Modify: `backend/src/Infrastructure/Persistence/PdoStaffRepository.php` — calculate `top_course_borrowers`.

### Backend tests

- Create: `backend/tests/Unit/Course/CourseServiceTest.php` — service validation and repository interaction.
- Create: `backend/tests/Unit/Infrastructure/PdoCourseRepositoryTest.php` — SQLite catalog persistence.
- Create: `backend/tests/Feature/CourseControllerTest.php` — authorization, CSRF, JSON contracts, and duplicate handling.
- Modify: `backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php` — distinct course ranking and legacy fallback coverage.
- Modify: `backend/tests/Unit/Registration/RegistrationServiceTest.php` — valid and unknown-course registration behavior.
- Modify: `backend/tests/Feature/InstallSchemaContractTest.php` — catalog table and seed checks.

### Frontend

- Modify: `frontend/features/staff/pages/dashboard/dashboard.html` — Top 5 Course Borrowers markup and stylesheet hook.
- Modify: `frontend/features/staff/pages/dashboard/staff-dashboard.page.js` — normalize/render ranking and refresh it after dashboard loads.
- Modify: `frontend/features/staff/models/dashboard.model.js` — normalize `top_course_borrowers`.
- Modify: `frontend/assets/css/admin-overview.css` — Swiss-ranked course panel and responsive layout.
- Modify: `frontend/features/staff/pages/admin-staff/admin-staff.html` — Staff Accounts / College Settings tabs and course form/list.
- Modify: `frontend/features/staff/pages/admin-staff/admin-staff.page.js` — load, render, and create courses.
- Modify: `frontend/features/staff/services/admin-staff.service.js` — course list/create API methods.
- Modify: `frontend/features/auth/pages/register/register.html` — replace hard-coded course options with a catalog hook.
- Modify: `frontend/features/auth/pages/register/register.page.js` — load public courses and render loading/error states.

### Frontend tests

- Modify: `frontend/tests/staff-pages.test.js` — College Settings and ranking hooks.
- Modify: `frontend/tests/staff-pages.test.js` — ranking model/render behavior.
- Modify: `frontend/tests/auth-pages.test.js` — registration catalog hook and no hard-coded course list.

---

### Task 1: Add the database course catalog

**Files:**
- Modify: `sql/install.sql`
- Modify: `sql/database.sql`
- Create: `sql/upgrade_courses.sql`
- Modify: `README.md`
- Test: `backend/tests/Feature/InstallSchemaContractTest.php`

**Interfaces:**
- Produces a MySQL table named `courses` with integer `id`, unique `name`, and `created_at`.
- Produces the seven seeded names required by the approved specification.
- Provides an idempotent `sql/upgrade_courses.sql` for existing databases.

- [ ] **Step 1: Add the failing schema contract assertions**

Extend the install schema contract to assert that `sql/install.sql` contains the `courses` table, the unique name key, and each of the seven exact course names. Add an assertion that the README upgrade sequence contains `upgrade_courses.sql`.

Run:

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend/phpunit.xml backend/tests/Feature/InstallSchemaContractTest.php
```

Expected: FAIL because the course table and seed names are absent.

- [ ] **Step 2: Add the fresh-install table and seed**

Insert the following table before the user table in both schema files, and seed the rows after table creation:

```sql
CREATE TABLE `courses` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(120) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_courses_name` (`name`)
) ENGINE=InnoDB;

INSERT INTO `courses` (`name`) VALUES
    ('BSIT'),
    ('BSEd Major in Science'),
    ('BSEd Major in English'),
    ('BEeD'),
    ('BSBA Major in Financial Management'),
    ('BSBA Major in Human Resources Management'),
    ('BSA');
```

Add `DROP TABLE IF EXISTS \\`courses\\`;` before `users` in `sql/install.sql` so fresh resets remain complete.

- [ ] **Step 3: Add the idempotent upgrade migration**

Create `sql/upgrade_courses.sql` with the same table definition and idempotent inserts:

```sql
CREATE TABLE IF NOT EXISTS `courses` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(120) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_courses_name` (`name`)
) ENGINE=InnoDB;

INSERT INTO `courses` (`name`)
SELECT `seed`.`name`
FROM (
    SELECT 'BSIT' AS `name`
    UNION ALL SELECT 'BSEd Major in Science'
    UNION ALL SELECT 'BSEd Major in English'
    UNION ALL SELECT 'BEeD'
    UNION ALL SELECT 'BSBA Major in Financial Management'
    UNION ALL SELECT 'BSBA Major in Human Resources Management'
    UNION ALL SELECT 'BSA'
) AS `seed`
WHERE NOT EXISTS (
    SELECT 1 FROM `courses` AS `existing` WHERE `existing`.`name` = `seed`.`name`
);
```

Document the migration after the existing schema upgrades in the README’s ordered list.

- [ ] **Step 4: Run the schema contract again**

Run the same PHPUnit command. Expected: PASS.

- [ ] **Step 5: Commit when repository metadata is writable**

```powershell
git add sql/install.sql sql/database.sql sql/upgrade_courses.sql README.md backend/tests/Feature/InstallSchemaContractTest.php
git commit -m "feat: add database course catalog"
```

The current workspace may reject this because `.git` is read-only; leave source changes intact and report the exact error if it occurs.

### Task 2: Implement the typed course domain and PDO repository

**Files:**
- Create: `backend/src/Domain/Course/Course.php`
- Create: `backend/src/Application/DTO/CreateCourseRequest.php`
- Create: `backend/src/Application/Services/CourseService.php`
- Create: `backend/src/Infrastructure/Persistence/CourseRepositoryInterface.php`
- Create: `backend/src/Infrastructure/Persistence/PdoCourseRepository.php`
- Create: `backend/tests/Unit/Course/CourseServiceTest.php`
- Create: `backend/tests/Unit/Infrastructure/PdoCourseRepositoryTest.php`

**Interfaces:**

```php
final readonly class Course
{
    public function __construct(
        public int $id,
        public string $name,
        public string $createdAt,
    ) {}
}

final readonly class CreateCourseRequest
{
    public function __construct(public string $name) {}
}

interface CourseRepositoryInterface
{
    /** @return list<Course> */
    public function all(): array;

    public function existsByName(string $name): bool;

    public function create(string $name): Course;
}

final class CourseService
{
    /** @return list<Course> */
    public function all(): array;

    public function create(CreateCourseRequest $request): Course;

    public function exists(string $name): bool;
}
```

- [ ] **Step 1: Write service tests for trimming and validation**

Use a mocked `CourseRepositoryInterface` to assert that `CourseService::create()` trims the submitted name, rejects an empty name, rejects names longer than 120 characters, rejects case-insensitive duplicates, and delegates valid names to `create()`.

Run:

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend/phpunit.xml backend/tests/Unit/Course/CourseServiceTest.php
```

Expected: FAIL because the classes do not exist.

- [ ] **Step 2: Implement the readonly entity, DTO, interface, and service**

Use `declare(strict_types=1)`, typed signatures, `trim()`, `mb_strlen()`, and `InvalidArgumentException` messages:

```php
if ($name === '') {
    throw new InvalidArgumentException('Course name is required.');
}
if (mb_strlen($name) > 120) {
    throw new InvalidArgumentException('Course name must be 120 characters or fewer.');
}
if ($this->courses->existsByName($name)) {
    throw new InvalidArgumentException('That course already exists.');
}
```

- [ ] **Step 3: Write SQLite repository tests**

Create an in-memory SQLite `courses` table with the same columns. Assert that `all()` returns typed courses in name order, `existsByName()` ignores surrounding whitespace and case, and `create()` persists and returns the inserted course.

- [ ] **Step 4: Implement `PdoCourseRepository`**

Use prepared statements for `existsByName()` and `create()`. Use `LOWER(TRIM(name)) = LOWER(TRIM(:name))` for duplicate checks and `ORDER BY name ASC` for reads. Convert database rows into `Course` objects and throw a typed `RuntimeException` if the insert cannot be read back.

- [ ] **Step 5: Run both unit test files**

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend/phpunit.xml backend/tests/Unit/Course backend/tests/Unit/Infrastructure/PdoCourseRepositoryTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit when repository metadata is writable**

```powershell
git add backend/src/Domain/Course backend/src/Application/DTO/CreateCourseRequest.php backend/src/Application/Services/CourseService.php backend/src/Infrastructure/Persistence/CourseRepositoryInterface.php backend/src/Infrastructure/Persistence/PdoCourseRepository.php backend/tests/Unit/Course backend/tests/Unit/Infrastructure/PdoCourseRepositoryTest.php
git commit -m "feat: add typed course catalog service"
```

### Task 3: Add course HTTP endpoints and registration validation

**Files:**
- Create: `backend/src/Http/Controllers/CourseController.php`
- Create: `backend/src/Http/Routing/CourseRouteTable.php`
- Modify: `backend/src/Bootstrap/ApplicationFactory.php`
- Modify: `backend/src/Application/Services/RegistrationService.php`
- Modify: `backend/tests/Unit/Registration/RegistrationServiceTest.php`
- Create: `backend/tests/Feature/CourseControllerTest.php`

**Interfaces:**

```text
GET  /api/courses        -> CourseController::index()
GET  /api/admin/courses  -> CourseController::adminIndex()
POST /api/admin/courses  -> CourseController::store()
```

All successful payloads use the existing `{ "ok": true, "data": ... }` JSON shape. The catalog list is `data.courses`, with each course represented as `{ "id": int, "name": string, "created_at": string }`.

- [ ] **Step 1: Add failing controller and registration tests**

Cover public catalog access, unauthenticated admin rejection with the existing 401 behavior, non-admin rejection with the existing authorization behavior, invalid CSRF with 419, successful creation, duplicate response with 422, and student registration rejection when `existsByName()` returns false.

Run:

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend/phpunit.xml backend/tests/Feature/CourseControllerTest.php backend/tests/Unit/Registration/RegistrationServiceTest.php
```

Expected: FAIL because the controller/routes and catalog validation are absent.

- [ ] **Step 2: Implement `CourseController`**

Use `SessionService::current()` and the existing `Role::ADMIN` comparison pattern. `index()` is public. `adminIndex()` and `store()` require an administrator. `store()` calls `CsrfService::assertValid()`, builds `CreateCourseRequest` from a trimmed `name`, catches `InvalidArgumentException`, and returns structured JSON errors without exposing SQL details.

- [ ] **Step 3: Implement `CourseRouteTable` and application wiring**

Construct one `PdoCourseRepository`, one `CourseService`, and one `CourseController` in `ApplicationFactory`. Pass the same repository to `RegistrationService` for student-course existence validation. Merge `(new CourseRouteTable())->routes($courseController)` into the existing router array.

- [ ] **Step 4: Add registration catalog validation**

Extend `RegistrationService` with a typed `CourseRepositoryInterface` constructor dependency. After the existing validator passes and before the barcode check, add:

```php
if ($request->role === 'student' && !$this->courses->existsByName($request->course)) {
    return RegistrationResult::failure('Please select a course from the course list.');
}
```

Update existing registration test doubles and constructor calls to supply the repository mock. Do not change teacher validation.

- [ ] **Step 5: Run the targeted backend tests and PHPStan on new files**

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend/phpunit.xml backend/tests/Feature/CourseControllerTest.php backend/tests/Unit/Registration/RegistrationServiceTest.php
C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon backend/src/Domain/Course backend/src/Application/DTO/CreateCourseRequest.php backend/src/Application/Services/CourseService.php backend/src/Infrastructure/Persistence/CourseRepositoryInterface.php backend/src/Infrastructure/Persistence/PdoCourseRepository.php backend/src/Http/Controllers/CourseController.php backend/src/Http/Routing/CourseRouteTable.php
```

Expected: PASS with no new PHPStan errors.

- [ ] **Step 6: Commit when repository metadata is writable**

```powershell
git add backend/src/Http/Controllers/CourseController.php backend/src/Http/Routing/CourseRouteTable.php backend/src/Bootstrap/ApplicationFactory.php backend/src/Application/Services/RegistrationService.php backend/tests/Feature/CourseControllerTest.php backend/tests/Unit/Registration/RegistrationServiceTest.php
git commit -m "feat: expose course catalog endpoints"
```

### Task 4: Compute the live course borrower ranking

**Files:**
- Modify: `backend/src/Infrastructure/Persistence/PdoStaffRepository.php`
- Modify: `backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php`
- Modify: `backend/tests/Feature/StaffDashboardContractTest.php`

**Interfaces:**

Add this overview key:

```php
top_course_borrowers: list<array{rank: int, course: string, borrower_count: int}>
```

- [ ] **Step 1: Write the failing normalized-schema ranking test**

Build SQLite tables for `courses`, `users`, `borrowing_transactions`, and `borrowing_items`. Insert at least two student users in one course with multiple items/transactions, one user in another course, a pending transaction, and a teacher row. Assert that the first course count is the number of distinct student IDs, pending and teacher records are excluded, ranks are sequential, and ties sort by course name.

Run:

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend/phpunit.xml backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php
```

Expected: FAIL because `top_course_borrowers` is not returned.

- [ ] **Step 2: Write the failing legacy fallback test**

Create only `courses`, `users`, and the legacy `borrowing` table. Insert repeated approved rows for one student, a pending row, and a teacher row. Assert the same distinct-count and filtering rules.

- [ ] **Step 3: Implement normalized and legacy ranking queries**

Add a private `topCourseBorrowers()` method. For normalized storage, use a left join from `courses` through `users` to approved transaction/item rows and `COUNT(DISTINCT bt.user_id)`. For legacy storage, use `COUNT(DISTINCT br.user_id)`. Sort by `borrower_count DESC, course ASC`, limit to five, and assign `rank` while mapping rows. Keep course names from the catalog rather than from user text values.

The normalized predicate must include:

```sql
u.role = 'student'
AND bt.approval_status = 'approved'
AND bt.borrow_date IS NOT NULL
```

The legacy predicate must use the corresponding `borrowing` approval and borrow-date columns.

- [ ] **Step 4: Add the key to the dashboard overview and update PHPDoc**

Return `'top_course_borrowers' => $this->topCourseBorrowers()` from `dashboardOverview()` and include its exact array shape in the method docblock.

- [ ] **Step 5: Extend the dashboard contract fixture**

Add a `top_course_borrowers` row to `StaffDashboardContractTest` and assert that the response preserves it unchanged through the controller.

- [ ] **Step 6: Run ranking and dashboard tests**

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend/phpunit.xml backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php backend/tests/Feature/StaffDashboardContractTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit when repository metadata is writable**

```powershell
git add backend/src/Infrastructure/Persistence/PdoStaffRepository.php backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php backend/tests/Feature/StaffDashboardContractTest.php
git commit -m "feat: rank top course borrowers from transactions"
```

### Task 5: Add the administrator College Settings tab

**Files:**
- Modify: `frontend/features/staff/pages/admin-staff/admin-staff.html`
- Modify: `frontend/features/staff/pages/admin-staff/admin-staff.page.js`
- Modify: `frontend/features/staff/services/admin-staff.service.js`
- Modify: `frontend/assets/css/staff-management.css`
- Modify: `frontend/tests/staff-pages.test.js`

**Interfaces:**

```js
AdminStaffService.courses() -> GET /scan2borrow/api/admin/courses
AdminStaffService.addCourse(name) -> POST /scan2borrow/api/admin/courses
AdminStaffPage.renderCourses(rows)
```

- [ ] **Step 1: Add failing markup and service contract tests**

Assert that the admin page contains a tab named `College Settings`, a `course-form`, a `course-name` field, a `courses-body` list, and the course API paths. Assert that a fake API receives the expected GET and POST calls.

Run:

```powershell
npm test -- --test-name-pattern="staff"
```

Expected: FAIL because the tab and service methods are absent.

- [ ] **Step 2: Add tab markup and course management controls**

Add an accessible `role="tablist"` with `Staff Accounts` and `College Settings` buttons. Mark existing staff content as the first panel. Add a hidden College Settings panel with an Add Course form and a table containing course name and created date. Give all new elements stable IDs/data attributes for tests and DOM binding.

- [ ] **Step 3: Add service methods**

Extend `AdminStaffService`:

```js
courses() { return this.api.get('/scan2borrow/api/admin/courses'); }
addCourse(name) { return this.api.post('/scan2borrow/api/admin/courses', { name }); }
```

- [ ] **Step 4: Implement tab, list, and create behavior**

Update `AdminStaffPage` to bind tab buttons, load the catalog after the existing staff data, render escaped course rows, submit the form through `addCourse()`, clear the field on success, reload the catalog, and show existing success/error feedback. If a course request fails, leave staff account management usable and show the error in the feedback region.

- [ ] **Step 5: Add responsive tab/list styling**

Extend `staff-management.css` with hairline tab rules, active-state styling, a compact course form, and mobile wrapping. Do not introduce fabricated course values or a delete control.

- [ ] **Step 6: Run the frontend staff tests**

```powershell
npm test -- --test-name-pattern="staff|admin"
```

Expected: PASS.

- [ ] **Step 7: Commit when repository metadata is writable**

```powershell
git add frontend/features/staff/pages/admin-staff/admin-staff.html frontend/features/staff/pages/admin-staff/admin-staff.page.js frontend/features/staff/services/admin-staff.service.js frontend/assets/css/staff-management.css frontend/tests/staff-pages.test.js
git commit -m "feat: add administrator college settings tab"
```

### Task 6: Render the dashboard ranking with live refresh

**Files:**
- Modify: `frontend/features/staff/models/dashboard.model.js`
- Modify: `frontend/features/staff/pages/dashboard/dashboard.html`
- Modify: `frontend/features/staff/pages/dashboard/staff-dashboard.page.js`
- Modify: `frontend/assets/css/admin-overview.css`
- Modify: `frontend/tests/staff-pages.test.js` — ranking model/render behavior.
- Modify: `backend/tests/Feature/StaffDashboardMarkupTest.php`

**Interfaces:**

```js
normalizeStaffDashboard(value).overview.top_course_borrowers
// Array<{ rank: number, course: string, borrower_count: number }>
```

- [ ] **Step 1: Add failing model and markup assertions**

Assert that normalization retains the ranking fields and that the dashboard source includes `overview-course-borrowers`, `overview-course-borrowers-list`, and the `Top 5 Course Borrowers` heading.

Run:

```powershell
npm test -- --test-name-pattern="dashboard"
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend/phpunit.xml backend/tests/Feature/StaffDashboardMarkupTest.php
```

Expected: FAIL because the model and markup do not contain the new section.

- [ ] **Step 2: Add normalized model data**

Map `value.overview.top_course_borrowers` to shallow-cloned rows and default to an empty array. Preserve numeric rank and borrower count through the existing normalizer’s number helper when rendering.

- [ ] **Step 3: Add dashboard markup**

Add an overview panel with the exact title `Top 5 Course Borrowers`, an explanatory description, and a list hook. Keep the section within the current overview grid and avoid changing unrelated panels.

- [ ] **Step 4: Implement escaped ranked-row rendering**

Add `renderTopCourseBorrowers(rows)` to `StaffDashboardPage`. Use server rank when present, escape the course name, pluralize the borrower label from the numeric count, and calculate each bar width as `count / maxCount * 100` with a zero-safe fallback. Render a clear empty message when rows are empty.

- [ ] **Step 5: Add Swiss visual treatment**

Add panel rules using `#F7F7F8`/white surfaces, `#002FA7` accent, Helvetica Neue/Arial, 1px rules, left alignment, and tabular numerics. Use the ranked rail and proportional bar as the single differentiator. Add responsive rules so the list remains readable at the project’s supported narrow viewport.

- [ ] **Step 6: Refresh dashboard data after actions and periodically**

Keep the existing notification polling. Add one dashboard refresh timer owned by `StaffDashboardPage`, avoid creating duplicate timers when `dashboard()` runs again, refresh after existing circulation/approval actions that already call `dashboard()`, and clear the timer in a `destroy()`/`beforeunload` cleanup path.

- [ ] **Step 7: Run frontend and markup tests**

```powershell
npm test -- --test-name-pattern="dashboard"
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend/phpunit.xml backend/tests/Feature/StaffDashboardMarkupTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit when repository metadata is writable**

```powershell
git add frontend/features/staff/models/dashboard.model.js frontend/features/staff/pages/dashboard/dashboard.html frontend/features/staff/pages/dashboard/staff-dashboard.page.js frontend/assets/css/admin-overview.css frontend/tests backend/tests/Feature/StaffDashboardMarkupTest.php
git commit -m "feat: show top course borrowers on dashboard"
```

### Task 7: Load the catalog in student registration

**Files:**
- Modify: `frontend/features/auth/pages/register/register.html`
- Modify: `frontend/features/auth/pages/register/register.page.js`
- Modify: `frontend/tests/auth-pages.test.js`

**Interfaces:**

```text
GET /scan2borrow/api/courses
```

- [ ] **Step 1: Add failing registration catalog assertions**

Assert that registration no longer contains the previous hard-coded course options, contains a catalog hook, and requests `/scan2borrow/api/courses` during startup.

Run:

```powershell
npm test -- --test-name-pattern="auth|registration"
```

Expected: FAIL because registration currently embeds static course options and makes no catalog request.

- [ ] **Step 2: Replace static options with a catalog loading state**

Keep the existing select element and default `Select Course` option. Add a disabled loading state that is replaced by escaped catalog rows from the API. On failure, show a concise form error and keep the select disabled until a valid catalog is available.

- [ ] **Step 3: Implement the fetch in `RegistrationPage`**

Call `fetch('/scan2borrow/api/courses', { headers: { Accept: 'application/json' } })` from `start()` or the constructor’s startup path, parse the existing JSON envelope, reject non-OK responses, sort server results by name if needed, and append options through DOM APIs or escaped HTML. Keep the existing registration submission and role toggling behavior unchanged.

- [ ] **Step 4: Run registration tests**

```powershell
npm test -- --test-name-pattern="auth|registration"
```

Expected: PASS.

- [ ] **Step 5: Commit when repository metadata is writable**

```powershell
git add frontend/features/auth/pages/register/register.html frontend/features/auth/pages/register/register.page.js frontend/tests/auth-pages.test.js
git commit -m "feat: load registration courses from catalog"
```

### Task 8: Run the complete quality gate and inspect the final diff

**Files:**
- Inspect all files changed by Tasks 1–7.
- Modify only files required to resolve verified failures.

- [ ] **Step 1: Run the complete frontend suite**

```powershell
npm test
```

Expected: all Node tests pass.

- [ ] **Step 2: Run the complete backend PHPUnit suite**

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend/phpunit.xml
```

Expected: all backend tests pass within the repository’s 110-second lane target.

- [ ] **Step 3: Run PHPStan**

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon
```

Expected: no PHPStan errors.

- [ ] **Step 4: Run whitespace and source checks**

```powershell
git diff --check
rg -n "test\.only|describe\.only|it\.only|\.skip\(|xit\(|xdescribe\(" frontend/tests backend/tests
```

Expected: no whitespace errors and no newly introduced focused/skipped tests.

- [ ] **Step 5: Inspect the final scope**

```powershell
git status --short
git diff --stat
git diff -- frontend/assets/css/admin-overview.css frontend/features/staff/pages/dashboard/dashboard.html frontend/features/staff/pages/admin-staff/admin-staff.html sql/upgrade_courses.sql
```

Confirm that unrelated existing changes (`frontend/features/staff/services/index.js`, `frontend/tests/scanner.test.js`, and untracked user assets) remain untouched and that no secrets or generated files were added.

- [ ] **Step 6: Report verification honestly**

Report fresh command output for every completed gate. If GitHub Actions access is unavailable, label remote CI as unverified rather than implying it passed. If `.git` remains read-only, report that commits could not be created even though source verification completed.


