# Borrower Approval Notification Panel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Persist one borrower-scoped browser notification when staff approves a student or teacher online borrowing transaction, then expose it in a shared borrower notification panel.

**Architecture:** Reuse the existing notifications table and CirculationNotificationRepositoryInterface. PdoStaffRepository will load approval context before changing statuses and insert the borrower notification on the same PDO transaction; the existing pending guard will make the operation idempotent. BorrowerController and PdoBorrowerPortalRepository will expose user-scoped read/read-all actions, while frontend/assets/js/core/app-navbar.js will add the panel only for student and teacher navigation.

**Tech Stack:** PHP 8.2+, PDO, SQLite-backed PHPUnit tests, MySQL migration SQL, browser JavaScript, shared CSS, and Node's built-in test runner.

## Global Constraints

- The feature applies to student and teacher online borrowing approvals, including bulk approvals.
- Staff approval creates one notification per approved borrowing transaction.
- Bulk messages list all distinct approved book titles.
- Rejected requests remain out of scope.
- The recommendation shelf and Recent Activity panel remain unchanged.
- Fresh schema changes belong in sql/install.sql; existing databases use sql/upgrade_borrower_approval_notifications.sql.
- Approval status changes and borrower notification insertion must use the same database transaction.
- Every borrower notification query and update must include the authenticated user ID.
- The browser panel must escape notification text and leave staff and guest navigation unchanged.
- No service worker, OS push permission, email, SMS, rejection notification, or recommendation-ranking change is allowed.
- Preserve unrelated dirty-worktree changes and stage only files belonging to this feature.

---

## File Map

### Approval persistence and schema

- Modify sql/install.sql: add borrow_approved to the existing notifications.type enum.
- Create sql/upgrade_borrower_approval_notifications.sql: rerunnable enum migration for existing databases.
- Modify backend/src/Infrastructure/Persistence/PdoStaffRepository.php: inject the optional circulation notification repository, collect normalized/legacy approval context, and notify exactly once for a successful approval.
- Modify backend/src/Bootstrap/ApplicationFactory.php: provide one PdoCirculationNotificationRepository backed by the same PDO connection to PdoStaffRepository.
- Modify backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php: cover normalized single-title, normalized bulk, legacy approval, and idempotency notification behavior.
- Modify backend/tests/Feature/SchemaContractTest.php: require the new migration and enum marker.

### Borrower API

- Modify backend/src/Infrastructure/Persistence/BorrowerPortalRepositoryInterface.php: define notification listing and user-scoped read actions.
- Modify backend/src/Infrastructure/Persistence/PdoBorrowerPortalRepository.php: return the latest 20 notifications and unread count, and scope both update operations by user.
- Modify backend/src/Http/Controllers/BorrowerController.php: add GET and POST notification handlers using existing borrower authentication, CSRF, and JSON conventions.
- Modify backend/src/Http/Routing/BorrowerRouteTable.php: add student and teacher GET/POST notification routes.
- Modify backend/tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php: cover current-user filtering, latest-20 ordering, unread count, read-one, and read-all isolation.
- Create backend/tests/Feature/BorrowerNotificationContractTest.php: cover controller response shape, CSRF, invalid IDs, and role rejection.
- Modify backend/tests/Feature/BorrowerBulkBorrowingTest.php: keep its anonymous portal fixture compatible with the expanded interface.

### Shared borrower panel

- Modify frontend/assets/js/core/app-navbar.js: create a borrower-only notification bell and panel, fetch on open, render escaped rows, mark one row read, mark all read, and clean up listeners.
- Modify frontend/assets/css/style.css: add only the notification button, unread badge, panel, row, and empty-state styles required by the new markup.
- Create frontend/tests/borrower-notifications.test.js: verify both role endpoints, panel markup/controls, escaped rendering, read actions, and no borrower controls for staff/guest roles.
- Modify frontend/tests/navbar-cache.test.js only if the fake DOM needs shared support for the new topbar control.

---

### Task 1: Persist approval notifications atomically

**Files:**
- Create: sql/upgrade_borrower_approval_notifications.sql
- Modify: sql/install.sql
- Modify: backend/src/Infrastructure/Persistence/PdoStaffRepository.php:13-22, 857-946
- Modify: backend/src/Bootstrap/ApplicationFactory.php:178-233
- Test: backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php:132-210
- Test: backend/tests/Feature/SchemaContractTest.php

**Interfaces:**
- Consumes: CirculationNotificationRepositoryInterface::notifyBorrower(int $userId, string $type, string $title, string $message, int $relatedId): void.
- Produces: PdoStaffRepository::__construct(PDO $pdo, ?AuditEventRepositoryInterface $audit = null, ?CirculationNotificationRepositoryInterface $notifications = null) and one borrow_approved notification per successful approved transaction.

- [ ] **Step 1: Extend schema contract tests first.** Add a test requiring borrow_approved in sql/install.sql, the new migration file, MODIFY COLUMN type, and an information-schema guard.

~~~php
public function testBorrowerApprovalNotificationSchemaIsAvailable(): void
{
    $install = $this->readSql('install.sql');
    self::assertStringContainsString("'borrow_approved'", $install);

    $migration = $this->readSql('upgrade_borrower_approval_notifications.sql');
    self::assertStringContainsString('MODIFY COLUMN', $migration);
    self::assertStringContainsString('information_schema.COLUMNS', $migration);
    self::assertStringContainsString("'borrow_approved'", $migration);
}
~~~

- [ ] **Step 2: Run the new test and verify it fails.**

~~~powershell
php vendor/bin/phpunit tests/Feature/SchemaContractTest.php --filter BorrowerApprovalNotificationSchemaIsAvailable
~~~

Expected: FAIL because the migration does not exist and install.sql does not contain borrow_approved.

- [ ] **Step 3: Add the fresh-install enum value and rerunnable existing-database migration.** Retain all current enum values and append borrow_approved in sql/install.sql. Create the migration below; the dynamic statement is a no-op when the value already exists.

~~~sql
-- Scan2Borrow: borrower approval notification enum upgrade
USE scan2borrow_2.0;

SET @notification_type_column = (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'notifications'
      AND COLUMN_NAME = 'type'
    LIMIT 1
);

SET @notification_type_sql = IF(
    @notification_type_column IS NULL
    OR LOCATE('''borrow_approved''', @notification_type_column) > 0,
    'SELECT 1',
    'ALTER TABLE notifications MODIFY COLUMN type ENUM(''borrow_request'',''overdue_alert'',''return_alert'',''hold_available'',''renewal_approved'',''renewal_rejected'',''borrow_approved'') NOT NULL DEFAULT ''borrow_request'''
);

PREPARE notification_type_statement FROM @notification_type_sql;
EXECUTE notification_type_statement;
DEALLOCATE PREPARE notification_type_statement;
~~~

- [ ] **Step 4: Add failing PdoStaffRepository tests.** Use PdoCirculationNotificationRepository with the same SQLite PDO. Extend normalizedApprovalFixture with a second title/copy/item for the bulk fixture.

~~~php
public function testNormalizedApprovalCreatesOneBorrowerNotification(): void
{
    $pdo = $this->normalizedApprovalFixture();
    $repository = new PdoStaffRepository($pdo, null, new PdoCirculationNotificationRepository($pdo));

    $repository->approveBorrowing(1, 99);

    $row = $pdo->query('SELECT user_id, type, title, message, related_id, is_read FROM notifications WHERE user_id = 2')->fetch(PDO::FETCH_ASSOC);
    self::assertSame(2, (int) $row['user_id']);
    self::assertSame('borrow_approved', $row['type']);
    self::assertSame('Borrowing request approved', $row['title']);
    self::assertStringContainsString('"Clean Code"', $row['message']);
    self::assertSame(1, (int) $row['related_id']);
    self::assertSame(0, (int) $row['is_read']);
}

public function testNormalizedBulkApprovalListsDistinctTitlesInOneNotification(): void
{
    $pdo = $this->normalizedBulkApprovalFixture();
    $repository = new PdoStaffRepository($pdo, null, new PdoCirculationNotificationRepository($pdo));

    $repository->approveBorrowing(1, 99);

    $rows = $pdo->query("SELECT message FROM notifications WHERE user_id = 2 AND type = 'borrow_approved'")->fetchAll(PDO::FETCH_COLUMN);
    self::assertCount(1, $rows);
    self::assertStringContainsString('"Clean Code"', $rows[0]);
    self::assertStringContainsString('"Refactoring"', $rows[0]);
}

public function testLegacyTeacherApprovalCreatesTheSameNotificationType(): void
{
    $this->pdo->exec("UPDATE users SET role = 'teacher' WHERE id = 2");
    $repository = new PdoStaffRepository($this->pdo, null, new PdoCirculationNotificationRepository($this->pdo));

    $repository->approveBorrowing(1, 1);

    self::assertSame('borrow_approved', $this->pdo->query('SELECT type FROM notifications WHERE user_id = 2')->fetchColumn());
}

public function testRepeatedApprovalDoesNotDuplicateBorrowerNotification(): void
{
    $pdo = $this->normalizedApprovalFixture();
    $repository = new PdoStaffRepository($pdo, null, new PdoCirculationNotificationRepository($pdo));

    $repository->approveBorrowing(1, 99);
    $repository->approveBorrowing(1, 99);

    self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'borrow_approved'")->fetchColumn());
}
~~~

Expected first run: FAIL because the constructor does not accept the notification dependency and no approval notification is inserted.

- [ ] **Step 5: Run the focused repository tests to capture the failure.**

~~~powershell
php vendor/bin/phpunit tests/Unit/Infrastructure/PdoStaffRepositoryTest.php --filter 'Approval|Notification'
~~~

Expected: the new notification assertions fail while the pre-existing approval assertions still pass.

- [ ] **Step 6: Implement atomic notification creation in both approval paths.** Add the optional dependency and a helper with this behavior:

~~~php
public function __construct(
    private readonly PDO $pdo,
    private readonly ?AuditEventRepositoryInterface $audit = null,
    private readonly ?CirculationNotificationRepositoryInterface $notifications = null,
) {
}

private function notifyApprovedBorrower(array $copies, int $borrowingId): void
{
    if ($this->notifications === null) {
        return;
    }

    $userId = 0;
    $titles = [];
    foreach ($copies as $copy) {
        $candidateUserId = $copy['user_id'] ?? null;
        if ($userId < 1 && is_numeric($candidateUserId)) {
            $userId = (int) $candidateUserId;
        }
        $title = $this->string($copy['title'] ?? null);
        if ($title !== '') {
            $titles[] = $title;
        }
    }
    $titles = array_values(array_unique($titles));
    if ($userId < 1 || $titles === []) {
        return;
    }

    $quotedTitles = array_map(static fn (string $title): string => '"' . $title . '"', $titles);
    $this->notifications->notifyBorrower(
        $userId,
        'borrow_approved',
        'Borrowing request approved',
        'Your borrowing request was approved. You borrowed: ' . implode(', ', $quotedTitles) . '.',
        $borrowingId,
    );
}
~~~

The normalized pre-status query must join borrowing_transactions and select bt.user_id. The legacy pre-status query must run before the update:

~~~sql
SELECT b.user_id, books.title
FROM borrowing b
JOIN books ON books.id = b.book_id
WHERE b.id = :id
LIMIT 1
~~~

The legacy update must include AND approval_status = 'pending'; a rowCount below one commits the no-op and returns before notifying. The normalized path passes fetched copies to the helper after the existing staff notification update. The legacy path passes its one-row approval context after updating books and marking the staff notification read. Call the helper only for approval, never rejection, and before commit. Any notification insert exception must reach the existing catch/rollback path.

- [ ] **Step 7: Wire the shared PDO repository and rerun focused tests.**

~~~php
$staffRepository = new \App\Infrastructure\Persistence\PdoStaffRepository(
    $pdo,
    $auditRepository,
    new \App\Infrastructure\Persistence\PdoCirculationNotificationRepository($pdo),
);
~~~

Run:

~~~powershell
php vendor/bin/phpunit tests/Unit/Infrastructure/PdoStaffRepositoryTest.php --filter 'Approval|Notification'
php vendor/bin/phpunit tests/Feature/SchemaContractTest.php --filter BorrowerApprovalNotificationSchemaIsAvailable
~~~

Expected: PASS for focused approval and schema tests.

- [ ] **Step 8: Commit the atomic backend/schema slice.**

~~~powershell
git add sql/install.sql sql/upgrade_borrower_approval_notifications.sql backend/src/Infrastructure/Persistence/PdoStaffRepository.php backend/src/Bootstrap/ApplicationFactory.php backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php backend/tests/Feature/SchemaContractTest.php
git commit -m "feat: notify borrowers after approval"
~~~

---

### Task 2: Add borrower-scoped notification API

**Files:**
- Modify: backend/src/Infrastructure/Persistence/BorrowerPortalRepositoryInterface.php
- Modify: backend/src/Infrastructure/Persistence/PdoBorrowerPortalRepository.php
- Modify: backend/src/Http/Controllers/BorrowerController.php
- Modify: backend/src/Http/Routing/BorrowerRouteTable.php
- Test: backend/tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php
- Test: backend/tests/Feature/BorrowerNotificationContractTest.php
- Modify: backend/tests/Feature/BorrowerBulkBorrowingTest.php

**Interfaces:**
- Consumes: notifications rows written by Task 1.
- Produces: notifications(int userId): array, markNotificationRead(int userId, int notificationId): void, and markAllNotificationsRead(int userId): void; GET data shape {notifications: list, unread_count: int}; POST actions read and read_all.

- [ ] **Step 1: Add failing repository tests for filtering, count, ordering, and scoped mutations.** Add a notifications table to the portal fixture, insert rows for users 1 and 2, and assert only user 1's latest 20 rows are returned. Assert user 1 cannot mark user 2's row read and markAllNotificationsRead(1) leaves user 2 unread.

~~~php
public function testNotificationsAreLatestTwentyAndScopedToTheBorrower(): void
{
    $this->pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INTEGER, type TEXT, title TEXT, message TEXT, related_id INTEGER, is_read INTEGER, created_at TEXT)');
    $this->pdo->exec("INSERT INTO notifications VALUES
        (1, 1, 'borrow_approved', 'Borrowing request approved', 'Clean Code', 44, 0, '2026-11-10 12:00:00'),
        (2, 1, 'renewal_approved', 'Renewal approved', 'Refactoring', 45, 1, '2026-11-09 12:00:00'),
        (3, 2, 'borrow_approved', 'Borrowing request approved', 'Other account', 46, 0, '2026-11-11 12:00:00')");

    $result = (new PdoBorrowerPortalRepository($this->pdo))->notifications(1);

    self::assertSame(1, $result['unread_count']);
    self::assertCount(2, $result['notifications']);
    self::assertSame(1, $result['notifications'][0]['id']);
    self::assertNotContains('Other account', array_column($result['notifications'], 'message'));
}

public function testNotificationReadActionsCannotChangeAnotherBorrower(): void
{
    $this->pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INTEGER, type TEXT, title TEXT, message TEXT, related_id INTEGER, is_read INTEGER, created_at TEXT)');
    $this->pdo->exec("INSERT INTO notifications VALUES (1, 1, 'borrow_approved', 'Approved', 'Mine', 1, 0, '2026-11-10'), (2, 2, 'borrow_approved', 'Approved', 'Other', 2, 0, '2026-11-10')");
    $repository = new PdoBorrowerPortalRepository($this->pdo);

    $repository->markNotificationRead(1, 2);
    self::assertSame(0, (int) $this->pdo->query('SELECT is_read FROM notifications WHERE id = 2')->fetchColumn());

    $repository->markAllNotificationsRead(1);
    self::assertSame(1, (int) $this->pdo->query('SELECT is_read FROM notifications WHERE id = 1')->fetchColumn());
    self::assertSame(0, (int) $this->pdo->query('SELECT is_read FROM notifications WHERE id = 2')->fetchColumn());
}
~~~

- [ ] **Step 2: Run the focused repository tests and verify they fail.**

~~~powershell
php vendor/bin/phpunit tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php --filter Notification
~~~

Expected: FAIL because the methods do not yet exist.

- [ ] **Step 3: Define and implement the user-scoped repository methods.**

~~~php
/** @return array{notifications: list<array<string, mixed>>, unread_count: int} */
public function notifications(int $userId): array;

public function markNotificationRead(int $userId, int $notificationId): void;

public function markAllNotificationsRead(int $userId): void;
~~~

~~~php
public function notifications(int $userId): array
{
    $statement = $this->pdo->prepare(
        'SELECT id, type, title, message, related_id, is_read, created_at
         FROM notifications
         WHERE user_id = :user_id
         ORDER BY created_at DESC, id DESC
         LIMIT 20'
    );
    $statement->execute(['user_id' => $userId]);
    $rows = array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['related_id'] = $row['related_id'] === null ? null : (int) $row['related_id'];
        $row['is_read'] = (int) $row['is_read'];
        return $row;
    }, $statement->fetchAll(PDO::FETCH_ASSOC));

    $unread = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0');
    $unread->execute(['user_id' => $userId]);

    return ['notifications' => $rows, 'unread_count' => (int) $unread->fetchColumn()];
}

public function markNotificationRead(int $userId, int $notificationId): void
{
    $statement = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id');
    $statement->execute(['id' => $notificationId, 'user_id' => $userId]);
}

public function markAllNotificationsRead(int $userId): void
{
    $statement = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0');
    $statement->execute(['user_id' => $userId]);
}
~~~

- [ ] **Step 4: Add failing controller and route contract tests.** Assert both role routes exist, GET returns the nested data shape, POST read validates a positive integer and CSRF, POST read_all calls the user-scoped repository method, and a non-student/teacher session receives 401. Follow BorrowerActivityContractTest's session fixture.

~~~php
self::assertContains('/api/student/notifications', $pathsForGet);
self::assertContains('/api/teacher/notifications', $pathsForGet);
self::assertContains('/api/student/notifications', $pathsForPost);
self::assertContains('/api/teacher/notifications', $pathsForPost);

$portal->expects(self::once())->method('notifications')->with(7)->willReturn([
    'notifications' => [[
        'id' => 12,
        'type' => 'borrow_approved',
        'title' => 'Borrowing request approved',
        'message' => 'Your borrowing request was approved. You borrowed: "Clean Code".',
        'related_id' => 44,
        'is_read' => 0,
        'created_at' => '2026-11-10 12:00:00',
    ]],
    'unread_count' => 1,
]);
$response = $controller->notifications($this->request('GET', []));
self::assertSame(200, $response->status());
self::assertSame(1, $response->payload()['data']['unread_count']);
~~~

- [ ] **Step 5: Run the controller contract test and verify the missing endpoints fail.**

~~~powershell
php vendor/bin/phpunit tests/Feature/BorrowerNotificationContractTest.php
~~~

Expected: FAIL with missing controller methods or route entries.

- [ ] **Step 6: Implement controller handlers and routes.** notifications() calls borrower() and returns {ok: true, data: ...}. notificationAction() requires a valid CSRF token, accepts only read with a positive notification_id or read_all, returns 422 for any other action/ID, and returns the refreshed user-scoped payload.

~~~php
Route::create('GET', '/api/student/notifications', [$controller, 'notifications']),
Route::create('GET', '/api/teacher/notifications', [$controller, 'notifications']),
Route::create('POST', '/api/student/notifications', [$controller, 'notificationAction']),
Route::create('POST', '/api/teacher/notifications', [$controller, 'notificationAction']),
~~~

~~~php
public function notifications(ServerRequest $request): JsonResponse
{
    $identity = $this->borrower();
    if ($identity === null) {
        return $this->unauthorized();
    }

    return new JsonResponse(200, ['ok' => true, 'data' => $this->portal->notifications($identity->userId())]);
}

public function notificationAction(ServerRequest $request): JsonResponse
{
    $identity = $this->borrower();
    if ($identity === null) {
        return $this->unauthorized();
    }
    try {
        $this->csrf->assertValid($this->value($request->body(), 'csrf'));
    } catch (InvalidArgumentException $exception) {
        return new JsonResponse(419, ['ok' => false, 'errors' => [$exception->getMessage()]]);
    }

    $action = $this->value($request->body(), 'action');
    if ($action === 'read') {
        $notificationId = $this->positiveInt($request->body()['notification_id'] ?? null);
        if ($notificationId < 1) {
            return new JsonResponse(422, ['ok' => false, 'errors' => ['A valid notification ID is required.']]);
        }
        $this->portal->markNotificationRead($identity->userId(), $notificationId);
    } elseif ($action === 'read_all') {
        $this->portal->markAllNotificationsRead($identity->userId());
    } else {
        return new JsonResponse(422, ['ok' => false, 'errors' => ['Unsupported notification action.']]);
    }

    return new JsonResponse(200, ['ok' => true, 'data' => $this->portal->notifications($identity->userId())]);
}
~~~

- [ ] **Step 7: Update concrete test doubles and run focused API tests.** Add these methods to BulkBorrowingPortalRepository and every concrete implementation found by rg "implements BorrowerPortalRepositoryInterface".

~~~php
/** @return array{notifications: list<array<string, mixed>>, unread_count: int} */
public function notifications(int $userId): array { return ['notifications' => [], 'unread_count' => 0]; }
public function markNotificationRead(int $userId, int $notificationId): void {}
public function markAllNotificationsRead(int $userId): void {}
~~~

Run:

~~~powershell
php vendor/bin/phpunit tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php --filter Notification
php vendor/bin/phpunit tests/Feature/BorrowerNotificationContractTest.php
php vendor/bin/phpunit tests/Feature/BorrowerBulkBorrowingTest.php tests/Feature/BorrowerActivityContractTest.php
~~~

Expected: PASS for notification repository/controller contracts and unchanged borrower activity/bulk contracts.

- [ ] **Step 8: Commit the borrower API slice.**

~~~powershell
git add backend/src/Infrastructure/Persistence/BorrowerPortalRepositoryInterface.php backend/src/Infrastructure/Persistence/PdoBorrowerPortalRepository.php backend/src/Http/Controllers/BorrowerController.php backend/src/Http/Routing/BorrowerRouteTable.php backend/tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php backend/tests/Feature/BorrowerNotificationContractTest.php backend/tests/Feature/BorrowerBulkBorrowingTest.php
git commit -m "feat: expose borrower notifications"
~~~

---

### Task 3: Render the persistent borrower notification panel

**Files:**
- Create: frontend/tests/borrower-notifications.test.js
- Modify: frontend/assets/js/core/app-navbar.js
- Modify: frontend/assets/css/style.css
- Modify: frontend/tests/navbar-cache.test.js only for required fake-DOM behavior

**Interfaces:**
- Consumes: role-specific GET and POST notification endpoints with csrf, action, and optional notification_id.
- Produces: borrower-only .borrower-notifications topbar markup with bell button, unread badge, panel, message rows, empty state, and mark-all control.

- [ ] **Step 1: Write frontend contract tests first.** Load the legacy navbar script through the existing VM helper. Extend the fake DOM only enough to query new topbar controls and dispatch clicks. Assert student and teacher use their own endpoints, markup contains badge/empty/mark-all controls, message text is inserted through textContent rather than HTML, and admin/librarian/guest renders do not add borrower controls.

~~~js
test('borrower navbar uses the role-specific notification endpoint and controls', async () => {
  const fixture = createNavbarFixture('/scan2borrow/student/dashboard', 'student');
  const calls = [];
  fixture.window.fetch = async (url, options = {}) => {
    calls.push({ url, options });
    return { json: async () => ({ ok: true, data: { notifications: [], unread_count: 0 } }) };
  };
  const AppNavbar = loadNavbar(fixture.window, fixture.document);
  const navbar = new AppNavbar(fixture.root);

  navbar.render('student');
  navbar.setupBorrowerNotifications('student');
  await navbar.openNotifications();

  assert.equal(calls[0].url, '/scan2borrow/api/student/notifications');
  assert.match(fixture.topbar.innerHTML, /borrower-notifications/);
  assert.match(fixture.topbar.innerHTML, /Mark all as read/);
  assert.match(fixture.topbar.innerHTML, /No notifications yet/);
});

test('staff and guest navbar renders do not include borrower notification controls', () => {
  for (const role of ['admin', 'librarian', 'guest']) {
    const fixture = createNavbarFixture('/scan2borrow/staff/dashboard', role);
    const AppNavbar = loadNavbar(fixture.window, fixture.document);
    const navbar = new AppNavbar(fixture.root);
    navbar.render(role);
    navbar.setupBorrowerNotifications(role);
    assert.doesNotMatch(fixture.topbar.innerHTML, /borrower-notifications/);
  }
});
~~~

Add an escaped-text assertion with message <img src=x onerror=alert(1)>; assert row.textContent equals that literal and no message is assigned through row.innerHTML.

- [ ] **Step 2: Run the focused frontend test and verify it fails.**

~~~powershell
node --test frontend/tests/borrower-notifications.test.js
~~~

Expected: FAIL because AppNavbar has no borrower notification setup or controls.

- [ ] **Step 3: Implement borrower-only panel creation and lifecycle in app-navbar.js.** Call setupBorrowerNotifications(role) from start() after borrower rendering and responsive controls. Return for non-borrower roles or missing topbar, avoid duplicate mounts, read the CSRF meta tag, and use DOM textContent for title/message/time values.

~~~js
setupBorrowerNotifications(role) {
  if (!['student', 'teacher'].includes(role) || this.notificationRoot) return;
  const app = this.root.closest?.('.app');
  const topbar = app?.querySelector?.('.topbar') || this.document?.querySelector?.('.topbar');
  if (!topbar || !this.document?.createElement) return;

  this.notificationPath = '/scan2borrow/api/' + role + '/notifications';
  this.notificationCsrf = this.document.querySelector?.('meta[name="csrf"]')?.content || '';
  const wrapper = this.document.createElement('div');
  wrapper.className = 'borrower-notifications';
  wrapper.innerHTML = [
    '<button type="button" class="borrower-notifications__toggle" aria-expanded="false" aria-controls="borrower-notifications-panel">',
    '<span aria-hidden="true">&#128276;</span><span class="borrower-notifications__label">Notifications</span>',
    '<span class="borrower-notifications__badge" hidden>0</span></button>',
    '<section id="borrower-notifications-panel" class="borrower-notifications__panel" hidden aria-label="Notifications">',
    '<div class="borrower-notifications__header"><strong>Notifications</strong>',
    '<button type="button" data-notification-action="read-all">Mark all as read</button></div>',
    '<div class="borrower-notifications__list"></div></section>',
  ].join('');
  topbar.appendChild(wrapper);
  this.notificationRoot = wrapper;
  this.notificationToggle = wrapper.querySelector('.borrower-notifications__toggle');
  this.notificationPanel = wrapper.querySelector('.borrower-notifications__panel');
  this.notificationBadge = wrapper.querySelector('.borrower-notifications__badge');
  this.notificationList = wrapper.querySelector('.borrower-notifications__list');
  this.handleNotificationToggle = () => this.toggleNotifications();
  this.handleNotificationClick = (event) => this.handleNotificationAction(event);
  this.notificationToggle.addEventListener('click', this.handleNotificationToggle);
  wrapper.addEventListener('click', this.handleNotificationClick);
}

async openNotifications() {
  if (!this.notificationPanel) return;
  this.notificationPanel.hidden = false;
  this.notificationToggle?.setAttribute('aria-expanded', 'true');
  await this.loadNotifications();
}
~~~

Implement loadNotifications() with Accept: application/json, renderNotifications(data), and an empty fallback. Implement markNotificationRead(id) and markAllNotificationsRead() with URLSearchParams containing csrf, then refresh. renderNotifications() creates each row and sets title/message/created_at with textContent, sets data-notification-id, and sets badge text/hidden from unread_count. Remove listeners and clear notificationRoot in destroy().

- [ ] **Step 4: Add shared visual styles without changing dashboard/recommendation/activity markup.**

~~~css
.borrower-notifications { position: relative; }
.borrower-notifications__toggle { align-items: center; background: transparent; border: 1px solid var(--border); border-radius: 999px; color: var(--navy); display: inline-flex; gap: 7px; padding: 8px 11px; }
.borrower-notifications__badge { background: var(--danger); border-radius: 999px; color: #fff; font-size: .68rem; font-weight: 800; min-width: 18px; padding: 2px 5px; text-align: center; }
.borrower-notifications__panel { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-lg); max-width: min(380px, calc(100vw - 28px)); position: absolute; right: 0; top: calc(100% + 10px); width: 380px; z-index: 30; }
.borrower-notifications__header { align-items: center; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; padding: 13px 15px; }
.borrower-notifications__header button { background: none; border: 0; color: var(--primary); cursor: pointer; font-size: .74rem; font-weight: 700; }
.borrower-notifications__list { max-height: 360px; overflow-y: auto; padding: 5px; }
.borrower-notification { background: #fff; border: 0; border-bottom: 1px solid var(--border); display: block; padding: 12px 10px; text-align: left; width: 100%; }
.borrower-notification.is-unread { background: var(--sky); }
.borrower-notification__title { color: var(--navy); display: block; font-size: .82rem; font-weight: 750; }
.borrower-notification__message { color: var(--text); display: block; font-size: .78rem; line-height: 1.4; margin-top: 4px; }
.borrower-notification__time { color: var(--muted); display: block; font-size: .68rem; margin-top: 6px; }
.borrower-notifications__empty { color: var(--muted); font-size: .82rem; padding: 28px 18px; text-align: center; }
@media (max-width: 576px) {
    .borrower-notifications__label { display: none; }
    .borrower-notifications__panel { right: -8px; }
}
~~~

- [ ] **Step 5: Run targeted and full frontend tests.**

~~~powershell
node --test frontend/tests/borrower-notifications.test.js frontend/tests/navbar-cache.test.js
npm test
~~~

Expected: new panel tests and existing navbar tests pass; the full frontend suite reports no new failures. Recommendation and Recent Activity tests remain unchanged and passing.

- [ ] **Step 6: Commit the shared frontend slice.**

~~~powershell
git add frontend/assets/js/core/app-navbar.js frontend/assets/css/style.css frontend/tests/borrower-notifications.test.js frontend/tests/navbar-cache.test.js
git commit -m "feat: add borrower notification panel"
~~~

---

### Task 4: Run repository quality gates and finish safely

**Files:**
- No implementation files; verification and Git state only.

**Interfaces:**
- Consumes: committed Task 1-3 slices.
- Produces: fresh local verification evidence and a feature-only commit set on master.

- [ ] **Step 1: Inspect diff state and confirm unrelated changes are untouched.**

~~~powershell
git status --short
git diff --check HEAD~2..HEAD
git diff --stat HEAD~2..HEAD
~~~

Expected: only approval notification schema/backend/API/frontend files appear in feature commits; existing frontend/features/staff/services/index.js, frontend/tests/scanner.test.js, EXAMPLE.jpg, and unrelated docs/uploads remain unstaged or original.

- [ ] **Step 2: Run all affected backend tests.**

~~~powershell
Set-Location backend
php vendor/bin/phpunit tests/Unit/Infrastructure/PdoStaffRepositoryTest.php tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php tests/Feature/BorrowerNotificationContractTest.php tests/Feature/BorrowerBulkBorrowingTest.php tests/Feature/BorrowerActivityContractTest.php tests/Feature/SchemaContractTest.php
~~~

Expected: affected tests pass. If an existing unrelated failure appears, record its first failing test and do not weaken or skip it.

- [ ] **Step 3: Run documented backend quality and frontend suites.**

~~~powershell
Set-Location backend
composer run quality
Set-Location ..
npm test
~~~

Expected: new tests remain green. Known unrelated baseline PHPUnit/PHPStan failures, if still present, are reported with fresh counts and first failures separately; no CI lane is weakened or suppressed.

- [ ] **Step 4: Review final diff for scope, security, and behavior.** Confirm all borrower SQL has user_id = :user_id, POST requires CSRF, notifications are inserted before approval commit, duplicate approval is guarded, dynamic text uses DOM textContent, and staff/guest navbar output has no borrower controls.

- [ ] **Step 5: Push only after local verification and report exact remote state.**

~~~powershell
git log --oneline -4
git status --short
git push origin master
~~~

Expected: feature commits plus the approved design/plan history are pushed to origin/master; inspect the resulting GitHub Actions run when available, otherwise state remote CI is unverified.

