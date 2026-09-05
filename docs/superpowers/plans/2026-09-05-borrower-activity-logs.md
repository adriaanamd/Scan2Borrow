# Student and Teacher Activity Logs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (\`- [ ]\`) syntax for tracking.

**Goal:** Add a five-entry recent activity preview to student and teacher dashboards and a complete, role-scoped Activity Logs page for each role.

**Architecture:** Extend the existing borrower portal repository with a unified read-only activity projection assembled from existing borrowing, reservation, renewal, profile-change, login, and audit records. Reuse one shared frontend timeline renderer behind role-owned activity page templates and add dedicated role routes/API endpoints without changing the existing borrowing-history contract.

**Tech Stack:** PHP 8.2+, PDO/MySQL with SQLite PHPUnit fixtures, vanilla ES modules, HTML, CSS, Bootstrap-compatible templates, and Node’s built-in test runner.

## Global Constraints

- Use the authenticated borrower ID for every activity query; never accept a user ID from the client.
- Return at most five entries for dashboard \`recent_activity\`; return the complete available timeline from the activity endpoints.
- Prefer normalized borrowing tables and use the legacy \`borrowing\` table when normalized tables are unavailable.
- Treat reservations, renewals, profile changes, \`last_login\`, and \`audit_log\` as optional existing sources guarded by \`hasTable()\`.
- Do not add a new activity table or migration for this feature.
- Escape every server-provided activity value before placing it in HTML.
- Keep guest and staff navigation and existing student/teacher borrowing-history pages unchanged.
- Do not add dependencies or external chart/UI libraries.
- Run targeted tests after each red-green cycle, then \`npm test\`, PHPUnit, and PHPStan before completion.

---

### Task 1: Define the repository activity contract with failing tests

**Files:**
- Modify: \`backend/tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php\`
- Modify: \`backend/src/Infrastructure/Persistence/BorrowerPortalRepositoryInterface.php\`

**Interfaces:**
- Add \`activity(int $userId): array\` and \`recentActivity(int $userId): array\`.
- Every entry has \`id\`, \`type\`, \`label\`, \`details\`, \`title\`, \`transaction_code\`, \`status\`, and \`occurred_at\`.

- [ ] **Step 1: Write the failing contract test.**

Add normalized fixtures for two users, a borrowing transaction/item, a
reservation, a renewal request, a profile-change request, and audit-log rows.
Assert that \`activity(1)\` is newest-first, includes normalized labels, does
not include user 2’s details, and that \`recentActivity(1)\` returns five
entries when more than five records exist.

\`\`\`php
public function testActivityIsUserScopedNewestFirstAndRecentIsLimitedToFive(): void
{
    $this->createNormalizedActivityTables();
    $this->insertActivityFixturesForUserOneAndUserTwo();

    $repository = new PdoBorrowerPortalRepository($this->pdo);
    $activity = $repository->activity(1);

    self::assertSame('2026-08-06 14:00:00', $activity[0]['occurred_at']);
    self::assertSame('login', $activity[0]['type']);
    self::assertNotContains('Other account', array_column($activity, 'details'));
    self::assertCount(5, $repository->recentActivity(1));
}
\`\`\`

- [ ] **Step 2: Run the focused test and verify the expected red failure.**

\`\`\`text
C:\\xampp\\php\\php.exe backend\\vendor\\bin\\phpunit --configuration=backend\\phpunit.xml backend\\tests\\Unit\\Infrastructure\\PdoBorrowerPortalRepositoryTest.php --filter Activity
\`\`\`

Expected: FAIL because the two repository methods do not exist.

- [ ] **Step 3: Add the exact interface declarations.**

\`\`\`php
/** @return list<array<string, mixed>> */
public function activity(int $userId): array;

/** @return list<array<string, mixed>> */
public function recentActivity(int $userId): array;
\`\`\`

- [ ] **Step 4: Re-run the focused test and verify it remains red for missing implementation.**

Use the same command. Expected: FAIL with repository behavior missing, not a
fixture or syntax error.

- [ ] **Step 5: Commit.**

\`\`\`text
git add backend/tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php backend/src/Infrastructure/Persistence/BorrowerPortalRepositoryInterface.php
git commit -m "test: define borrower activity repository contract"
\`\`\`

### Task 2: Implement the unified activity read model

**Files:**
- Modify: \`backend/src/Infrastructure/Persistence/PdoBorrowerPortalRepository.php\`
- Modify: \`backend/tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php\`

**Interfaces:**
- \`activity(int $userId)\` returns the complete merged timeline.
- \`recentActivity(int $userId)\` returns the first five entries from that timeline.
- \`dashboard(int $userId)\` includes \`recent_activity\`.

- [ ] **Step 1: Write a failing legacy fallback test.**

Using the current legacy fixture, insert active and returned \`borrowing\` rows.
Assert that the repository emits a borrow event at \`borrow_date\` and a return
event at \`return_date\`, newest first, without requiring optional tables.

\`\`\`php
public function testActivityFallsBackToLegacyBorrowingWithoutOptionalTables(): void
{
    $this->pdo->exec("INSERT INTO borrowing VALUES
        (4, 'TX-4', 1, 1, '2026-08-11 09:00:00', '2026-08-18', NULL, 'Borrowed', 0),
        (5, 'TX-5', 1, 2, '2026-08-01 09:00:00', '2026-08-08', '2026-08-07 16:00:00', 'Returned', 0)");

    $activity = (new PdoBorrowerPortalRepository($this->pdo))->activity(1);

    self::assertSame('2026-08-11 09:00:00', $activity[0]['occurred_at']);
    self::assertSame('2026-08-07 16:00:00', $activity[1]['occurred_at']);
    self::assertSame('Returned', $activity[1]['status']);
}
\`\`\`

- [ ] **Step 2: Run the focused test and verify red.**

Run the Task 1 PHPUnit command with \`--filter Activity\`. Expected: FAIL on
the legacy activity assertions.

- [ ] **Step 3: Implement source readers and deterministic merging.**

Add these private methods to \`PdoBorrowerPortalRepository\`:

\`\`\`php
/** @return list<array<string, mixed>> */
private function activityRows(int $userId): array;

/** @return list<array<string, mixed>> */
private function normalizedBorrowingActivity(int $userId): array;

/** @return list<array<string, mixed>> */
private function legacyBorrowingActivity(int $userId): array;

/** @param array<string, mixed> $row */
private function activityEntry(
    mixed $id,
    string $type,
    string $label,
    string $details,
    string $title,
    string $transactionCode,
    string $status,
    string $occurredAt,
): array;
\`\`\`

Use \`hasTable('borrowing_items')\` to choose normalized versus legacy
borrowing. The normalized query joins transactions, items, copies, and titles,
filters \`bt.user_id = :user_id\`, and emits borrow/request and non-empty return
events. The legacy query joins \`borrowing\` to \`books\`, filters
\`br.user_id = :user_id\`, and emits equivalent events.

Guard optional readers with \`hasTable()\`: reservations emit creation and
later status updates; renewals emit request and decision events; profile changes
emit request and review events; \`users.last_login\` emits a login event; and
\`audit_log\` emits stored action/details rows. Every reader binds the supplied
user ID.

Merge all rows, sort by \`occurred_at DESC\` and then deterministic type/id
descending, normalize missing values to empty strings, and return the stable
entry shape. Implement:

\`\`\`php
public function activity(int $userId): array
{
    $rows = $this->activityRows($userId);
    usort($rows, static fn (array $left, array $right): int =>
        [$right['occurred_at'], $right['type'], (string) $right['id']]
        <=> [$left['occurred_at'], $left['type'], (string) $left['id']]
    );
    return $rows;
}

public function recentActivity(int $userId): array
{
    return array_slice($this->activity($userId), 0, 5);
}
\`\`\`

- [ ] **Step 4: Add the dashboard field.**

Add \`'recent_activity' => $this->recentActivity($userId)\` beside
\`current_loans\` in the dashboard payload.

- [ ] **Step 5: Run repository tests and verify green.**

\`\`\`text
C:\\xampp\\php\\php.exe backend\\vendor\\bin\\phpunit --configuration=backend\\phpunit.xml backend\\tests\\Unit\\Infrastructure\\PdoBorrowerPortalRepositoryTest.php
\`\`\`

Expected: all tests in the file pass.

- [ ] **Step 6: Commit.**

\`\`\`text
git add backend/src/Infrastructure/Persistence/PdoBorrowerPortalRepository.php backend/tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php
git commit -m "feat: expose borrower account activity"
\`\`\`

### Task 3: Add borrower activity API and protected page routes

**Files:**
- Modify: \`backend/src/Http/Controllers/BorrowerController.php\`
- Modify: \`backend/src/Http/Routing/BorrowerRouteTable.php\`
- Modify: \`backend/src/Http/Routing/PageRouteTable.php\`
- Modify: \`backend/src/Http/Documentation/ApiEndpointCatalog.php\`
- Modify: \`backend/tests/Feature/PageRouteTableTest.php\`
- Create: \`backend/tests/Feature/BorrowerActivityContractTest.php\`

**Interfaces:**
- \`BorrowerController::activity(ServerRequest $request): JsonResponse\`.
- \`GET /api/student/activity\` and \`GET /api/teacher/activity\` return
  \`{ok: true, data: {activity: [...]}}\`.
- \`/student/activity\` allows only student; \`/teacher/activity\` allows only
  teacher.

- [ ] **Step 1: Write failing controller and route tests.**

Use fake repository/session dependencies. Assert the controller returns the
repository’s activity under \`data.activity\`, never accepts a client user ID,
and returns the existing 401 response for staff. Assert both page paths have
the exact role policy and feature-owned templates.

- [ ] **Step 2: Run focused tests and verify red.**

\`\`\`text
C:\\xampp\\php\\php.exe backend\\vendor\\bin\\phpunit --configuration=backend\\phpunit.xml backend\\tests\\Feature\\BorrowerActivityContractTest.php backend\\tests\\Feature\\PageRouteTableTest.php --filter Activity
\`\`\`

Expected: FAIL because the method and routes do not exist.

- [ ] **Step 3: Implement the controller method and route registrations.**

\`\`\`php
public function activity(ServerRequest $request): JsonResponse
{
    $identity = $this->borrower();
    if ($identity === null) {
        return $this->unauthorized();
    }

    return new JsonResponse(200, [
        'ok' => true,
        'data' => ['activity' => $this->portal->activity($identity->userId())],
    ]);
}
\`\`\`

Register both GET API routes in \`BorrowerRouteTable\`, both protected page
routes in \`PageRouteTable\`, and update both API catalog descriptions.

- [ ] **Step 4: Run focused tests and verify green.**

Use the Task 3 PHPUnit command. Expected: all selected tests pass.

- [ ] **Step 5: Commit.**

\`\`\`text
git add backend/src/Http/Controllers/BorrowerController.php backend/src/Http/Routing/BorrowerRouteTable.php backend/src/Http/Routing/PageRouteTable.php backend/src/Http/Documentation/ApiEndpointCatalog.php backend/tests/Feature/PageRouteTableTest.php backend/tests/Feature/BorrowerActivityContractTest.php
git commit -m "feat: add borrower activity endpoints"
\`\`\`

### Task 4: Build the shared activity timeline and role-owned pages

**Files:**
- Create: \`frontend/app/shared/components/activity-timeline/activity-timeline.component.js\`
- Create: \`frontend/app/shared/pages/borrower-activity.page.js\`
- Create: \`frontend/features/student/pages/activity/activity.html\`
- Create: \`frontend/features/student/pages/activity/student-activity.page.js\`
- Create: \`frontend/features/teacher/pages/activity/activity.html\`
- Create: \`frontend/features/teacher/pages/activity/teacher-activity.page.js\`
- Create: \`frontend/assets/css/borrower-activity.css\`
- Create: \`frontend/tests/borrower-activity.test.js\`

**Interfaces:**
- \`ActivityTimelineComponent.render(rows, options)\` renders rows or the
  empty state.
- \`BorrowerActivityPage\` accepts \`api\`, \`role\`, \`title\`,
  \`description\`, and \`classPrefix\`, and exposes \`load()\` and \`render()\`.

- [ ] **Step 1: Write failing frontend contract tests.**

Require the shared component to expose \`render\`, \`escapeHtml\`, and
\`formatDate\`; require the shared page to fetch the configured endpoint and use
\`payload.data.activity\`; and require both templates to contain their
\`data-app-page\`, \`data-navbar-role\`, \`activity-timeline\`, and module
markers.

\`\`\`js
test('borrower activity pages use the shared timeline renderer', () => {
  assert.match(read('app/shared/components/activity-timeline/activity-timeline.component.js'), /class ActivityTimelineComponent/);
  assert.match(read('app/shared/pages/borrower-activity.page.js'), /data\\.activity/);
  assert.match(read('features/student/pages/activity/activity.html'), /data-app-page="student-activity"/);
  assert.match(read('features/teacher/pages/activity/activity.html'), /data-app-page="teacher-activity"/);
});
\`\`\`

- [ ] **Step 2: Run focused Node tests and verify red.**

\`\`\`text
node --test frontend/tests/borrower-activity.test.js
\`\`\`

Expected: FAIL because the shared component and role pages do not exist.

- [ ] **Step 3: Implement the renderer and page shell.**

\`ActivityTimelineComponent\` renders classes
\`activity-timeline__item\`, \`activity-timeline__label\`,
\`activity-timeline__details\`, \`activity-timeline__status\`, and
\`activity-timeline__time\`. It escapes all server values, formats dates with
\`toLocaleString('en-US')\`, falls back on invalid dates, and renders
\`No account activity yet.\` when empty.

\`BorrowerActivityPage\` fetches with \`X-Requested-With: fetch\`, throws on
non-OK or \`payload.ok === false\`, passes \`payload.data?.activity || []\` to
the component, and renders \`Unable to load activity.\` on failure.

Student wrapper configuration:

\`\`\`js
new BorrowerActivityPage({
  api: '/scan2borrow/api/student/activity',
  role: 'Student',
  title: 'Activity Logs',
  description: 'Your complete account activity timeline.',
  classPrefix: 'student-activity',
});
\`\`\`

Teacher wrapper uses the teacher endpoint, \`Teacher\`, and
\`teacher-activity\`.

- [ ] **Step 4: Add role-owned HTML and scoped CSS.**

Both templates load \`style.css\`, \`borrower-activity.css\`, and
\`app-navbar.js\); contain the central navbar, topbar, activity host, and role
wrapper module. The CSS defines readable rows, status badges, timestamps,
responsive stacking below 768px, keyboard focus, and reduced-motion behavior
under \`.borrower-activity\` or role prefixes.

- [ ] **Step 5: Run focused Node tests and verify green.**

\`\`\`text
node --test frontend/tests/borrower-activity.test.js
\`\`\`

Expected: all selected tests pass.

- [ ] **Step 6: Commit.**

\`\`\`text
git add frontend/app/shared/components/activity-timeline frontend/app/shared/pages/borrower-activity.page.js frontend/features/student/pages/activity frontend/features/teacher/pages/activity frontend/assets/css/borrower-activity.css frontend/tests/borrower-activity.test.js
git commit -m "feat: add borrower activity log pages"
\`\`\`

### Task 5: Add dashboard previews and sidebar navigation

**Files:**
- Modify: \`frontend/features/student/pages/dashboard/dashboard.html\`
- Modify: \`frontend/features/student/pages/dashboard/student-dashboard.page.js\`
- Modify: \`frontend/features/teacher/pages/dashboard/dashboard.html\`
- Modify: \`frontend/features/teacher/pages/dashboard/teacher-dashboard.page.js\`
- Modify: \`frontend/assets/js/core/app-navbar.js\`
- Modify: \`backend/tests/Feature/RoleNavbarContractTest.php\`
- Modify: \`frontend/tests/student-pages.test.js\`
- Modify: \`frontend/tests/teacher-services.test.js\`
- Modify: \`frontend/tests/borrower-activity.test.js\`

**Interfaces:**
- Controllers expose \`renderRecentActivity(rows)\` and consume
  \`data.recent_activity\`.
- Both dashboards expose \`#recent-activity\` and a role-specific full-page
  link.
- Navbar emits \`/scan2borrow/student/activity\` or
  \`/scan2borrow/teacher/activity\`.

- [ ] **Step 1: Write failing dashboard/navbar assertions.**

Assert each dashboard contains \`id="recent-activity"\` and its matching
activity URL; each controller references \`recent_activity\` and
\`renderRecentActivity\`; and the navbar retains history links while adding
both activity links.

\`\`\`js
test('both dashboards expose recent activity hooks', () => {
  for (const role of ['student', 'teacher']) {
    const html = read('features/' + role + '/pages/dashboard/dashboard.html');
    const source = read('features/' + role + '/pages/dashboard/' + role + '-dashboard.page.js');
    assert.match(html, /id="recent-activity"/);
    assert.match(html, new RegExp('/scan2borrow/' + role + '/activity'));
    assert.match(source, /renderRecentActivity/);
    assert.match(source, /recent_activity/);
  }
});
\`\`\`

- [ ] **Step 2: Run focused tests and verify red.**

\`\`\`text
node --test frontend/tests/borrower-activity.test.js frontend/tests/student-pages.test.js frontend/tests/teacher-services.test.js
\`\`\`

Expected: FAIL because the dashboard hooks and navbar links do not exist.

- [ ] **Step 3: Implement the five-item dashboard panel.**

Add this panel to each dashboard, changing only the role URL:

\`\`\`html
<section class="borrower-dashboard__panel borrower-dashboard__recent-activity" aria-labelledby="recent-activity-title">
  <div class="section-title"><span class="dot"></span> Recent Activity</div>
  <div id="recent-activity" aria-live="polite">
    <p class="text-muted mb-0">No account activity yet.</p>
  </div>
  <a class="btn btn-link btn-sm px-0" href="/scan2borrow/student/activity">View all activity</a>
</section>
\`\`\`

Instantiate \`ActivityTimelineComponent\` on \`#recent-activity\` in each
controller and add:

\`\`\`js
renderRecentActivity(rows) {
  this.activityTimeline.render(
    Array.isArray(rows) ? rows.slice(0, 5) : [],
    { compact: true },
  );
}
\`\`\`

Call it from \`render(data)\` and preserve the existing dashboard methods.

- [ ] **Step 4: Add role-specific Activity Logs navbar links.**

In \`renderBorrower(role)\`, define \`activityPath\`, insert the Activity Logs
link after borrowing history, and update \`roleAllowed()\` for the two exact
paths. Keep all guest and staff branches unchanged.

- [ ] **Step 5: Run focused frontend and PHP tests and verify green.**

\`\`\`text
node --test frontend/tests/borrower-activity.test.js frontend/tests/student-pages.test.js frontend/tests/teacher-services.test.js
C:\\xampp\\php\\php.exe backend\\vendor\\bin\\phpunit --configuration=backend\\phpunit.xml backend\\tests\\Feature\\RoleNavbarContractTest.php backend\\tests\\Feature\\PageRouteTableTest.php
\`\`\`

Expected: all selected tests pass.

- [ ] **Step 6: Commit.**

\`\`\`text
git add frontend/features/student/pages/dashboard frontend/features/teacher/pages/dashboard frontend/assets/js/core/app-navbar.js backend/tests/Feature/RoleNavbarContractTest.php frontend/tests/student-pages.test.js frontend/tests/teacher-services.test.js frontend/tests/borrower-activity.test.js
git commit -m "feat: show recent activity on borrower dashboards"
\`\`\`

### Task 6: Add page parity coverage and run the complete CI gate

**Files:**
- Modify: \`backend/tests/Feature/PageRouteTableTest.php\`
- Modify: \`backend/tests/Feature/RoleNavbarContractTest.php\`
- Modify: \`frontend/tests/served-parity.test.js\`
- Modify: \`frontend/tests/architecture.test.js\`

- [ ] **Step 1: Add failing parity assertions for both new pages.**

Require feature-owned templates, role-correct navbar markers, shared activity
imports, and no guest/staff imports. Add both new paths to protected-page
enumerations without weakening existing assertions.

- [ ] **Step 2: Run parity tests and verify red.**

\`\`\`text
node --test frontend/tests/served-parity.test.js frontend/tests/architecture.test.js
C:\\xampp\\php\\php.exe backend\\vendor\\bin\\phpunit --configuration=backend\\phpunit.xml backend\\tests\\Feature\\PageRouteTableTest.php backend\\tests\\Feature\\RoleNavbarContractTest.php
\`\`\`

Expected: FAIL only on missing activity markers/list entries.

- [ ] **Step 3: Implement the positive parity assertions and re-run them.**

Run the same commands. Expected: all selected tests pass.

- [ ] **Step 4: Run every documented local CI gate.**

\`\`\`text
npm test
C:\\xampp\\php\\php.exe backend\\vendor\\bin\\phpunit --configuration=backend\\phpunit.xml
C:\\xampp\\php\\php.exe backend\\vendor\\bin\\phpstan analyse --configuration=backend/phpstan.neon
\`\`\`

Expected: all commands exit 0 with no failures, errors, or PHPStan findings.

- [ ] **Step 5: Inspect scope and request review.**

\`\`\`text
git diff --check HEAD~5..HEAD
git status --short
git diff --stat HEAD~5..HEAD
\`\`\`

Confirm only the activity feature and its tests/docs changed, then use
\`superpowers:requesting-code-review\` with the first implementation commit as
\`BASE_SHA\` and the final implementation commit as \`HEAD_SHA\`. Resolve all
Critical and Important findings and rerun the affected tests and complete gate.
