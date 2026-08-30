# Reservations and Renewals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build title-level FIFO reservations with 24-hour holds and automatic notifications, plus borrower-requested renewals requiring librarian approval.

**Architecture:** Add independent typed reservation and renewal domain services behind PDO repository interfaces. Reservations advance from return and an idempotent expiry command; renewals use a borrower request service and a staff approval service that rechecks policy inside a transaction. Existing normalized tables are primary and legacy tables remain supported through repository capability checks.

**Tech Stack:** PHP 8.2+, strict typed framework-free PHP, PDO/MySQL or SQLite test doubles, PHPUnit 11, PHPStan level 9, ES modules, Node test runner, existing API client and Bootstrap page shell.

## Global Constraints

- Reservations are title-level, not copy-level.
- Queue order is first-come-first-served by an immutable sequence/timestamp combination.
- A borrower may have one active reservation per title.
- An offer expires after exactly 24 hours and the next eligible borrower is notified.
- Borrowers create renewal requests themselves; librarians approve or reject them.
- One loan may receive one approved renewal for one additional standard loan period.
- Renewal eligibility requires an active account, no overdue active loan, no outstanding fine, no active hold on the title, and no previous approved renewal for that loan.
- Every mutating endpoint uses the existing CSRF and role authorization mechanisms.
- In-app notifications are authoritative; SMTP email is best-effort when configured.
- Existing legacy `books` / `borrowing` fallback behavior must remain intact.
- Frontend surfaces use Swiss direction: `#F7F7F8`, `#E4002B`, Helvetica Neue/system sans, 1px rules, left alignment, and real API values only.
- The implementation contains at least 12 meaningful reservation commits and at least 12 meaningful renewal commits.
- Every PHP file added or modified declares `strict_types=1`, uses typed properties/parameters/returns, and follows PSR-12.
- Verification commands are `cd backend && composer test`, `cd backend && composer analyse`, and `npm test`.

## File Map

### Reservation files

- Create `sql/upgrade_reservations.sql` for normalized title reservation schema and indexes.
- Create `backend/src/Domain/Reservation/HoldStatus.php` for the reservation state enum.
- Create `backend/src/Domain/Reservation/HoldRecord.php` for the typed reservation read model.
- Create `backend/src/Application/DTO/JoinHoldRequest.php` and `backend/src/Application/DTO/HoldActionRequest.php` for validated inputs.
- Create `backend/src/Infrastructure/Persistence/HoldRepositoryInterface.php` and `backend/src/Infrastructure/Persistence/PdoHoldRepository.php` for storage and locking.
- Create `backend/src/Infrastructure/Persistence/CirculationNotificationRepositoryInterface.php` and `backend/src/Infrastructure/Persistence/PdoCirculationNotificationRepository.php` for borrower notifications.
- Create `backend/src/Application/Services/ReservationService.php` for join/cancel/claim/fulfil policy.
- Create `backend/src/Application/Services/ReservationAvailabilityService.php` for offer creation and queue advancement.
- Create `backend/src/Application/Services/HoldExpiryService.php` for repeatable 24-hour expiry processing.
- Create `backend/src/Http/Controllers/ReservationController.php` and `backend/src/Http/Routing/ReservationRouteTable.php` for borrower endpoints.
- Create `backend/src/Http/Controllers/StaffCirculationController.php` and modify `backend/src/Http/Routing/StaffRouteTable.php` for staff hold actions.
- Create `backend/bin/process-circulation.php` for the maintenance command.
- Create `frontend/features/shared/services/circulation.service.js` for hold and renewal API calls.
- Create `frontend/features/shared/components/hold-queue/hold-queue.component.js` for escaped borrower hold rendering.
- Modify `frontend/features/student/pages/dashboard/student-dashboard.page.js`, `frontend/features/teacher/pages/dashboard/teacher-dashboard.page.js`, and their HTML files for borrower hold panels.
- Create `frontend/features/staff/pages/circulation/circulation.html`, `circulation.page.js`, and `entry.js` for staff hold review.
- Modify `backend/src/Bootstrap/ApplicationFactory.php`, `backend/src/Http/Routing/PageRouteTable.php`, and `backend/tests/Support/FrontendPagePaths.php` for wiring.

### Renewal files

- Create `sql/upgrade_renewals.sql` for renewal request schema and due-date audit columns.
- Create `backend/src/Domain/Renewal/RenewalStatus.php` and `backend/src/Domain/Renewal/RenewalRequestRecord.php` for typed state.
- Create `backend/src/Application/DTO/RequestRenewalRequest.php` and `backend/src/Application/DTO/RenewalActionRequest.php` for validated inputs.
- Create `backend/src/Infrastructure/Persistence/RenewalRepositoryInterface.php` and `backend/src/Infrastructure/Persistence/PdoRenewalRepository.php` for eligibility, requests, and approvals.
- Create `backend/src/Application/Services/RenewalPolicy.php`, `backend/src/Application/Services/RenewalRequestService.php`, and `backend/src/Application/Services/RenewalApprovalService.php` for typed policy and workflows.
- Create `backend/src/Http/Controllers/RenewalController.php` and modify `backend/src/Http/Routing/ReservationRouteTable.php` or add `backend/src/Http/Routing/RenewalRouteTable.php` for borrower requests.
- Modify `backend/src/Http/Controllers/StaffCirculationController.php` and its route table for staff approvals.
- Create `frontend/features/shared/components/renewal-list/renewal-list.component.js` for borrower renewal rendering.
- Modify borrower dashboard/history pages for renewal buttons and factual rule copy.
- Modify staff circulation page for renewal filters and approve/reject actions.
- Modify `backend/src/Bootstrap/ApplicationFactory.php`, API documentation catalog, and frontend services for wiring.

---

## Reservation implementation: 12 commits

### Task 1: Add reservation schema and state enum

**Files:**
- Create: `sql/upgrade_reservations.sql`
- Create: `backend/src/Domain/Reservation/HoldStatus.php`
- Create: `backend/tests/Unit/Reservation/HoldStatusTest.php`
- Modify: `backend/tests/Feature/SchemaContractTest.php`

**Interfaces:**
- Produces `HoldStatus::QUEUED`, `OFFERED`, `CLAIMED`, `FULFILLED`, `EXPIRED`, and `CANCELLED` string values.
- Produces `reservations` columns `id`, `user_id`, `title_id`, `queue_sequence`, `status`, `offered_copy_id`, `offered_at`, `hold_expires_at`, `claimed_at`, `fulfilled_at`, `expired_at`, `cancelled_at`, `cancelled_by`, and timestamps.

- [ ] **Step 1: Write the failing enum and schema contract tests.** Assert every enum value is stable and the SQL contains the unique active-reservation key plus indexes on `(title_id, status, queue_sequence)` and `(hold_expires_at, status)`.
- [ ] **Step 2: Run `cd backend && vendor/bin/phpunit tests/Unit/Reservation/HoldStatusTest.php tests/Feature/SchemaContractTest.php -v`; expect failure because the enum and SQL contract do not exist.**
- [ ] **Step 3: Add `HoldStatus` with a `label(): string` match and write the migration using `CREATE TABLE IF NOT EXISTS` plus foreign keys to `users`, `book_titles`, and `book_copies`.**
- [ ] **Step 4: Run the focused tests and expect PASS.**
- [ ] **Step 5: Commit with `git add sql/upgrade_reservations.sql backend/src/Domain/Reservation/HoldStatus.php backend/tests/Unit/Reservation/HoldStatusTest.php backend/tests/Feature/SchemaContractTest.php; git commit -m "feat: add reservation schema and states"`.**

### Task 2: Add typed reservation records and borrower DTOs

**Files:**
- Create: `backend/src/Domain/Reservation/HoldRecord.php`
- Create: `backend/src/Application/DTO/JoinHoldRequest.php`
- Create: `backend/src/Application/DTO/HoldActionRequest.php`
- Create: `backend/tests/Unit/Reservation/HoldRecordTest.php`
- Create: `backend/tests/Unit/Reservation/HoldRequestTest.php`

**Interfaces:**
- `JoinHoldRequest::__construct(public readonly int $userId, public readonly int $titleId)`.
- `HoldActionRequest::__construct(public readonly int $userId, public readonly int $holdId, public readonly string $action)`.
- `HoldRecord` exposes `id(): int`, `titleId(): int`, `title(): string`, `status(): HoldStatus`, `queuePosition(): ?int`, `holdExpiresAt(): ?DateTimeImmutable`, and `userId(): int`.

- [ ] **Step 1: Write tests for positive ids, rejected zero/negative ids, accepted actions `cancel` and `claim`, and conversion of nullable expiry timestamps.**
- [ ] **Step 2: Run `cd backend && vendor/bin/phpunit tests/Unit/Reservation/HoldRecordTest.php tests/Unit/Reservation/HoldRequestTest.php -v`; expect validation/construction failures.**
- [ ] **Step 3: Implement readonly DTOs and `HoldRecord::fromRow(array $row): self` with explicit scalar normalization and `InvalidArgumentException` for invalid input.**
- [ ] **Step 4: Run focused tests and expect PASS.**
- [ ] **Step 5: Commit with `git add backend/src/Domain/Reservation backend/src/Application/DTO/JoinHoldRequest.php backend/src/Application/DTO/HoldActionRequest.php backend/tests/Unit/Reservation; git commit -m "feat: define reservation records and requests"`.**

### Task 3: Define the hold repository contract

**Files:**
- Create: `backend/src/Infrastructure/Persistence/HoldRepositoryInterface.php`
- Create: `backend/tests/Unit/Reservation/HoldRepositoryContractTest.php`

**Interfaces:**
- `findActiveForUserTitle(int $userId, int $titleId): ?HoldRecord`.
- `listForUser(int $userId): list<HoldRecord>`.
- `join(int $userId, int $titleId): HoldRecord`.
- `cancel(int $holdId, int $userId): bool`.
- `claim(int $holdId, int $userId): ?HoldRecord`.
- `fulfil(int $holdId, int $staffId): bool`.
- `nextEligibleQueued(int $titleId): ?HoldRecord`.
- `offer(int $holdId, int $copyId, DateTimeImmutable $offeredAt, DateTimeImmutable $expiresAt): bool`.
- `expire(int $holdId, DateTimeImmutable $expiredAt): bool`.
- `listStaff(string $status): list<HoldRecord>`.

- [ ] **Step 1: Write a reflection-based contract test asserting every method name, parameter type, and return type.**
- [ ] **Step 2: Run the contract test and expect failure because the interface is absent.**
- [ ] **Step 3: Add the interface with PHPDoc array shapes for list results and `DateTimeImmutable` imports.**
- [ ] **Step 4: Run the contract test and expect PASS.**
- [ ] **Step 5: Commit with `git add backend/src/Infrastructure/Persistence/HoldRepositoryInterface.php backend/tests/Unit/Reservation/HoldRepositoryContractTest.php; git commit -m "feat: define reservation repository contract"`.**

### Task 4: Implement PDO queue reads and joins

**Files:**
- Create: `backend/src/Infrastructure/Persistence/PdoHoldRepository.php`
- Create: `backend/tests/Unit/Infrastructure/PdoHoldRepositoryReservationTest.php`
- Modify: `backend/tests/Support/SqliteDatabase.php` if the existing test helper needs reservation DDL.

**Interfaces:**
- Implements all read/join methods from `HoldRepositoryInterface`.
- Normalized queries join `reservations`, `book_titles`, `users`, and optional `book_copies`; all SQL uses prepared parameters.

- [ ] **Step 1: Add SQLite tests for queue order, duplicate active reservation lookup, user list positions, and unavailable title rejection.**
- [ ] **Step 2: Run `cd backend && vendor/bin/phpunit tests/Unit/Infrastructure/PdoHoldRepositoryReservationTest.php -v`; expect SQL/table failures.**
- [ ] **Step 3: Implement prepared SQL, transaction-safe sequence allocation, explicit row hydration, and normalized-table detection matching `PdoBorrowingRepository`.**
- [ ] **Step 4: Run the focused PDO tests and expect PASS.**
- [ ] **Step 5: Commit with `git add backend/src/Infrastructure/Persistence/PdoHoldRepository.php backend/tests/Unit/Infrastructure/PdoHoldRepositoryReservationTest.php backend/tests/Support/SqliteDatabase.php; git commit -m "feat: persist reservation queues"`.**

### Task 5: Add reservation join/cancel policy service

**Files:**
- Create: `backend/src/Application/Services/ReservationService.php`
- Create: `backend/src/Application/DTO/ReservationResult.php`
- Create: `backend/tests/Unit/Reservation/ReservationServiceTest.php`

**Interfaces:**
- `ReservationService::join(JoinHoldRequest $request): ReservationResult`.
- `ReservationService::list(int $userId): list<HoldRecord>`.
- `ReservationService::cancel(HoldActionRequest $request): ReservationResult`.

- [ ] **Step 1: Write mock repository tests for invalid title ids, duplicate active holds, successful FIFO join, cancel ownership, and already fulfilled/cancelled conflicts.**
- [ ] **Step 2: Run the focused service test and expect failure because the service/result types are absent.**
- [ ] **Step 3: Implement constructor DI with `HoldRepositoryInterface`, return typed result messages, and preserve the existing `{ok, data, errors}` controller mapping boundary.**
- [ ] **Step 4: Run the focused service tests and expect PASS.**
- [ ] **Step 5: Commit with `git add backend/src/Application/Services/ReservationService.php backend/src/Application/DTO/ReservationResult.php backend/tests/Unit/Reservation/ReservationServiceTest.php; git commit -m "feat: add reservation join policy"`.**

### Task 6: Add availability queue advancement and notifications

**Files:**
- Create: `backend/src/Infrastructure/Persistence/CirculationNotificationRepositoryInterface.php`
- Create: `backend/src/Infrastructure/Persistence/PdoCirculationNotificationRepository.php`
- Create: `backend/src/Application/Services/ReservationAvailabilityService.php`
- Create: `backend/tests/Unit/Reservation/ReservationAvailabilityServiceTest.php`
- Create: `backend/tests/Unit/Infrastructure/PdoCirculationNotificationRepositoryTest.php`

**Interfaces:**
- `ReservationAvailabilityService::advance(int $titleId, int $copyId, DateTimeImmutable $now): ?HoldRecord`.
- `CirculationNotificationRepositoryInterface::notifyBorrower(int $userId, string $type, string $title, string $message, int $relatedId): void`.

- [ ] **Step 1: Write tests proving the oldest eligible queued user receives the offer, a second advancement is idempotent, and in-app notification is written with a 24-hour expiry.**
- [ ] **Step 2: Run focused tests and expect failure because the service and notification repository are absent.**
- [ ] **Step 3: Implement a transaction boundary that checks for an existing offered hold, calls `offer`, and writes the notification after the hold transition; use the existing `notifications` table for borrower user ids.**
- [ ] **Step 4: Run focused tests and expect PASS.**
- [ ] **Step 5: Commit with `git add backend/src/Infrastructure/Persistence/CirculationNotificationRepositoryInterface.php backend/src/Infrastructure/Persistence/PdoCirculationNotificationRepository.php backend/src/Application/Services/ReservationAvailabilityService.php backend/tests/Unit/Reservation/ReservationAvailabilityServiceTest.php backend/tests/Unit/Infrastructure/PdoCirculationNotificationRepositoryTest.php; git commit -m "feat: advance reservation queues with notifications"`.**

### Task 7: Integrate availability advancement with borrower returns

**Files:**
- Modify: `backend/src/Application/Services/ReturnService.php`
- Modify: `backend/src/Application/Services/ReturnResult.php`
- Modify: `backend/tests/Unit/Borrowing/ReturnServiceTest.php`
- Create: `backend/tests/Unit/Reservation/ReturnAvailabilityIntegrationTest.php`

**Interfaces:**
- `ReturnService` receives an optional `ReservationAvailabilityService` dependency and calls `advance(titleId, copyId, now)` once per newly available copy.

- [ ] **Step 1: Add a failing return test asserting an eligible hold is offered after a successful normalized return and no offer occurs for failed returns.**
- [ ] **Step 2: Run the return-focused tests and expect the offer callback never to occur.**
- [ ] **Step 3: Inject the availability service through `ApplicationFactory`, pass the returned copy/title ids from repository result data, and preserve legacy returns when normalized identifiers are unavailable.**
- [ ] **Step 4: Run `cd backend && vendor/bin/phpunit tests/Unit/Borrowing/ReturnServiceTest.php tests/Unit/Reservation/ReturnAvailabilityIntegrationTest.php -v` and expect PASS.**
- [ ] **Step 5: Commit with `git add backend/src/Application/Services/ReturnService.php backend/src/Application/Services/ReturnResult.php backend/tests/Unit/Borrowing/ReturnServiceTest.php backend/tests/Unit/Reservation/ReturnAvailabilityIntegrationTest.php; git commit -m "feat: offer reservations after returns"`.**

### Task 8: Add reservation claim, fulfilment, and borrower API

**Files:**
- Modify: `backend/src/Application/Services/ReservationService.php`
- Create: `backend/src/Http/Controllers/ReservationController.php`
- Create: `backend/src/Http/Routing/ReservationRouteTable.php`
- Create: `backend/tests/Feature/ReservationControllerTest.php`
- Create: `backend/tests/Unit/Reservation/ReservationClaimTest.php`

**Interfaces:**
- `ReservationService::claim(HoldActionRequest $request): ReservationResult`.
- `ReservationService::fulfil(int $holdId, int $staffId): ReservationResult`.
- Controller actions `list`, `create`, and `action` return the existing JSON envelope.

- [ ] **Step 1: Write feature tests for student/teacher list, join, cancel, claim, CSRF failure, unauthorized user, and claim-after-expiry conflict.**
- [ ] **Step 2: Run the focused feature tests and expect missing-route/missing-controller failures.**
- [ ] **Step 3: Implement controller identity checks using `SessionService`, CSRF checks using `CsrfService`, typed DTO creation, and route entries for both student and teacher paths.**
- [ ] **Step 4: Run the focused feature tests and expect PASS.**
- [ ] **Step 5: Commit with `git add backend/src/Application/Services/ReservationService.php backend/src/Http/Controllers/ReservationController.php backend/src/Http/Routing/ReservationRouteTable.php backend/tests/Feature/ReservationControllerTest.php backend/tests/Unit/Reservation/ReservationClaimTest.php; git commit -m "feat: expose borrower reservation actions"`.**

### Task 9: Add staff hold review and maintenance expiry

**Files:**
- Create: `backend/src/Application/Services/HoldExpiryService.php`
- Create: `backend/src/Http/Controllers/StaffCirculationController.php`
- Modify: `backend/src/Http/Routing/StaffRouteTable.php`
- Create: `backend/bin/process-circulation.php`
- Create: `backend/tests/Unit/Reservation/HoldExpiryServiceTest.php`
- Create: `backend/tests/Feature/StaffCirculationHoldTest.php`

**Interfaces:**
- `HoldExpiryService::expireOffers(DateTimeImmutable $now): int`.
- Staff controller actions `holds` and `holdAction` support `cancel`, `advance`, and `fulfil`.
- CLI command accepts only `expire-holds` and exits nonzero for unsupported commands.

- [ ] **Step 1: Write tests for expiring offers at `hold_expires_at <= now`, advancing each affected title once, staff-only actions, and CLI command dispatch.**
- [ ] **Step 2: Run focused tests and expect missing service/controller/command failures.**
- [ ] **Step 3: Implement expiry in a transaction-safe loop, inject `ClockInterface`, wire staff authorization and CSRF, and bootstrap the CLI with `ApplicationFactory` dependencies without emitting HTTP.**
- [ ] **Step 4: Run focused tests and expect PASS.**
- [ ] **Step 5: Commit with `git add backend/src/Application/Services/HoldExpiryService.php backend/src/Http/Controllers/StaffCirculationController.php backend/src/Http/Routing/StaffRouteTable.php backend/bin/process-circulation.php backend/tests/Unit/Reservation/HoldExpiryServiceTest.php backend/tests/Feature/StaffCirculationHoldTest.php; git commit -m "feat: expire and review reservation holds"`.**

### Task 10: Add shared borrower hold service and queue rail

**Files:**
- Create: `frontend/features/shared/services/circulation.service.js`
- Create: `frontend/features/shared/components/hold-queue/hold-queue.component.js`
- Create: `frontend/tests/circulation-service.test.js`
- Create: `frontend/tests/hold-queue-component.test.js`

**Interfaces:**
- `CirculationService.listHolds(role)`, `joinHold(role, titleId)`, and `holdAction(role, holdId, action)` call the route paths from the spec through `ApiClient`.
- `HoldQueueComponent.render(root, holds)` renders escaped title/status/position/expiry values and factual empty state copy.

- [ ] **Step 1: Write Node tests for exact endpoint paths, request bodies, XSS escaping, queue position visibility, and no fabricated rows.**
- [ ] **Step 2: Run `npm test -- frontend/tests/circulation-service.test.js frontend/tests/hold-queue-component.test.js` and expect module/component failures.**
- [ ] **Step 3: Implement the ES modules with DOM APIs already used by the frontend and Swiss tokens (`#F7F7F8`, `#E4002B`, 1px rules) scoped to the component class names.**
- [ ] **Step 4: Run focused Node tests and expect PASS.**
- [ ] **Step 5: Commit with `git add frontend/features/shared frontend/tests/circulation-service.test.js frontend/tests/hold-queue-component.test.js; git commit -m "feat: add borrower reservation queue rail"`.**

### Task 11: Surface holds on student and teacher dashboards

**Files:**
- Modify: `frontend/features/student/pages/dashboard/dashboard.html`
- Modify: `frontend/features/student/pages/dashboard/student-dashboard.page.js`
- Modify: `frontend/features/teacher/pages/dashboard/dashboard.html`
- Modify: `frontend/features/teacher/pages/dashboard/teacher-dashboard.page.js`
- Modify: `frontend/assets/css/style.css`
- Create: `frontend/tests/borrower-hold-dashboard.test.js`

**Interfaces:**
- Dashboard API response may include `holds`; pages call `CirculationService` when it is not embedded and render the same component for both roles.

- [ ] **Step 1: Write tests asserting student and teacher dashboards include the hold host, import the service/component, escape API values, and show Cancel/Claim only for valid states.**
- [ ] **Step 2: Run the focused frontend tests and expect missing markup/import behavior.**
- [ ] **Step 3: Add a factual “Reservations” panel, wire load/action/refresh behavior, and implement the vertical queue rail with real `queue_position` and `hold_expires_at` values only.**
- [ ] **Step 4: Run focused tests and expect PASS.**
- [ ] **Step 5: Commit with `git add frontend/features/student/pages/dashboard frontend/features/teacher/pages/dashboard frontend/assets/css/style.css frontend/tests/borrower-hold-dashboard.test.js; git commit -m "feat: show reservations on borrower dashboards"`.**

### Task 12: Add staff circulation page and reservation integration coverage

**Files:**
- Create: `frontend/features/staff/pages/circulation/circulation.html`
- Create: `frontend/features/staff/pages/circulation/circulation.page.js`
- Create: `frontend/features/staff/pages/circulation/entry.js`
- Modify: `backend/src/Http/Routing/PageRouteTable.php`
- Modify: `backend/tests/Support/FrontendPagePaths.php`
- Create: `frontend/tests/staff-circulation-page.test.js`
- Create: `backend/tests/Feature/ReservationRouteTableTest.php`

**Interfaces:**
- Staff page consumes `GET /api/staff/holds` and `POST /api/staff/holds/action` through `StaffCirculationService` methods.
- Page route key is `staff-circulation` at `/staff/circulation`.

- [ ] **Step 1: Write page tests for the staff path, status filters, empty queue, and action payloads plus backend route-table tests for all reservation routes.**
- [ ] **Step 2: Run focused tests and expect missing page/route failures.**
- [ ] **Step 3: Implement the staff review table using real borrower/title/position/expiry values, add page registry/bootstrap wiring, and add route-table entries for borrower and staff APIs.**
- [ ] **Step 4: Run `npm test` and the reservation feature suite; expect PASS.**
- [ ] **Step 5: Commit with `git add frontend/features/staff/pages/circulation backend/src/Http/Routing/PageRouteTable.php backend/tests/Support/FrontendPagePaths.php frontend/tests/staff-circulation-page.test.js backend/tests/Feature/ReservationRouteTableTest.php; git commit -m "feat: add staff reservation review surface"`.**

## Renewal implementation: 12 commits

### Task 13: Add renewal schema and state enum

**Files:**
- Create: `sql/upgrade_renewals.sql`
- Create: `backend/src/Domain/Renewal/RenewalStatus.php`
- Create: `backend/tests/Unit/Renewal/RenewalStatusTest.php`
- Modify: `backend/tests/Feature/SchemaContractTest.php`

**Interfaces:**
- Produces `RenewalStatus::PENDING`, `APPROVED`, `REJECTED`, and `CANCELLED`.
- Produces `renewal_requests` fields for user, loan/item, title, prior/new due dates, reviewer, status, reason, and timestamps, with indexes on borrower/status and status/requested_at.

- [ ] **Step 1: Write enum and SQL contract tests for the four states, one active pending request key, and all due-date audit columns.**
- [ ] **Step 2: Run focused tests and expect missing enum/schema failures.**
- [ ] **Step 3: Add the enum and idempotent migration with foreign keys and indexes for normalized circulation plus nullable legacy loan id.**
- [ ] **Step 4: Run focused tests and expect PASS.**
- [ ] **Step 5: Commit with `git add sql/upgrade_renewals.sql backend/src/Domain/Renewal/RenewalStatus.php backend/tests/Unit/Renewal/RenewalStatusTest.php backend/tests/Feature/SchemaContractTest.php; git commit -m "feat: add renewal request schema and states"`.**

### Task 14: Add typed renewal records, DTOs, and policy

**Files:**
- Create: `backend/src/Domain/Renewal/RenewalRequestRecord.php`
- Create: `backend/src/Application/DTO/RequestRenewalRequest.php`
- Create: `backend/src/Application/DTO/RenewalActionRequest.php`
- Create: `backend/src/Application/Services/RenewalPolicy.php`
- Create: `backend/tests/Unit/Renewal/RenewalRequestTest.php`
- Create: `backend/tests/Unit/Renewal/RenewalPolicyTest.php`

**Interfaces:**
- `RequestRenewalRequest::__construct(public readonly int $userId, public readonly int $loanId)`.
- `RenewalActionRequest::__construct(public readonly int $staffId, public readonly int $requestId, public readonly string $action, public readonly ?string $reason)`.
- `RenewalPolicy::__construct(int $maxRenewals = 1, int $extensionDays = 7)` with `maxRenewals(): int` and `extensionDays(): int`.

- [ ] **Step 1: Write tests for positive ids, actions `cancel`, `approve`, `reject`, default one-renewal/seven-day policy, and typed row hydration.**
- [ ] **Step 2: Run focused tests and expect missing type/policy failures.**
- [ ] **Step 3: Implement readonly DTOs, enum-backed record hydration, and policy validation with constructor DI-friendly defaults.**
- [ ] **Step 4: Run focused tests and expect PASS.**
- [ ] **Step 5: Commit with `git add backend/src/Domain/Renewal backend/src/Application/DTO/RequestRenewalRequest.php backend/src/Application/DTO/RenewalActionRequest.php backend/src/Application/Services/RenewalPolicy.php backend/tests/Unit/Renewal; git commit -m "feat: define renewal domain policy"`.**

### Task 15: Define and implement renewal repository reads

**Files:**
- Create: `backend/src/Infrastructure/Persistence/RenewalRepositoryInterface.php`
- Create: `backend/src/Infrastructure/Persistence/PdoRenewalRepository.php`
- Create: `backend/tests/Unit/Infrastructure/PdoRenewalRepositoryTest.php`

**Interfaces:**
- `findLoanForRenewal(int $userId, int $loanId): ?array`.
- `activeLoanStanding(int $userId, int $loanId): array{account_active: bool, overdue_count: int, fine_total: float, active_hold: bool, renewal_count: int}`.
- `pendingForUser(int $userId): list<RenewalRequestRecord>`.
- `listStaff(string $status): list<RenewalRequestRecord>`.
- `createRequest(RequestRenewalRequest $request, DateTimeImmutable $requestedAt): RenewalRequestRecord`.

- [ ] **Step 1: Write SQLite tests for normalized and legacy loan lookup, standing counts, duplicate pending request detection, and staff list filtering.**
- [ ] **Step 2: Run focused PDO tests and expect missing interface/table/query failures.**
- [ ] **Step 3: Implement prepared normalized queries with legacy fallback modeled after `PdoBorrowingRepository`, preserving fine and overdue semantics from existing reports.**
- [ ] **Step 4: Run focused tests and expect PASS.**
- [ ] **Step 5: Commit with `git add backend/src/Infrastructure/Persistence/RenewalRepositoryInterface.php backend/src/Infrastructure/Persistence/PdoRenewalRepository.php backend/tests/Unit/Infrastructure/PdoRenewalRepositoryTest.php; git commit -m "feat: persist renewal eligibility and requests"`.**

### Task 16: Implement borrower renewal request service

**Files:**
- Create: `backend/src/Application/Services/RenewalRequestService.php`
- Create: `backend/src/Application/DTO/RenewalResult.php`
- Create: `backend/tests/Unit/Renewal/RenewalRequestServiceTest.php`

**Interfaces:**
- `RenewalRequestService::request(RequestRenewalRequest $request): RenewalResult`.
- `RenewalRequestService::list(int $userId): list<RenewalRequestRecord>`.
- `RenewalRequestService::cancel(RenewalActionRequest $request): RenewalResult`.

- [ ] **Step 1: Write mock tests for account inactive, overdue, fine, active hold, prior renewal, duplicate pending request, successful pending request, and borrower cancellation.**
- [ ] **Step 2: Run focused tests and expect missing service/result failures.**
- [ ] **Step 3: Implement policy evaluation in one service, return precise user-safe messages, and never update loan due dates during request creation.**
- [ ] **Step 4: Run focused tests and expect PASS.**
- [ ] **Step 5: Commit with `git add backend/src/Application/Services/RenewalRequestService.php backend/src/Application/DTO/RenewalResult.php backend/tests/Unit/Renewal/RenewalRequestServiceTest.php; git commit -m "feat: add borrower renewal requests"`.**

### Task 17: Implement librarian approval and rejection transactions

**Files:**
- Create: `backend/src/Application/Services/RenewalApprovalService.php`
- Modify: `backend/src/Infrastructure/Persistence/RenewalRepositoryInterface.php`
- Modify: `backend/src/Infrastructure/Persistence/PdoRenewalRepository.php`
- Create: `backend/tests/Unit/Renewal/RenewalApprovalServiceTest.php`
- Create: `backend/tests/Unit/Infrastructure/PdoRenewalApprovalTransactionTest.php`

**Interfaces:**
- `RenewalApprovalService::approve(RenewalActionRequest $request): RenewalResult`.
- `RenewalApprovalService::reject(RenewalActionRequest $request): RenewalResult`.
- Repository methods `approve(int $requestId, int $staffId, DateTimeImmutable $reviewedAt, DateTimeImmutable $newDueDate): bool` and `reject(int $requestId, int $staffId, string $reason, DateTimeImmutable $reviewedAt): bool`.

- [ ] **Step 1: Write tests for approval adding exactly seven days, second approval conflict, recheck of active hold/overdue/fine/account standing, rejection reason storage, and atomic due-date update.**
- [ ] **Step 2: Run focused tests and expect failure because approval methods are absent.**
- [ ] **Step 3: Implement repository transactions with `SELECT ... FOR UPDATE` on MySQL, conditional updates for SQLite, and service-side recheck before the repository mutation.**
- [ ] **Step 4: Run focused tests and expect PASS.**
- [ ] **Step 5: Commit with `git add backend/src/Application/Services/RenewalApprovalService.php backend/src/Infrastructure/Persistence/RenewalRepositoryInterface.php backend/src/Infrastructure/Persistence/PdoRenewalRepository.php backend/tests/Unit/Renewal/RenewalApprovalServiceTest.php backend/tests/Unit/Infrastructure/PdoRenewalApprovalTransactionTest.php; git commit -m "feat: approve and reject renewals safely"`.**

### Task 18: Add borrower and staff renewal API routes

**Files:**
- Create: `backend/src/Http/Controllers/RenewalController.php`
- Create: `backend/src/Http/Routing/RenewalRouteTable.php`
- Modify: `backend/src/Http/Controllers/StaffCirculationController.php`
- Modify: `backend/src/Http/Routing/StaffRouteTable.php`
- Create: `backend/tests/Feature/RenewalControllerTest.php`
- Create: `backend/tests/Feature/RenewalRouteTableTest.php`

**Interfaces:**
- Borrower actions `list`, `create`, and `action` map to the renewal services.
- Staff actions `renewals` and `renewalAction` map to list/approve/reject.
- Routes are `/api/student/renewals`, `/api/teacher/renewals`, `/api/staff/renewals`, and `/api/staff/renewals/action`.

- [ ] **Step 1: Write feature tests for student/teacher parity, CSRF, ownership, staff approval/rejection, missing reason validation, and JSON envelope shape.**
- [ ] **Step 2: Run focused feature tests and expect missing controller/routes.**
- [ ] **Step 3: Implement role checks, CSRF checks, typed DTO parsing, HTTP 409 conflicts for stale approvals, and route-table registration.**
- [ ] **Step 4: Run focused feature tests and expect PASS.**
- [ ] **Step 5: Commit with `git add backend/src/Http/Controllers/RenewalController.php backend/src/Http/Routing/RenewalRouteTable.php backend/src/Http/Controllers/StaffCirculationController.php backend/src/Http/Routing/StaffRouteTable.php backend/tests/Feature/RenewalControllerTest.php backend/tests/Feature/RenewalRouteTableTest.php; git commit -m "feat: expose renewal request and approval APIs"`.**

### Task 19: Add renewal notifications

**Files:**
- Modify: `backend/src/Application/Services/RenewalRequestService.php`
- Modify: `backend/src/Application/Services/RenewalApprovalService.php`
- Modify: `backend/src/Infrastructure/Persistence/PdoCirculationNotificationRepository.php`
- Create: `backend/tests/Unit/Renewal/RenewalNotificationTest.php`

**Interfaces:**
- Approved notifications include title, new due date, and reviewer outcome.
- Rejected notifications include title and the stored rejection reason.
- Request creation optionally notifies staff through existing `notifications` rows without blocking borrower state when notification storage is unavailable in legacy mode.

- [ ] **Step 1: Write tests for borrower approval/rejection notification text, staff pending-request notification, and best-effort SMTP boundary behavior.**
- [ ] **Step 2: Run focused tests and expect missing notification calls/assertion failures.**
- [ ] **Step 3: Add notification calls after successful state transitions, use HTML escaping in email content, and keep notification failures from rolling back an already committed circulation decision.**
- [ ] **Step 4: Run focused tests and expect PASS.**
- [ ] **Step 5: Commit with `git add backend/src/Application/Services/RenewalRequestService.php backend/src/Application/Services/RenewalApprovalService.php backend/src/Infrastructure/Persistence/PdoCirculationNotificationRepository.php backend/tests/Unit/Renewal/RenewalNotificationTest.php; git commit -m "feat: notify borrowers about renewal decisions"`.**

### Task 20: Add shared frontend renewal service and borrower renderer

**Files:**
- Modify: `frontend/features/shared/services/circulation.service.js`
- Create: `frontend/features/shared/components/renewal-list/renewal-list.component.js`
- Create: `frontend/tests/renewal-service.test.js`
- Create: `frontend/tests/renewal-list-component.test.js`

**Interfaces:**
- `CirculationService.listRenewals(role)`, `requestRenewal(role, loanId)`, and `renewalAction(role, requestId, action)` use `ApiClient`.
- `RenewalListComponent.render(root, renewals, loans)` renders due dates, states, rule text, and request/cancel controls with escaped data.

- [ ] **Step 1: Write Node tests for exact URLs, request body keys, escaped rejection reasons, approved due-date display, and factual empty states.**
- [ ] **Step 2: Run focused Node tests and expect missing service/component failures.**
- [ ] **Step 3: Implement the service methods and component with Swiss rules: red status markers, hairline rows, neutral surface, and no invented borrower/loan data.**
- [ ] **Step 4: Run focused Node tests and expect PASS.**
- [ ] **Step 5: Commit with `git add frontend/features/shared/services/circulation.service.js frontend/features/shared/components/renewal-list frontend/tests/renewal-service.test.js frontend/tests/renewal-list-component.test.js; git commit -m "feat: add borrower renewal controls"`.**

### Task 21: Surface renewal requests on borrower dashboards and history

**Files:**
- Modify: `frontend/features/student/pages/dashboard/dashboard.html`
- Modify: `frontend/features/student/pages/dashboard/student-dashboard.page.js`
- Modify: `frontend/features/student/pages/history/history.html`
- Modify: `frontend/features/student/pages/history/student-history.page.js`
- Modify: `frontend/features/teacher/pages/dashboard/dashboard.html`
- Modify: `frontend/features/teacher/pages/dashboard/teacher-dashboard.page.js`
- Create: `frontend/tests/borrower-renewal-surfaces.test.js`

**Interfaces:**
- Student and teacher pages use the same renewal component/service contract and send `loan_id`, `action`, and CSRF through `ApiClient`.

- [ ] **Step 1: Write tests asserting active non-overdue loans show Request renewal, active holds/overdue/fines show the actual blocking reason, pending requests show Cancel, and approved requests show the new due date.**
- [ ] **Step 2: Run focused tests and expect missing host/action failures.**
- [ ] **Step 3: Add a “Renewals” panel beside “Reservations” and refresh both after every mutation; keep the existing borrow/return forms unchanged.**
- [ ] **Step 4: Run focused tests and expect PASS.**
- [ ] **Step 5: Commit with `git add frontend/features/student frontend/features/teacher frontend/tests/borrower-renewal-surfaces.test.js; git commit -m "feat: show renewal requests to borrowers"`.**

### Task 22: Add renewal review to the staff circulation page

**Files:**
- Modify: `frontend/features/staff/pages/circulation/circulation.html`
- Modify: `frontend/features/staff/pages/circulation/circulation.page.js`
- Modify: `frontend/features/staff/pages/circulation/entry.js`
- Create: `frontend/tests/staff-renewal-review.test.js`

**Interfaces:**
- Staff page consumes `GET /api/staff/renewals` and `POST /api/staff/renewals/action` with `approve`, `reject`, `request_id`, and optional `reason`.

- [ ] **Step 1: Write tests for pending filter, approve payload, reject reason requirement, disabled actions for non-pending rows, and escaped title/borrower/reason values.**
- [ ] **Step 2: Run focused frontend tests and expect missing review markup/handlers.**
- [ ] **Step 3: Add a separate renewal review table with current/new due dates and a required reason input on rejection; show successful approval/rejection toasts and reload data.**
- [ ] **Step 4: Run focused tests and expect PASS.**
- [ ] **Step 5: Commit with `git add frontend/features/staff/pages/circulation frontend/tests/staff-renewal-review.test.js; git commit -m "feat: add staff renewal approval surface"`.**

### Task 23: Wire application factory and API documentation

**Files:**
- Modify: `backend/src/Bootstrap/ApplicationFactory.php`
- Modify: `backend/src/Http/Documentation/ApiEndpointCatalog.php`
- Modify: `backend/src/Http/Routing/PageRouteTable.php`
- Modify: `backend/tests/Feature/ApiDocumentationControllerTest.php`
- Modify: `backend/tests/Feature/PageRouteTableTest.php`
- Modify: `frontend/app/shared/components/app-navbar/app-navbar.component.js`
- Create: `backend/tests/Feature/CirculationApplicationWiringTest.php`

**Interfaces:**
- Production factory constructs one shared PDO hold repository, renewal repository, notification repository, availability service, expiry service, borrower controllers, and staff controller.
- API docs list all borrower/staff hold and renewal endpoints and the staff circulation page is reachable from the session navbar.

- [ ] **Step 1: Write wiring tests that instantiate the production factory with a test environment and assert route names/catalog entries without connecting to an external database.**
- [ ] **Step 2: Run focused wiring/documentation tests and expect missing bindings/catalog entries.**
- [ ] **Step 3: Add constructor dependencies in a single composition-root change, add endpoint metadata, and add a “Circulation” staff navigation link without Unicode icon substitutes.**
- [ ] **Step 4: Run focused tests and expect PASS.**
- [ ] **Step 5: Commit with `git add backend/src/Bootstrap/ApplicationFactory.php backend/src/Http/Documentation/ApiEndpointCatalog.php backend/src/Http/Routing/PageRouteTable.php backend/tests/Feature/ApiDocumentationControllerTest.php backend/tests/Feature/PageRouteTableTest.php backend/tests/Feature/CirculationApplicationWiringTest.php frontend/app/shared/components/app-navbar/app-navbar.component.js; git commit -m "feat: wire circulation workflows into the app"`.**

### Task 24: Run full verification and close the feature set

**Files:**
- Modify: `docs/superpowers/plans/2026-08-30-reservations-renewals.md` to check completed steps and record verification output.
- Create: `backend/tests/Feature/CirculationParityTest.php`

**Interfaces:**
- No new runtime interfaces; this task is the final integration gate.

- [ ] **Step 1: Add the final parity test for student/teacher endpoint equivalence, hold queue progression, renewal approval, and notification rows.**
- [ ] **Step 2: Run `cd backend && composer test`; expect the complete PHPUnit suite to pass.**
- [ ] **Step 3: Run `cd backend && composer analyse`; expect PHPStan level 9 with zero errors.**
- [ ] **Step 4: Run `npm test`; expect the complete Node suite to pass.**
- [ ] **Step 5: Run `git diff --check`, confirm exactly 12 reservation feature commits and 12 renewal feature commits after the design commit, update the plan checkboxes with the command results, and commit with `git add docs/superpowers/plans/2026-08-30-reservations-renewals.md backend/tests/Feature/CirculationParityTest.php; git commit -m "test: verify reservations and renewals end to end"`.**

## Self-review

- Schema, states, FIFO queueing, 24-hour expiry, next-person notification, claims, staff fulfilment, maintenance processing, borrower UI, and staff UI are covered by Tasks 1–12.
- Renewal schema, policy, request/approval/rejection, rechecks, notifications, borrower UI, staff UI, wiring, and parity are covered by Tasks 13–24.
- Legacy fallback is explicitly covered by Tasks 4 and 15 and preserved in Task 7.
- CSRF, authorization, prepared SQL, typed DTOs, and JSON response shape are covered by Tasks 8, 9, and 18.
- No task depends on an undefined runtime method; the repository/service signatures are defined before their consumers.
- The plan has no placeholder markers or unbounded “handle edge cases” steps.
