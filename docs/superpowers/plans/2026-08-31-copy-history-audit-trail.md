# Copy History and Business Audit Trail Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (☐) syntax for tracking.

**Goal:** Build a staff-only barcode lookup that shows a physical copy's complete lifetime and records immutable business audit events for copy, circulation, printing, and archival actions.

**Architecture:** Add a typed audit-event domain layer and PDO repository backed by an append-only audit_events table. Existing business write paths receive an audit writer through constructor injection and record events in the same database transaction as their mutation. A staff-only history controller returns a copy snapshot plus newest-first events; a dedicated page and inventory-panel action consume it.

**Tech Stack:** PHP 8.2+ custom PDO modular monolith, PHPUnit 11, PHPStan 9, MySQL migrations, vanilla JavaScript ES modules, Bootstrap 5, existing BarcodeScannerComponent.

## Global Constraints

- Staff panel only: allow admin and librarian; do not add borrower, teacher, guest, or public access.
- Copy statuses are exactly Available, Borrowed, Reserved, Lost, and Damaged.
- A trimmed reason is required for every transition to or from Lost or Damaged.
- Audit events are append-only; no endpoint updates or deletes them.
- Every PHP file declares strict_types=1, has complete type declarations, and follows PSR-12.
- All SQL is parameterized; frontend server values are escaped before HTML insertion.
- Preserve the unrelated untracked file uploads/112299-81e92dfebf90d638.jpg.
- Use TDD for each behavior: failing test, run it, minimal passing implementation, then refactor.

## File Map

Create:

- sql/upgrade_copy_audit_trail.sql — status extension, audit table, indexes, and idempotent historical backfill.
- backend/src/Domain/Audit/AuditEventType.php — event enum and labels.
- backend/src/Domain/Audit/AuditEvent.php — immutable validated event value object.
- backend/src/Application/DTO/CopyHistoryResult.php — typed copy snapshot and event payload.
- backend/src/Application/Services/CopyHistoryService.php — barcode validation and lookup.
- backend/src/Infrastructure/Persistence/AuditEventRepositoryInterface.php — append/query contract.
- backend/src/Infrastructure/Persistence/PdoAuditEventRepository.php — PDO implementation.
- backend/src/Http/Controllers/CopyHistoryController.php — staff-only JSON endpoint.
- backend/src/Http/Routing/CopyHistoryRouteTable.php — API route registration.
- frontend/features/staff/pages/copy-history/copy-history.html — dedicated staff page.
- frontend/features/staff/pages/copy-history/copy-history.css — Swiss tokens and audit spine.
- frontend/features/staff/pages/copy-history/copy-history.page.js — page controller and timeline renderer.
- frontend/features/staff/pages/copy-history/entry.js — page bootstrap.
- backend/tests/Unit/Audit/AuditEventTest.php
- backend/tests/Unit/Audit/CopyHistoryServiceTest.php
- backend/tests/Unit/Infrastructure/PdoAuditEventRepositoryTest.php
- backend/tests/Feature/CopyHistoryControllerTest.php
- backend/tests/Feature/CopyAuditSchemaContractTest.php
- frontend/tests/copy-history.test.js

Modify:

- backend/src/Domain/Book/BookStatus.php
- backend/src/Application/DTO/BookCopyMutationRequest.php
- backend/src/Application/Validators/BookCopyMutationValidator.php
- backend/src/Infrastructure/Persistence/BookAdministrationRepositoryInterface.php
- backend/src/Infrastructure/Persistence/PdoBookRepository.php
- backend/src/Application/Services/BookMutationService.php
- backend/src/Application/Services/BookArchiveService.php
- backend/src/Application/Services/BorrowingService.php
- backend/src/Application/Services/ReturnService.php
- backend/src/Application/Services/BarcodePrintService.php
- backend/src/Infrastructure/Persistence/PdoBorrowingRepository.php
- backend/src/Infrastructure/Persistence/PdoBarcodePrintRepository.php
- backend/src/Http/Controllers/BookController.php
- backend/src/Http/Controllers/BookCopyController.php
- backend/src/Bootstrap/ApplicationFactory.php
- backend/src/Http/Routing/BookRouteTable.php
- backend/src/Http/Routing/PageRouteTable.php
- frontend/assets/js/core/app-navbar.js
- frontend/features/staff/components/copy-panel/copy-panel.component.js
- frontend/features/staff/pages/inventory/inventory.html
- frontend/features/staff/pages/inventory/inventory.page.js
- frontend/tests/barcode-printing.test.js
- frontend/tests/staff-pages.test.js
- backend/tests/Feature/PageRouteTableTest.php
- backend/tests/Feature/CleanRouteMatrixTest.php

---

### Task 1: Add audit domain types and schema

**Files:** Create the audit enum/value object, repository interface, CopyHistoryResult DTO, sql/upgrade_copy_audit_trail.sql, backend/tests/Unit/Audit/AuditEventTest.php, and backend/tests/Feature/CopyAuditSchemaContractTest.php. Modify BookStatus.php and sql/database.sql.

**Interfaces:**

- AuditEventType has eight values: acquired, status_changed, loaned, returned, barcode_printed, archived, restored, and deleted, each with a stable label.
- AuditEvent constructor: AuditEvent(int copyId, ?int actorUserId, AuditEventType type, ?string fromStatus, ?string toStatus, ?string reason, ?int transactionId, ?int borrowingItemId, ?int printBatchId, array metadata, DateTimeImmutable occurredAt).
- AuditEventRepositoryInterface::record(AuditEvent $event): void.
- AuditEventRepositoryInterface::findCopyHistory(string $barcode): ?CopyHistoryResult.

- [ ] Step 1: Write failing tests proving Lost and Damaged are valid statuses, invalid transition statuses are rejected, and the migration contains the five-status enum, audit_events table, index, and unique legacy source guard.

  Test assertions must include:
  - BookStatus::LOST->value equals Lost.
  - BookStatus::DAMAGED->value equals Damaged.
  - constructing a status event with fromStatus Unknown throws InvalidArgumentException.
  - the SQL contains CREATE TABLE IF NOT EXISTS audit_events and uq_audit_legacy_source.

- [ ] Step 2: Run the focused tests.

  Run: backend\vendor\bin\phpunit tests/Unit/Audit/AuditEventTest.php tests/Feature/CopyAuditSchemaContractTest.php

  Expected: FAIL because the new domain classes, statuses, and migration are absent.

- [ ] Step 3: Implement the enum, immutable event record, result DTO, repository contract, and SQL.

  The migration must add Lost and Damaged to normalized book_copies; create audit_events with nullable copy_id using ON DELETE SET NULL; nullable actor/transaction/item/print references; event/status/reason/metadata/occurred_at columns; KEY (copy_id, occurred_at, id); and a unique nullable legacy source marker. Update the fresh schema to contain the same status enum and table. Use JSON metadata snapshots for barcode, title, accession, location, borrower, and provenance.

- [ ] Step 4: Run the same focused command and verify PASS with zero failures, errors, warnings, and risky tests.

- [ ] Step 5: Commit only this slice.

  Run: git add backend/src/Domain/Audit backend/src/Application/DTO/CopyHistoryResult.php backend/src/Infrastructure/Persistence/AuditEventRepositoryInterface.php backend/src/Domain/Book/BookStatus.php sql/database.sql sql/upgrade_copy_audit_trail.sql backend/tests/Unit/Audit backend/tests/Feature/CopyAuditSchemaContractTest.php

  Commit: git commit -m "feat: add copy audit event domain and schema"

### Task 2: Implement PDO history queries and idempotent historical backfill

**Files:** Create PdoAuditEventRepository.php and its unit test. Complete sql/upgrade_copy_audit_trail.sql.

**Interfaces:**

- record() inserts only parameterized values and encodes metadata with JSON_THROW_ON_ERROR.
- findCopyHistory() trims the barcode, searches live and archived normalized copies, joins title and staff labels, orders occurred_at DESC and id DESC, and returns CopyHistoryResult.
- The migration backfill creates acquired from book_copies.created_at, loaned/returned from borrowing_items and borrowing_transactions plus legacy borrowing where present, and barcode_printed from barcode_print_batch_items.
- Historical actor data uses processed_by or approved_by when available. Missing actors remain null and display Historical record / Actor not recorded.
- Each backfilled row has a deterministic legacy source key; rerunning the SQL inserts no duplicate.

- [ ] Step 1: Write failing PDO tests for record/query and duplicate-safe backfill.

  Test scenarios:
  - record a status_changed event for barcode BC-1 with actor 7 and reason Missing after inventory count; query BC-1 and assert actor, to_status, and reason.
  - query barcode missing and assert null.
  - execute the backfill twice and assert the first run inserts one source event and the second inserts zero.

- [ ] Step 2: Run backend\vendor\bin\phpunit tests/Unit/Infrastructure/PdoAuditEventRepositoryTest.php and verify the failure is caused by the missing repository/schema, not a test typo.

- [ ] Step 3: Implement PdoAuditEventRepository using PDO::FETCH_ASSOC, typed conversion helpers, parameterized SQL, and metadata fallback for missing related records. Implement the SQL backfill with INSERT ... SELECT guarded by NOT EXISTS or the unique source key.

- [ ] Step 4: Run backend\vendor\bin\phpunit tests/Unit/Infrastructure/PdoAuditEventRepositoryTest.php tests/Feature/CopyAuditSchemaContractTest.php and verify PASS.

- [ ] Step 5: Commit with git commit -m "feat: query and backfill copy audit history".

### Task 3: Enforce Lost/Damaged reasons and audit copy mutations

**Files:** Modify BookCopyMutationRequest.php, BookCopyMutationValidator.php, BookAdministrationRepositoryInterface.php, PdoBookRepository.php, BookCopyController.php, inventory.html, inventory.page.js, and copy-panel.component.js. Extend BookCopyMutationValidatorTest.php and PdoBookRepositoryTest.php.

**Interfaces:**

- BookCopyMutationRequest adds public string $reason = ''.
- BookCopyMutationValidator::firstError() rejects unknown status and returns A reason is required when marking a copy lost or damaged. when a required reason is blank; the mutation service also compares the stored current status so transitions from Lost or Damaged require a reason.
- Copy mutation methods receive int actorId and write status_changed, archived, restored, or deleted events with copy snapshots.
- Permanent deletion writes the deleted event before deleting the row and relies on nullable copy_id plus metadata snapshot.

- [ ] Step 1: Add failing tests.

  Test:
  - new BookCopyMutationRequest(1, 'BC-1', status: 'Lost') returns the exact required-reason error.
  - a Damaged-to-Available request with reason Restored after repair returns null.
  - an unknown status returns an invalid-status error.
  - a repository status change records Available to Lost, actor 7, and the submitted reason.

- [ ] Step 2: Run backend\vendor\bin\phpunit tests/Unit/Book/BookCopyMutationValidatorTest.php tests/Unit/Infrastructure/PdoBookRepositoryTest.php and verify the new assertions fail.

- [ ] Step 3: Implement server validation and audit writes. Capture the current copy before update, keep active-loan protections, compare both current and requested statuses, pass the authenticated staff ID and reason, add Lost and Damaged select options, and add a reason input/confirmation flow in the copy panel. Escape barcode values used in data attributes. Archive, restore, and delete must preserve copy metadata.

- [ ] Step 4: Run the focused backend command and npm test -- frontend/tests/staff-pages.test.js. Verify PASS.

- [ ] Step 5: Commit with git commit -m "feat: audit physical copy status changes".

### Task 4: Audit loans, returns, and barcode printing

**Files:** Modify BorrowingService.php, ReturnService.php, BarcodePrintService.php, PdoBorrowingRepository.php, PdoBarcodePrintRepository.php, ApplicationFactory.php, and the existing borrowing/return/print tests.

**Interfaces:**

- Normalized borrowing records one loaned event per allocated copy with transaction/item references and a copy snapshot.
- Return completion records one returned event in the same transaction as the loan item and copy update.
- Barcode batch creation records one barcode_printed event per newly printed copy with print_batch_id and staff actor.
- Borrower-originated actions may have actor_user_id null; staff-originated print/approval actions use the authenticated staff ID.
- Audit insertion failure rolls back the business transaction.

- [ ] Step 1: Add failing tests with an in-memory audit writer that collects AuditEvent objects.

  Test:
  - a two-copy bulk loan emits two loaned events.
  - return emits one returned event with the loan/item references.
  - a two-label print batch emits two barcode_printed events.
  - an audit writer exception prevents the business transaction from committing.

- [ ] Step 2: Run the focused existing tests and verify the new event assertions fail because no audit writer is connected.

  Run: backend\vendor\bin\phpunit tests/Unit/Borrowing/BorrowingServiceTest.php tests/Unit/Borrowing/ReturnServiceTest.php tests/Unit/Infrastructure/PdoBorrowingRepositoryTest.php tests/Unit/Infrastructure/PdoBarcodePrintRepositoryTest.php

- [ ] Step 3: Inject the audit writer into production repositories/services and write events before commit. Preserve current loan allocation, return fine, availability, and print re-export behavior. Do not emit duplicate events when an existing PDF batch is merely viewed.

- [ ] Step 4: Run the focused command again and verify all old and new assertions pass.

- [ ] Step 5: Commit with git commit -m "feat: audit circulation and barcode printing".

### Task 5: Add staff-only history API and route wiring

**Files:** Create CopyHistoryService.php, CopyHistoryController.php, CopyHistoryRouteTable.php, CopyHistoryServiceTest.php, CopyHistoryControllerTest.php. Modify ApplicationFactory.php, PageRouteTable.php, BookRouteTable.php, PageRouteTableTest.php, and CleanRouteMatrixTest.php.

**Interfaces:**

- CopyHistoryService::findByBarcode(string $barcode): CopyHistoryResult validates a non-empty barcode, delegates to AuditEventRepositoryInterface, and raises InvalidArgumentException for invalid input and a not-found exception for unknown copy.
- CopyHistoryController::index(ServerRequest $request): JsonResponse returns 401, 422, 404, or 200 with data.copy and data.events.
- CopyHistoryRouteTable::routes(CopyHistoryController $controller): array registers GET /api/staff/copy-history.
- PageRouteTable protects /staff/copy-history for admin and librarian.

- [ ] Step 1: Add failing tests for student rejection, blank barcode 422, unknown barcode 404, staff success 200, and newest-first event JSON.

- [ ] Step 2: Run backend\vendor\bin\phpunit tests/Unit/Audit/CopyHistoryServiceTest.php tests/Feature/CopyHistoryControllerTest.php tests/Feature/PageRouteTableTest.php and verify expected missing-class/route failures.

- [ ] Step 3: Implement the typed service/controller/routes using existing SessionService role checks, JsonResponse conventions, and Router route-table assembly. Do not add the API to borrower or guest routes.

- [ ] Step 4: Run the focused command plus tests/Feature/CleanRouteMatrixTest.php and verify PASS.

- [ ] Step 5: Commit with git commit -m "feat: add staff copy history API".

### Task 6: Build the dedicated Swiss staff history page

**Files:** Create copy-history.html, copy-history.css, copy-history.page.js, entry.js, and frontend/tests/copy-history.test.js. Modify app-navbar.js.

**Interfaces:**

- CopyHistoryPage.start(): Promise<CopyHistoryPage> binds submit and scanner behavior and loads ?barcode= when present.
- CopyHistoryPage.load(barcode): Promise<void> calls GET /scan2borrow/api/staff/copy-history through ApiClient.
- CopyHistoryPage.render(payload): void renders copy identity and newest-first escaped event spine.

- [ ] Step 1: Add failing frontend tests that assert the API path/params, actor/transition/reason rendering, escaped server strings, scanner target, loading/not-found/error states, and Copy History staff navigation.

  The API assertion must expect path /scan2borrow/api/staff/copy-history and params { barcode: 'BC-1' }. The XSS assertion must provide a reason containing a script tag and assert that timeline.innerHTML contains no script element.

- [ ] Step 2: Run npm test -- frontend/tests/copy-history.test.js and verify failure is due to the absent page module/template.

- [ ] Step 3: Implement the page with the Swiss anchor: #FFFFFF or #F7F7F8 surface, Helvetica Neue/Akzidenz-style sans stack, #002FA7 accent, left alignment, visible 1px rules, no gradients/warm paper/grain/rounded cards. Use BarcodeScannerComponent with data-scan-target="copy-history-barcode" and data-scan-submit. Render text status badges, explicit states, and the continuous blue audit spine with transition pairs and reason beneath.

- [ ] Step 4: Run npm test -- frontend/tests/copy-history.test.js frontend/tests/staff-pages.test.js and verify PASS.

- [ ] Step 5: Commit with git commit -m "feat: add staff copy history page".

### Task 7: Connect the inventory panel entry point

**Files:** Modify copy-panel.component.js, inventory.html, inventory.page.js, barcode-printing.test.js, staff-pages.test.js, FrontendPagePathsTest.php, and any shared navigation contract test that fails.

**Interfaces:**

- Each copy row renders View history with a URL-safe barcode.
- Clicking it navigates to /scan2borrow/staff/copy-history?barcode=...
- Inventory status options contain all five statuses; the reason is submitted when present and server validation remains authoritative.
- Archived rows retain View history while a barcode snapshot is available.

- [ ] Step 1: Add failing contract tests asserting the page route, View history label, /staff/copy-history path, and Lost/Damaged options.

- [ ] Step 2: Run npm test -- frontend/tests/barcode-printing.test.js frontend/tests/staff-pages.test.js and verify the new markers are absent.

- [ ] Step 3: Implement the navigation with encodeURIComponent, escaped data attributes, and no duplication of timeline rendering inside the modal. Keep barcode export/history behavior intact.

- [ ] Step 4: Run npm test -- frontend/tests/barcode-printing.test.js frontend/tests/staff-pages.test.js frontend/tests/copy-history.test.js and verify PASS.

- [ ] Step 5: Commit with git commit -m "feat: link inventory copies to audit history".

### Task 8: Full verification and handoff

**Files:** Only files required by a failing verification command; do not change unrelated features.

- [ ] Step 1: Inspect the final diff.

  Run: git status --short; git diff --check; git diff HEAD~7 --stat

  Expected: no whitespace errors; the unrelated upload remains untracked and unstaged.

- [ ] Step 2: Run the full backend suite.

  Run: backend\vendor\bin\phpunit

  Expected: exit code 0, zero failures/errors/warnings/risky tests.

- [ ] Step 3: Run the full frontend suite.

  Run: npm test

  Expected: exit code 0 and all Node tests pass.

- [ ] Step 4: Run PHPStan level 9.

  Run: backend\vendor\bin\phpstan analyse --configuration=phpstan.neon

  Expected: exit code 0 with no reported errors. If dependencies are unavailable, report the exact command output.

- [ ] Step 5: Verify every acceptance criterion from the design: staff-only page/API, five statuses, required Lost/Damaged reasons, acquisition/loan/return/print/archive/restore/delete events, idempotent backfill, immutable UI, escaped timeline values, and unchanged existing circulation behavior.

- [ ] Step 6: Commit only verified fixes and report exact evidence.

  Run: git status --short; git log -10 --oneline; git diff --check

  Do not claim completion until fresh verification output confirms it.
