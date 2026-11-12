# Top 5 Course Borrowers and Course Catalog Design

## Context

Scan2Borrow already exposes staff dashboard metrics from the database and stores borrower course values as text on `users.course`. The requested feature adds a database-backed course catalog, administrator-managed course creation, and a dashboard ranking of the five courses with the most distinct student borrowers.

## Approved decisions

- Use a new `courses` table while preserving the existing `users.course` text column for compatibility.
- Treat “number of borrowers” as distinct student borrowers per course. A student who borrows multiple books or completes multiple borrowing transactions counts once for that course.
- Count approved borrowing transactions with a real borrow date. Returned and currently active approved loans both contribute to historical borrower counts.
- Sort by borrower count descending, then course name ascending for deterministic ties.
- Show the first five ranked rows and assign rankings on the server response.
- Manage courses in a separate `College Settings` tab inside the existing administrator staff page, alongside the existing `Staff Accounts` tab.
- Add courses only. Do not add delete behavior, because removing a catalog record could make historical user and borrowing data ambiguous.
- Populate new student registration course choices from the database catalog and validate the selected course on the backend.

## Course catalog

Create a `courses` table with:

- an auto-incrementing integer identifier;
- a course name with a case-insensitive unique constraint;
- a creation timestamp.

Fresh installs must seed these course names:

1. `BSIT`
2. `BSEd Major in Science`
3. `BSEd Major in English`
4. `BEeD`
5. `BSBA Major in Financial Management`
6. `BSBA Major in Human Resources Management`
7. `BSA`

Existing installations must receive an idempotent upgrade migration. The migration must not overwrite existing user course values or borrowing records.

New course creation trims input, rejects empty values, enforces the application’s course-name length limit, and rejects duplicates without relying only on frontend validation.

## Dashboard ranking

The existing staff dashboard response will gain an overview collection named `top_course_borrowers`. Each row contains:

- `rank` — integer from 1 through 5;
- `course` — catalog course name;
- `borrower_count` — number of distinct student user IDs with qualifying borrowing records.

For the normalized borrowing schema, the query joins `courses`, `users`, `borrowing_transactions`, and `borrowing_items`. It filters to student users, approved transactions, and non-null borrow dates, and counts distinct transaction user IDs. Returned items remain eligible because the ranking represents historical borrowing activity.

The existing legacy-table fallback remains compatible where the normalized borrowing tables are not available. It uses the legacy `borrowing` rows with the same student, approval, and borrow-date rules.

The result is computed from current database state for each dashboard request. No counters are stored or manually incremented. The dashboard refreshes after circulation actions and periodically while open, so new borrowing activity is reflected without a full-page reload.

Courses with zero qualifying borrowers may be included before the final five-row limit, ensuring catalog-backed results rather than fabricated entries. If the catalog or transactions produce no rows, the frontend renders an explicit empty state.

## Backend interfaces

Introduce a focused course persistence/service boundary following the existing PDO repository style:

- read the public course catalog;
- read the administrator course list;
- create a course after typed validation;
- check case-insensitive uniqueness.

Expose these HTTP operations:

- `GET /api/courses` — public, read-only catalog for registration;
- `GET /api/admin/courses` — administrator-only catalog management read;
- `POST /api/admin/courses` — administrator-only course creation with CSRF validation.

The admin endpoints must return structured JSON errors for unauthorized access, CSRF failure, invalid names, and duplicate names. All database writes use prepared statements.

## Frontend behavior

### Admin College Settings tab

The existing administrator page gains two tabs:

- `Staff Accounts` — existing behavior;
- `College Settings` — new course catalog management.

The College Settings tab contains an Add Course form and a database-backed list of saved courses. Successful creation updates the list without a full page reload. Duplicate and validation errors are shown in the page’s existing feedback surface.

### Registration

The student registration course control becomes a select populated from `GET /api/courses`. The page handles loading, empty, and failed catalog states. Backend registration validation independently checks that the submitted course exists.

### Dashboard

Add a `Top 5 Course Borrowers` panel to the existing admin overview. It shows rank, course name, and distinct borrower count. A horizontal relative bar is visualized from returned values only; it is not a second source of truth.

The new surface follows a Swiss visual direction: neutral white/near-white surfaces, Helvetica Neue/Arial typography, Yves Klein Blue `#002FA7` as the single accent, 1px hairline rules, left-aligned text, and tabular numerics. The memorable interaction is the ranked rail with proportional activity bars.

All dynamic course names and numbers are escaped before insertion. No fabricated sample counts or course rows are rendered.

## Error handling and security

- Require administrator authorization for course management endpoints and the College Settings tab page.
- Require CSRF validation for course creation.
- Validate and normalize course names on the backend.
- Use parameterized SQL for all reads with user input and all writes.
- Preserve the existing `users.course` values and borrowing history.
- Render empty and failed dashboard/catalog states as user-facing messages without exposing SQL errors.

## Verification strategy

Backend tests must cover:

- distinct borrower counting when one student has multiple borrowing records;
- normalized and legacy ranking paths;
- deterministic rank ordering and ties;
- approved, pending, and rejected transaction filtering;
- course creation, trimming, duplicate rejection, and list retrieval;
- admin authorization and CSRF behavior;
- install and upgrade schema contracts.

Frontend tests must cover:

- dashboard model normalization for `top_course_borrowers`;
- dashboard markup and ranked-row rendering;
- College Settings tab markup and Add Course interaction;
- registration catalog loading and failure states;
- preservation of existing dashboard, admin, and registration contracts.

Before completion, run the complete project gates:

```text
npm test
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml
C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon
```

