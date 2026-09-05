# Student and Teacher Activity Logs

## User outcome

Students and teachers can see their five most recent account activities directly
on their dashboard and open a dedicated Activity Logs tab for the complete
timeline of their own account.

The existing borrowing-history pages remain focused on borrowing records. The
new activity timeline is a separate cross-workflow view.

## Scope and behavior

### Dashboard preview

- `GET /api/student/dashboard` and `GET /api/teacher/dashboard` gain a
  `recent_activity` array.
- The array is sorted newest first and contains at most five entries.
- The preview includes a link to the role-specific full activity page.
- Existing dashboard statistics, current-loan data, borrowing controls, and
  history links remain unchanged.

### Full activity pages

- Add `/student/activity` and `/teacher/activity` page routes.
- Add `GET /api/student/activity` and `GET /api/teacher/activity` API routes.
- Each API response is scoped to the authenticated borrower and returns an
  `activity` array containing the complete available account timeline.
- Add an `Activity Logs` item to the student and teacher sidebars. The existing
  borrowing-history item remains separate.
- Use one shared timeline renderer with role-owned page templates and styling
  hooks. The renderer must provide explicit loading, empty, and error states.

Each activity entry uses a stable shape:

```text
{
  id: string|int,
  type: string,
  label: string,
  details: string,
  title: string,
  transaction_code: string,
  status: string,
  occurred_at: string
}
```

The UI escapes all server-provided text before inserting it into markup and
formats timestamps without relying on a live or hardcoded date.

## Data sources and backend design

`PdoBorrowerPortalRepository` owns the read model so the controller and both
roles share the same authorization and ordering behavior. Add methods for the
full activity timeline and bounded recent activity, while retaining the
existing `dashboard`, `history`, and `receipt` contracts.

The repository assembles a unified event list from available existing sources:

1. Borrowing transactions/items, including pending, borrowed, overdue, and
   returned states. The normalized `borrowing_transactions`/
   `borrowing_items` model is preferred, with the legacy `borrowing` table used
   when normalized tables are unavailable.
2. Reservation records when the `reservations` table exists.
3. Renewal request records when the `renewal_requests` table exists.
4. Borrower profile-change request records when the
   `profile_change_requests` table exists.
5. The authenticated user’s `last_login` value when present.
6. Existing `audit_log` records for that user when the table exists.

Each source is converted to the stable entry shape, merged in PHP, sorted by
`occurred_at DESC` with a deterministic source/id tie-breaker, and then either
returned in full or sliced to five entries. Table-existence checks preserve
compatibility with the repository’s legacy SQLite/MySQL test fixtures and
older installations. Queries always bind the authenticated `user_id`.

No new write-side activity table or migration is required for this scope. The
design intentionally exposes activity already represented by current domain
records and leaves room to add future event sources behind the same read model.

The borrower controller will expose the new read endpoint through the existing
student/teacher route table. It will reject any non-student/non-teacher session
with the existing borrower authentication response.

## Frontend design

The dashboard controllers render `recent_activity` after the existing dashboard
payload is loaded. A shared borrower activity component/page renderer owns:

- stable activity row markup;
- date/time and status presentation;
- HTML escaping;
- empty and error copy;
- the full-page fetch and render lifecycle.

The student and teacher templates retain their existing role-specific visual
surfaces and add a compact recent-activity panel. The full pages use role-owned
templates and scoped CSS, while sharing the renderer and response contract.

The sidebar marks the activity route active based on the current path. No guest
or staff navigation changes are included.

## Testing and verification

Test-first implementation will add coverage for:

- repository event normalization, newest-first ordering, five-item limiting,
  legacy fallback, optional-table handling, and user isolation;
- controller/API response shape and role authorization;
- page routes, sidebar links, dashboard preview hooks, and full-page markup;
- shared renderer escaping, empty/error states, and activity row rendering;
- existing frontend, PHPUnit, and PHPStan gates.

The implementation will run targeted tests after each red-green cycle, then the
complete relevant local gates documented by the repository:

```text
npm test
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml
C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon
```

Remote GitHub Actions verification will be reported separately if repository
access is available; no workflow files are expected to change.
