# Borrower Contact Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Send idempotent SMS and email notifications after borrowing requests and approvals for students, teachers, and guests, while preserving successful borrowing when external delivery fails.

**Status:** Implementation complete; local verification recorded, remote CI pending.

**Architecture:** Add a small application notification port and DTO, a PDO-backed delivery repository, and one delivery service that resolves registered-user or visitor contacts and records each channel attempt. Borrowing and approval flows call the port only after their persistence operation succeeds; the service records provider failures instead of throwing them back into circulation.

**Tech Stack:** PHP 8.2+, framework-free application services, PDO/MySQL with SQLite test fixtures, PHPUnit 11, PHPStan level 9, existing SMTP and TextBee SMS sender adapters.

## Global Constraints

- Work inline on the existing `master` branch; do not create a worktree.
- Preserve unrelated dirty-worktree files and stage only files belonging to this feature.
- Keep existing tests and quality gates intact; do not skip, weaken, or focus tests.
- External email/SMS failures must not roll back a committed borrowing or approval.
- Existing known test failures are baseline and must be reported separately from new failures.
- Use the existing `EmailSenderInterface`, `SmsSenderInterface`, `SmtpEmailSender`, and `SmsSenderFactory`.
- Add the table to both `sql/install.sql` and the forward-compatible `sql/upgrade.sql` path used by this repository.
- Run targeted tests first and the complete affected backend quality lane before completion.

---

## File map

### New application contracts and service

- Create: `backend/src/Application/DTO/BorrowerContactNotification.php` — immutable event payload containing recipient, event, reference, titles, and due date.
- Create: `backend/src/Application/Services/BorrowerContactNotificationInterface.php` — application port used by borrowing/approval flows.
- Create: `backend/src/Application/Services/BorrowerContactNotificationService.php` — message rendering, channel selection, delivery attempts, and failure isolation.

### New persistence contract and adapter

- Create: `backend/src/Infrastructure/Persistence/ContactNotificationDeliveryRepositoryInterface.php` — recipient lookup and idempotent delivery-state operations.
- Create: `backend/src/Infrastructure/Persistence/PdoContactNotificationDeliveryRepository.php` — PDO implementation for `users`, `visitors`, and `contact_notification_deliveries`.

### Existing application integrations

- Modify: `backend/src/Application/Services/BorrowingService.php` — dispatch registered-user request/approved events after single and bulk creation.
- Modify: `backend/src/Infrastructure/Persistence/BorrowingRepositoryInterface.php` — expose grouped bulk titles in the creation result.
- Modify: `backend/src/Infrastructure/Persistence/PdoBorrowingRepository.php` — return grouped title names from bulk creation.
- Modify: `backend/src/Application/Services/GuestBorrowingService.php` — dispatch guest request events after creation.
- Modify: `backend/src/Infrastructure/Persistence/GuestBorrowingRepositoryInterface.php` — return guest title/due-date creation context.
- Modify: `backend/src/Infrastructure/Persistence/PdoGuestBorrowingRepository.php` — return the inserted guest borrowing context.
- Modify: `backend/src/Application/Services/GuestApprovalService.php` — dispatch guest approval events after repository approval.
- Modify: `backend/src/Infrastructure/Persistence/PdoStaffRepository.php` — dispatch registered-user approval events after commit, using the existing approval context.
- Modify: `backend/src/Application/StaffCirculation/StaffCirculationService.php` — dispatch direct staff-desk approved events after commit.
- Modify: `backend/src/Bootstrap/ApplicationFactory.php` — construct one shared delivery repository/service and inject the port into all relevant flows.
- Modify: `sql/install.sql` — create the unified delivery table on fresh installs.
- Modify: `sql/upgrade.sql` — create the unified delivery table for existing installations.

### Tests

- Create: `backend/tests/Unit/Mail/BorrowerContactNotificationServiceTest.php` — channel selection, rendering, failures, and idempotency.
- Create: `backend/tests/Unit/Infrastructure/PdoContactNotificationDeliveryRepositoryTest.php` — recipient lookup, delivery rows, and unique-event behavior.
- Create: `backend/tests/Feature/ContactNotificationSchemaContractTest.php` — install/upgrade SQL contracts.
- Modify: `backend/tests/Unit/Borrowing/BorrowingServiceTest.php` — single registered-user request/approved dispatch.
- Modify: `backend/tests/Unit/Borrowing/BulkBorrowingServiceTest.php` — grouped bulk titles and dispatch.
- Modify: `backend/tests/Unit/GuestBorrowingServiceTest.php` — guest request dispatch.
- Modify: `backend/tests/Unit/GuestApprovalServiceTest.php` — guest approval dispatch.
- Modify: `backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php` — post-commit registered-user approval dispatch.
- Modify: `backend/tests/Unit/StaffCirculation/StaffCirculationServiceTest.php` — direct-borrow approved dispatch.
- Modify: `backend/tests/Unit/Infrastructure/PdoStaffCirculationRepositoryTest.php` only if the returned direct-borrow payload contract needs a regression assertion.

---

## Task 1: Add the schema and persistence contract

**Files:**
- Create: `backend/src/Infrastructure/Persistence/ContactNotificationDeliveryRepositoryInterface.php`
- Create: `backend/src/Infrastructure/Persistence/PdoContactNotificationDeliveryRepository.php`
- Create: `backend/tests/Unit/Infrastructure/PdoContactNotificationDeliveryRepositoryTest.php`
- Create: `backend/tests/Feature/ContactNotificationSchemaContractTest.php`
- Modify: `sql/install.sql`
- Modify: `sql/upgrade.sql`

**Interfaces:**

The repository must expose these exact operations:

```php
interface ContactNotificationDeliveryRepositoryInterface
{
    /** @return array{name: string, email: ?string, phone: ?string}|null */
    public function findRecipient(string $recipientType, int $recipientId): ?array;

    /**
     * Return the newly inserted delivery ID, or null when the unique event/channel row already exists.
     */
    public function createDelivery(
        string $recipientType,
        int $recipientId,
        string $eventType,
        string $borrowingReference,
        string $channel,
        ?string $destination,
        ?string $subject,
        string $message,
        string $status,
        ?string $lastError = null,
    ): ?int;

    public function markSent(int $deliveryId, DateTimeImmutable $sentAt): void;

    public function markFailed(int $deliveryId, string $error): void;
}
```

- [ ] **Step 1: Write the failing schema contract test.** Assert both SQL files contain `CREATE TABLE` for `contact_notification_deliveries`, the two event values, both channel values, the four status values, and the uniqueness key fields.

```php
public function testInstallAndUpgradeContainContactDeliverySchema(): void
{
    $install = file_get_contents(__DIR__ . '/../../../sql/install.sql');
    $upgrade = file_get_contents(__DIR__ . '/../../../sql/upgrade.sql');

    self::assertIsString($install);
    self::assertIsString($upgrade);
    foreach ([$install, $upgrade] as $sql) {
        self::assertStringContainsString('contact_notification_deliveries', $sql);
        self::assertStringContainsString('borrow_requested', $sql);
        self::assertStringContainsString('borrow_approved', $sql);
        self::assertStringContainsString("'email'", $sql);
        self::assertStringContainsString("'sms'", $sql);
        self::assertStringContainsString("'pending'", $sql);
        self::assertStringContainsString("'sent'", $sql);
        self::assertStringContainsString("'failed'", $sql);
        self::assertStringContainsString("'skipped'", $sql);
        self::assertStringContainsString('recipient_type', $sql);
        self::assertStringContainsString('borrowing_reference', $sql);
    }
}
```

- [ ] **Step 2: Run the schema test and verify it fails.**

Run from `backend`:

```powershell
php vendor/bin/phpunit tests/Feature/ContactNotificationSchemaContractTest.php
```

Expected: FAIL because the table and SQL contract do not exist yet.

- [ ] **Step 3: Write the SQL schema.** Add the table after the existing notification tables in both SQL files. Use nullable `destination`, `subject`, `last_error`, and `sent_at`; `TEXT` for message; `INT` for attempts; timestamps; and a unique key across `recipient_type`, `recipient_id`, `event_type`, `borrowing_reference`, and `channel`. Keep the schema compatible with the repository's MySQL installation and SQLite unit fixtures.

```sql
CREATE TABLE IF NOT EXISTS `contact_notification_deliveries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recipient_type` ENUM('user','visitor') NOT NULL,
    `recipient_id` INT NOT NULL,
    `event_type` ENUM('borrow_requested','borrow_approved') NOT NULL,
    `borrowing_reference` VARCHAR(100) NOT NULL,
    `channel` ENUM('email','sms') NOT NULL,
    `destination` VARCHAR(150) DEFAULT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
    `attempts` INT NOT NULL DEFAULT 0,
    `last_error` VARCHAR(500) DEFAULT NULL,
    `sent_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_contact_delivery_event` (`recipient_type`, `recipient_id`, `event_type`, `borrowing_reference`, `channel`),
    KEY `idx_contact_delivery_status` (`status`, `created_at`)
) ENGINE=InnoDB;
```

- [ ] **Step 4: Add the PDO repository and its failing-first tests.** Implement recipient lookup using `users` for `user` and `visitors` for `visitor`, returning normalized `name`, nullable `email`, and nullable `phone`. Implement a driver-aware insert-if-missing: SQLite may use `INSERT OR IGNORE`, while MySQL may use `INSERT IGNORE`; return the inserted ID only when this call created the row. `markSent()` must increment attempts, set `sent_at`, clear `last_error`, and set `status = 'sent'`. `markFailed()` must increment attempts, set `status = 'failed'`, and persist a bounded error message.

- [ ] **Step 5: Run the repository and schema tests.**

```powershell
php vendor/bin/phpunit tests/Unit/Infrastructure/PdoContactNotificationDeliveryRepositoryTest.php tests/Feature/ContactNotificationSchemaContractTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit the persistence slice.**

```powershell
git add sql/install.sql sql/upgrade.sql backend/src/Infrastructure/Persistence/ContactNotificationDeliveryRepositoryInterface.php backend/src/Infrastructure/Persistence/PdoContactNotificationDeliveryRepository.php backend/tests/Unit/Infrastructure/PdoContactNotificationDeliveryRepositoryTest.php backend/tests/Feature/ContactNotificationSchemaContractTest.php
git commit -m "feat: add contact notification delivery storage"
```

---

## Task 2: Implement the notification payload and delivery service

**Files:**
- Create: `backend/src/Application/DTO/BorrowerContactNotification.php`
- Create: `backend/src/Application/Services/BorrowerContactNotificationInterface.php`
- Create: `backend/src/Application/Services/BorrowerContactNotificationService.php`
- Create: `backend/tests/Unit/Mail/BorrowerContactNotificationServiceTest.php`

**Interfaces:**

```php
final readonly class BorrowerContactNotification
{
    /** @param list<array{title: string, quantity: int}> $titles */
    public function __construct(
        public string $recipientType,
        public int $recipientId,
        public string $eventType,
        public string $borrowingReference,
        public array $titles,
        public ?string $dueDate = null,
    ) {}
}

interface BorrowerContactNotificationInterface
{
    public function dispatch(BorrowerContactNotification $notification): void;
}
```

- [ ] **Step 1: Write failing service tests.** Use a fake repository plus fake email/SMS senders. Cover:
  - both channels are created and attempted when both contacts exist;
  - missing email creates a skipped email row and still sends SMS;
  - missing phone creates a skipped SMS row and still sends email;
  - SMTP disabled and SMS exceptions mark failures without throwing;
  - a null delivery ID means an existing unique row and must not send again;
  - bulk titles render as `Title (2 copies), Other Title` and due date appears only when supplied.

```php
public function testDispatchesBothChannelsAndMarksThemSent(): void
{
    $repository = new InMemoryContactDeliveryRepository(
        ['name' => 'Juan Cruz', 'email' => 'juan@example.com', 'phone' => '09170000001'],
    );
    $email = new SpyEmailSender(true);
    $sms = new SpySmsSender();
    $service = new BorrowerContactNotificationService($repository, $email, $sms);

    $service->dispatch(new BorrowerContactNotification(
        'user',
        7,
        'borrow_requested',
        'S2B-20261110-ABC123',
        [['title' => 'Clean Code', 'quantity' => 2]],
    ));

    self::assertCount(1, $email->messages);
    self::assertCount(1, $sms->messages);
    self::assertSame(['sent', 'sent'], $repository->statuses());
    self::assertStringContainsString('Clean Code (2 copies)', $email->messages[0]['html']);
}
```

- [ ] **Step 2: Run the service tests and verify they fail.**

```powershell
php vendor/bin/phpunit tests/Unit/Mail/BorrowerContactNotificationServiceTest.php
```

Expected: FAIL because the DTO/service do not exist.

- [ ] **Step 3: Implement the DTO, port, and service.** Validate the event type, recipient type, channel names, positive recipient ID, non-empty reference, and non-empty title list at the service boundary. Resolve contacts once, render the request or approval subject/body, create one row for each channel, and only call a sender when its destination/provider is usable. Catch `Throwable` around SMS and email calls; treat a `false` email result as a failure; update the row through the repository. Do not let delivery errors escape `dispatch()`.

```php
public function dispatch(BorrowerContactNotification $notification): void
{
    $recipient = $this->deliveries->findRecipient($notification->recipientType, $notification->recipientId);
    if ($recipient === null) {
        return;
    }

    foreach (['email', 'sms'] as $channel) {
        $destination = $channel === 'email' ? $recipient['email'] : $recipient['phone'];
        $subject = $channel === 'email' ? $this->subject($notification->eventType) : null;
        $message = $channel === 'email'
            ? $this->htmlMessage($recipient['name'], $notification)
            : $this->smsMessage($recipient['name'], $notification);
        $status = $destination === null || trim($destination) === '' ? 'skipped' : 'pending';
        $error = $status === 'skipped' ? 'No contact value is on file.' : null;

        $deliveryId = $this->deliveries->createDelivery(
            $notification->recipientType,
            $notification->recipientId,
            $notification->eventType,
            $notification->borrowingReference,
            $channel,
            $destination,
            $subject,
            $message,
            $status,
            $error,
        );
        if ($deliveryId === null || $status === 'skipped') {
            continue;
        }

        $this->send($deliveryId, $channel, (string) $destination, $recipient['name'], (string) $subject, $message);
    }
}
```

- [ ] **Step 4: Run focused service tests and PHPStan.**

```powershell
php vendor/bin/phpunit tests/Unit/Mail/BorrowerContactNotificationServiceTest.php
php vendor/bin/phpstan analyse --configuration=phpstan.neon
```

Expected: service tests PASS; PHPStan reports no new errors.

- [ ] **Step 5: Commit the delivery slice.**

```powershell
git add backend/src/Application/DTO/BorrowerContactNotification.php backend/src/Application/Services/BorrowerContactNotificationInterface.php backend/src/Application/Services/BorrowerContactNotificationService.php backend/tests/Unit/Mail/BorrowerContactNotificationServiceTest.php
git commit -m "feat: deliver borrower contact notifications"
```

---

## Task 3: Integrate registered-user portal requests

**Files:**
- Modify: `backend/src/Application/Services/BorrowingService.php`
- Modify: `backend/src/Infrastructure/Persistence/BorrowingRepositoryInterface.php`
- Modify: `backend/src/Infrastructure/Persistence/PdoBorrowingRepository.php`
- Modify: `backend/tests/Unit/Borrowing/BorrowingServiceTest.php`
- Modify: `backend/tests/Unit/Borrowing/BulkBorrowingServiceTest.php`

**Interfaces:**

Add an optional `?BorrowerContactNotificationInterface $contactNotifications = null` constructor dependency to `BorrowingService` so existing test doubles remain compatible. Extend the bulk repository result to:

```php
/** @return array{
 *   transaction_code: string,
 *   copy_count: int,
 *   title_count: int,
 *   titles: list<array{title: string, quantity: int}>
 * } */
```

- [ ] **Step 1: Add failing service tests.** Assert single pending requests dispatch `borrow_requested`; bulk requests dispatch one event with every title/quantity; immediate-approved policy dispatches `borrow_approved` instead; failed borrowing results dispatch nothing.

```php
$notifications->expects(self::once())
    ->method('dispatch')
    ->with(self::callback(static fn (BorrowerContactNotification $event): bool =>
        $event->recipientType === 'user'
        && $event->recipientId === 7
        && $event->eventType === 'borrow_requested'
        && $event->borrowingReference === 'S2B-20261110-ABC123'
        && $event->titles === [['title' => 'Clean Code', 'quantity' => 1]]
    ));
```

- [ ] **Step 2: Run the targeted tests and verify the new assertions fail.**

```powershell
php vendor/bin/phpunit tests/Unit/Borrowing/BorrowingServiceTest.php tests/Unit/Borrowing/BulkBorrowingServiceTest.php
```

Expected: existing borrowing behavior passes, but the new notification expectations fail.

- [ ] **Step 3: Implement dispatch after repository success.** Pass the optional port into the service. For single borrowing, use the already loaded book title and transaction code. For bulk borrowing, have `PdoBorrowingRepository::createBulkTransaction()` group the allocated copies by title and return `titles`; use the returned transaction code and due date. Dispatch only after the repository method returns successfully.

```php
if ($this->contactNotifications !== null) {
    $this->contactNotifications->dispatch(new BorrowerContactNotification(
        'user',
        $request->userId,
        $approvalValue === 'approved' ? 'borrow_approved' : 'borrow_requested',
        $transactionCode,
        [['title' => $title, 'quantity' => 1]],
        $dueDate->format('Y-m-d'),
    ));
}
```

- [ ] **Step 4: Run borrowing tests and PHPStan.**

```powershell
php vendor/bin/phpunit tests/Unit/Borrowing/BorrowingServiceTest.php tests/Unit/Borrowing/BulkBorrowingServiceTest.php
php vendor/bin/phpstan analyse --configuration=phpstan.neon
```

Expected: PASS with no new analysis errors.

- [ ] **Step 5: Commit the registered-request slice.**

```powershell
git add backend/src/Application/Services/BorrowingService.php backend/src/Infrastructure/Persistence/BorrowingRepositoryInterface.php backend/src/Infrastructure/Persistence/PdoBorrowingRepository.php backend/tests/Unit/Borrowing/BorrowingServiceTest.php backend/tests/Unit/Borrowing/BulkBorrowingServiceTest.php
git commit -m "feat: notify registered borrowers about requests"
```

---

## Task 4: Integrate registered-user approval and staff-desk borrowing

**Files:**
- Modify: `backend/src/Infrastructure/Persistence/PdoStaffRepository.php`
- Modify: `backend/src/Application/StaffCirculation/StaffCirculationService.php`
- Modify: `backend/src/Bootstrap/ApplicationFactory.php` only for constructor wiring after all ports exist
- Modify: `backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php`
- Modify: `backend/tests/Unit/StaffCirculation/StaffCirculationServiceTest.php`

**Interfaces:**

Add optional `?BorrowerContactNotificationInterface $contactNotifications = null` to both `PdoStaffRepository` and `StaffCirculationService`. The approval repository must retain the existing in-app notifier and call the contact port only after its transaction commits.

- [ ] **Step 1: Add failing approval/direct-borrow tests.** Use a spy implementation of the contact port. Assert:
  - approved normalized borrowing dispatches one `borrow_approved` event after the PDO commit;
  - approved legacy borrowing dispatches one event;
  - rejected borrowing dispatches none;
  - repeated/stale approval dispatches none;
  - direct staff-desk borrowing dispatches one event with returned transaction code, due date, and title items.

- [ ] **Step 2: Run the targeted tests and verify the new expectations fail.**

```powershell
php vendor/bin/phpunit tests/Unit/Infrastructure/PdoStaffRepositoryTest.php tests/Unit/StaffCirculation/StaffCirculationServiceTest.php
```

Expected: existing approval/circulation behavior passes while the new contact-event assertions fail.

- [ ] **Step 3: Extend approval context and dispatch after commit.** Include transaction code and due date in the normalized and legacy approval SELECTs. Keep `notifyApprovedBorrower()` for the in-app notification. After the successful `$this->pdo->commit()`, call a new helper that builds unique title/quantity entries and dispatches `BorrowerContactNotification('user', ..., 'borrow_approved', ...)`. Do not call the external port in the transaction or when the update affected zero rows.

- [ ] **Step 4: Dispatch direct-borrow confirmation after the repository returns.** In `StaffCirculationService::borrow()`, use `data['items']`, `data['transaction_code']`, and `data['due_date']` to build the event. Normalize item titles and quantities before dispatching. Keep the user-facing result and existing error handling unchanged.

- [ ] **Step 5: Run targeted tests and PHPStan.**

```powershell
php vendor/bin/phpunit tests/Unit/Infrastructure/PdoStaffRepositoryTest.php tests/Unit/StaffCirculation/StaffCirculationServiceTest.php
php vendor/bin/phpstan analyse --configuration=phpstan.neon
```

Expected: PASS with no new analysis errors.

- [ ] **Step 6: Commit the approval/direct-borrow slice.**

```powershell
git add backend/src/Infrastructure/Persistence/PdoStaffRepository.php backend/src/Application/StaffCirculation/StaffCirculationService.php backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php backend/tests/Unit/StaffCirculation/StaffCirculationServiceTest.php
git commit -m "feat: notify borrowers after approval"
```

---

## Task 5: Integrate guest requests and approvals

**Files:**
- Modify: `backend/src/Application/Services/GuestBorrowingService.php`
- Modify: `backend/src/Infrastructure/Persistence/GuestBorrowingRepositoryInterface.php`
- Modify: `backend/src/Infrastructure/Persistence/PdoGuestBorrowingRepository.php`
- Modify: `backend/src/Application/Services/GuestApprovalService.php`
- Modify: `backend/tests/Unit/GuestBorrowingServiceTest.php`
- Modify: `backend/tests/Unit/GuestApprovalServiceTest.php`

**Interfaces:**

Change guest creation to return the context required by the notification, while keeping the inserted ID available to the controller:

```php
/** @return array{id: int, title: string, due_date: string} */
public function createPending(GuestBorrowRequest $request, int $visitorId): array;
```

Add optional `?BorrowerContactNotificationInterface $contactNotifications = null` to `GuestBorrowingService` and `GuestApprovalService`.

- [ ] **Step 1: Add failing guest tests.** Assert guest submission dispatches `borrow_requested` after creation with visitor recipient type, inserted ID reference, title, and due date. Assert guest approval dispatches `borrow_approved` after repository approval with the same visitor and title. Assert rejected requests never dispatch contact notifications.

- [ ] **Step 2: Run the guest tests and verify the new expectations fail.**

```powershell
php vendor/bin/phpunit tests/Unit/GuestBorrowingServiceTest.php tests/Unit/GuestApprovalServiceTest.php
```

Expected: existing guest in-app notification assertions pass while the new contact assertions fail.

- [ ] **Step 3: Implement guest context and dispatch.** Have `PdoGuestBorrowingRepository` select the book title before/after insert and return the new borrowing ID, title, and due date. Dispatch the request event only after `createPending()` succeeds. In `GuestApprovalService`, retain the existing in-app approval notification and dispatch the contact event only after `$this->requests->approve()` returns successfully.

- [ ] **Step 4: Run guest tests and PHPStan.**

```powershell
php vendor/bin/phpunit tests/Unit/GuestBorrowingServiceTest.php tests/Unit/GuestApprovalServiceTest.php
php vendor/bin/phpstan analyse --configuration=phpstan.neon
```

Expected: PASS with no new analysis errors.

- [ ] **Step 5: Commit the guest slice.**

```powershell
git add backend/src/Application/Services/GuestBorrowingService.php backend/src/Infrastructure/Persistence/GuestBorrowingRepositoryInterface.php backend/src/Infrastructure/Persistence/PdoGuestBorrowingRepository.php backend/src/Application/Services/GuestApprovalService.php backend/tests/Unit/GuestBorrowingServiceTest.php backend/tests/Unit/GuestApprovalServiceTest.php
git commit -m "feat: notify guests about borrowing status"
```

---

## Task 6: Wire production dependencies and validate the complete feature

**Files:**
- Modify: `backend/src/Bootstrap/ApplicationFactory.php`
- Modify: any test doubles required by changed constructor signatures
- Modify: `docs/superpowers/specs/2026-11-10-borrower-contact-notifications-design.md` only if implementation discovers an approved contract correction

- [ ] **Step 1: Add the shared production graph.** Construct one `PdoContactNotificationDeliveryRepository($pdo)` and one `BorrowerContactNotificationService($deliveryRepository, new SmtpEmailSender(), SmsSenderFactory::create())`. Inject the same port into `BorrowingService`, `StaffCirculationService`, `GuestBorrowingService`, `GuestApprovalService`, and `PdoStaffRepository`. Preserve the existing `BorrowerNotificationService` used by staff's manual history notification.

```php
$contactNotifications = new BorrowerContactNotificationService(
    new PdoContactNotificationDeliveryRepository($pdo),
    new SmtpEmailSender(),
    SmsSenderFactory::create(),
);
```

- [ ] **Step 2: Run all focused feature tests.**

```powershell
php vendor/bin/phpunit tests/Unit/Mail/BorrowerContactNotificationServiceTest.php tests/Unit/Infrastructure/PdoContactNotificationDeliveryRepositoryTest.php tests/Unit/Borrowing/BorrowingServiceTest.php tests/Unit/Borrowing/BulkBorrowingServiceTest.php tests/Unit/GuestBorrowingServiceTest.php tests/Unit/GuestApprovalServiceTest.php tests/Unit/Infrastructure/PdoStaffRepositoryTest.php tests/Unit/StaffCirculation/StaffCirculationServiceTest.php tests/Feature/ContactNotificationSchemaContractTest.php
```

Expected: all new and affected tests PASS.

- [ ] **Step 3: Run the complete backend quality gate.**

```powershell
Set-Location backend
composer run quality
```

Expected: record the fresh test/assertion counts, all new/affected tests passing, and any pre-existing failures separately. Do not change quality configuration to make baseline failures disappear.

- [ ] **Step 4: Run the repository frontend test lane because shared application wiring can affect browser behavior.**

From the repository root:

```powershell
npm test
```

Expected: record fresh output. Treat unrelated existing failures as baseline only if they reproduce unchanged.

- [ ] **Step 5: Inspect the final diff and status.**

```powershell
git diff --check
git status --short
git diff --stat HEAD~6..HEAD
```

Confirm unrelated files remain unstaged and unmodified by this feature. Review every staged file for accidental credentials, sensitive data, or unrelated formatting changes.

- [ ] **Step 6: Push and verify remote CI.**

```powershell
git push origin master
git log -6 --oneline
```

Use the configured GitHub integration or repository status to verify the corresponding Actions run. If remote CI remains unavailable or fails only in the already documented baseline lanes, report the exact run and failure counts rather than claiming a green build.

---

## Self-review checklist

- [x] Spec coverage: request and approval events for students, teachers, and guests are covered in Tasks 3–5; direct staff-desk approval is covered in Task 4; channels, logging, idempotency, transaction boundaries, and existing notification preservation are covered in Tasks 1–2 and 6.
- [x] No placeholders: every task names exact files, signatures, commands, and expected outcomes.
- [x] Type consistency: all integrations use `BorrowerContactNotificationInterface::dispatch(BorrowerContactNotification): void`; bulk and guest repository return shapes are explicit; the shared production graph uses the same delivery service instance.
- [x] Test integrity: no focused production test configuration, skipped tests, weakened assertions, or disabled quality gates are planned.
