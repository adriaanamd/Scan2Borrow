# Bulk Borrowing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (recommended for inline work) or superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow students and teachers to select multiple titles and copies, including repeated copies of one title, and create one atomic borrowing transaction backed by title, copy, transaction, and item records.

**Architecture:** Replace the current one-row-per-book checkout dependency with normalized `book_titles`, `book_copies`, `borrowing_transactions`, and `borrowing_items` tables. A bulk borrowing service will validate and allocate copies under database locks; a shared browser cart will power student search, student dashboard, and teacher dashboard. Existing one-book payloads remain compatible by being normalized into a one-item bulk request.

**Tech Stack:** Framework-free PHP 8+, PDO/MySQL, PHPUnit, vanilla JavaScript ES modules, Bootstrap 5, Node’s built-in test runner.

## Global Constraints

- Students and teachers use the same bulk-borrow capability.
- A cart may contain different titles and multiple copies of one title.
- The borrower limit counts physical copies, not distinct titles.
- If any requested title or copy is unavailable, the complete request is rejected with no partial records.
- Approval-enabled requests reserve copies immediately; staff approves or rejects the complete transaction.
- Any database change requires a new forward migration and corresponding installation documentation.
- Existing single-book requests remain accepted as a one-item compatibility request.
- Borrower authorization, CSRF checks, parameterized SQL, and existing output escaping remain in force.

---

## File map

### Database and documentation

- Create: `sql/upgrade_bulk_borrowing.sql` — idempotent forward migration/backfill for existing installations.
- Modify: `sql/database.sql` — final schema for fresh installs.
- Modify: `README.md` — installation and upgrade order, normalized quantity model.
- Modify: `backend/tests/Feature/SchemaContractTest.php` — migration and final-schema contracts.

### Backend domain/application

- Create: `backend/src/Application/DTO/BulkBorrowRequest.php` — normalized title/quantity/barcode request.
- Create: `backend/src/Application/DTO/BulkBorrowItem.php` — one cart line.
- Create: `backend/src/Domain/Borrowing/BulkBorrowingResult.php` — transaction code, count, message, and failure state.
- Modify: `backend/src/Application/DTO/BorrowRequest.php` — preserve the old one-barcode request as a compatibility adapter.
- Modify: `backend/src/Application/Services/BorrowingService.php` — bulk validation, due-date rules, and allocation orchestration.
- Modify: `backend/src/Application/Services/ReturnService.php` — copy-item and whole-transaction returns using normalized records.
- Modify: `backend/src/Infrastructure/Persistence/BorrowingRepositoryInterface.php` — bulk allocation, transaction/item, reservation, and return contracts.
- Modify: `backend/src/Infrastructure/Persistence/PdoBorrowingRepository.php` — MySQL transaction and row-lock implementation.
- Modify: `backend/src/Infrastructure/Persistence/BorrowerPortalRepositoryInterface.php` — expose normalized dashboard/history/receipt query methods.
- Modify: `backend/src/Infrastructure/Persistence/PdoBorrowerPortalRepository.php` — title/copy/transaction joins and quantity-aware summaries.
- Modify: `backend/src/Http/Controllers/BorrowerController.php` — bulk payload parsing and copy lookup response.
- Modify: `backend/src/Http/Routing/BorrowerRouteTable.php` — student/teacher lookup routes.
- Modify: `backend/src/Infrastructure/Persistence/BookRepositoryInterface.php` — title search and barcode lookup contracts.
- Modify: `backend/src/Infrastructure/Persistence/PdoBookRepository.php` — grouped title search and available-count projection.
- Modify: `backend/src/Http/Controllers/BookController.php` — title search/lookup response shape.
- Modify: `backend/src/Infrastructure/Persistence/StaffRepositoryInterface.php` — expose grouped approval and title/copy inventory methods.
- Modify: `backend/src/Infrastructure/Persistence/PdoStaffRepository.php` — grouped approval, dashboard counts, recent activity, and copy updates.
- Modify: `backend/src/Http/Controllers/StaffController.php` — transaction-level approval/rejection payloads.
- Modify: `backend/src/Bootstrap/ApplicationFactory.php` — wire any new repositories/services.

### Backend tests

- Create: `backend/tests/Unit/Borrowing/BulkBorrowingServiceTest.php` — pure validation and policy behavior.
- Modify: `backend/tests/Unit/Borrowing/BorrowingServiceTest.php` — one-item compatibility behavior.
- Modify: `backend/tests/Unit/Borrowing/ReturnServiceTest.php` — normalized return behavior.
- Modify: `backend/tests/Unit/Infrastructure/PdoBorrowingRepositoryTest.php` — SQLite-compatible repository contract coverage.
- Modify: `backend/tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php` — title/copy/transaction projections.
- Modify: `backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php` — grouped approval and copy status transitions.
- Modify: `backend/tests/Feature/SchemaContractTest.php` — fresh/migration schema markers.
- Modify: `backend/tests/Feature/StaffDashboardContractTest.php` and related markup contracts — grouped approval markup.

### Frontend

- Create: `frontend/app/core/models/bulk-borrow-cart.js` — framework-free cart state and payload normalization.
- Create: `frontend/tests/bulk-borrow-cart.test.js` — cart behavior.
- Modify: `frontend/features/student/pages/search/student-search.page.js` — add title/quantity controls and shared cart.
- Modify: `frontend/features/student/pages/search/search.html` — cart drawer/modal and quantity controls.
- Modify: `frontend/features/student/pages/dashboard/student-dashboard.page.js` — dashboard cart entry, barcode lookup, submit, and success count.
- Modify: `frontend/features/student/pages/dashboard/dashboard.html` — bulk cart/scanner controls.
- Modify: `frontend/features/teacher/pages/dashboard/teacher-dashboard.page.js` — teacher cart entry and due-date submission.
- Modify: `frontend/features/teacher/pages/dashboard/dashboard.html` — teacher cart/scanner controls.
- Modify: `frontend/features/staff/pages/dashboard/staff-dashboard.page.js` — grouped transaction approval/rejection actions.
- Modify: `frontend/features/staff/pages/dashboard/dashboard.html` — grouped request display.
- Modify: `frontend/features/staff/pages/inventory/inventory.page.js` — title totals and copy management.
- Modify: `frontend/features/staff/pages/inventory/inventory.html` — quantity/copy controls.
- Modify: `frontend/features/staff/components/book-drawer/book-drawer.component.js` — collect initial quantity and render generated-copy feedback for the inventory drawer.
- Modify: `frontend/tests/student-pages.test.js`, `frontend/tests/teacher-services.test.js`, `frontend/tests/staff-pages.test.js`, and relevant service tests — payload and markup contracts.

---

## Task 1: Add normalized schema and migration contracts

**Files:**

- Test: `backend/tests/Feature/SchemaContractTest.php`
- Create: `sql/upgrade_bulk_borrowing.sql`
- Modify: `sql/database.sql`
- Modify: `README.md`

**Interfaces:**

- Produces tables `book_titles`, `book_copies`, `borrowing_transactions`, and `borrowing_items`.
- `book_titles.quantity` stores total physical copies; availability is calculated from non-archived `book_copies.status`.
- `book_copies.barcode` remains unique and points to one title.
- `borrowing_transactions.transaction_code` is unique; `borrowing_items` contains one row per physical copy.

- [ ] **Step 1: Write failing schema tests.** Add assertions for the four table names, `book_titles.quantity`, unique copy barcode, transaction/item foreign keys, and the new migration filename in the migration list.

```php
public function testBulkBorrowingSchemaIsPresentInFreshSchemaAndMigration(): void
{
    $base = $this->readSql('database.sql');
    $migration = $this->readSql('upgrade_bulk_borrowing.sql');

    foreach (['CREATE TABLE `book_titles`', '`quantity`', 'CREATE TABLE `book_copies`', '`barcode`',
        'CREATE TABLE `borrowing_transactions`', 'CREATE TABLE `borrowing_items`'] as $marker) {
        self::assertStringContainsString($marker, $base);
    }
    foreach (['CREATE TABLE IF NOT EXISTS `book_titles`', 'CREATE TABLE IF NOT EXISTS `book_copies`',
        'CREATE TABLE IF NOT EXISTS `borrowing_transactions`', 'CREATE TABLE IF NOT EXISTS `borrowing_items`'] as $marker) {
        self::assertStringContainsString($marker, $migration);
    }
}
```

- [ ] **Step 2: Run the focused test and verify RED.**

Run: `C:\xampp\php\php.exe backend\vendor\bin\phpunit backend\tests\Feature\SchemaContractTest.php`

Expected: FAIL because the new schema markers and migration file do not exist.

- [ ] **Step 3: Implement the final fresh-install schema.** Add the normalized tables and foreign keys to `sql/database.sql`; retain existing user and role/status semantics. Move title metadata to `book_titles`, copy metadata/status/location to `book_copies`, transaction-wide fields to `borrowing_transactions`, and return/fine fields to `borrowing_items`.

- [ ] **Step 4: Implement the forward migration.** In `sql/upgrade_bulk_borrowing.sql`, create temporary/source-safe structures, group legacy rows by non-empty ISBN or normalized title/author/publisher, copy every legacy book into `book_copies`, map legacy borrowing rows into transaction/item rows, remap title-level and copy-level dependent records, validate row counts, and only then remove obsolete legacy structures. Use `IF NOT EXISTS` where safe and document that the data conversion is one-time.

- [ ] **Step 5: Update installation documentation.** Add the migration to the README upgrade order and state that fresh installs use the final `database.sql`, while existing installs must run every listed upgrade script, including `sql/upgrade_bulk_borrowing.sql`.

- [ ] **Step 6: Run the focused test and verify GREEN.**

Run: `C:\xampp\php\php.exe backend\vendor\bin\phpunit backend\tests\Feature\SchemaContractTest.php`

Expected: PASS.

- [ ] **Step 7: Commit.**

```powershell
git add sql/database.sql sql/upgrade_bulk_borrowing.sql README.md backend/tests/Feature/SchemaContractTest.php
git commit -m "feat: add normalized bulk borrowing schema"
```

## Task 2: Add bulk request/result objects and service validation

**Files:**

- Test: `backend/tests/Unit/Borrowing/BulkBorrowingServiceTest.php`
- Modify: `backend/src/Application/DTO/BorrowRequest.php`
- Create: `backend/src/Application/DTO/BulkBorrowItem.php`
- Create: `backend/src/Application/DTO/BulkBorrowRequest.php`
- Create: `backend/src/Domain/Borrowing/BulkBorrowingResult.php`
- Modify: `backend/src/Application/Services/BorrowingService.php`
- Modify: `backend/src/Infrastructure/Persistence/BorrowingRepositoryInterface.php`

**Interfaces:**

```php
final readonly class BulkBorrowItem
{
    /** @param list<string> $barcodes */
    public function __construct(
        public int $titleId,
        public int $quantity,
        public array $barcodes = [],
    ) {}
}

final readonly class BulkBorrowRequest
{
    /** @param list<BulkBorrowItem> $items */
    public function __construct(
        public int $userId,
        public Role $role,
        public array $items,
        public ?string $requestedDueDate = null,
    ) {}
}

public function bulkBorrow(BulkBorrowRequest $request): BulkBorrowingResult;
```

- [ ] **Step 1: Write failing service tests.** Cover empty carts, zero/negative quantities, duplicate title lines, barcode-count mismatch, max-copy limit, invalid teacher dates, and successful normalization of a one-item request.

```php
public function testRejectsARequestThatWouldExceedRemainingCopyLimit(): void
{
    $this->repository->activeApprovedCount(7)->willReturn(2);

    $result = $this->service()->bulkBorrow(new BulkBorrowRequest(
        7,
        Role::STUDENT,
        [new BulkBorrowItem(12, 2)],
    ));

    self::assertFalse($result->successful());
    self::assertStringContainsString('maximum', strtolower($result->message()));
}
```

- [ ] **Step 2: Run the focused test and verify RED.**

Run: `C:\xampp\php\php.exe backend\vendor\bin\phpunit backend\tests\Unit\Borrowing\BulkBorrowingServiceTest.php`

Expected: FAIL because the DTOs, result, and `bulkBorrow` behavior do not exist.

- [ ] **Step 3: Implement minimal DTO/result types and validation.** Validate positive quantities, normalize duplicate title lines before repository allocation, count total requested copies, preserve the existing `BorrowingPolicy`, and route `borrow(BorrowRequest)` through a one-item `BulkBorrowRequest` compatibility adapter.

- [ ] **Step 4: Run the focused test and verify GREEN.**

Run: `C:\xampp\php\php.exe backend\vendor\bin\phpunit backend\tests\Unit\Borrowing\BulkBorrowingServiceTest.php`

Expected: PASS.

- [ ] **Step 5: Commit.**

```powershell
git add backend/src/Application/DTO backend/src/Domain/Borrowing backend/src/Application/Services/BorrowingService.php backend/src/Infrastructure/Persistence/BorrowingRepositoryInterface.php backend/tests/Unit/Borrowing/BulkBorrowingServiceTest.php
git commit -m "feat: validate bulk borrowing requests"
```

## Task 3: Implement atomic copy allocation and reservation

**Files:**

- Test: `backend/tests/Unit/Infrastructure/PdoBorrowingRepositoryTest.php`
- Modify: `backend/src/Infrastructure/Persistence/PdoBorrowingRepository.php`
- Modify: `backend/src/Application/Services/BorrowingService.php`
- Modify: `backend/src/Bootstrap/ApplicationFactory.php`

**Interfaces:**

```php
/** @return array{transaction_code: string, copy_count: int, title_count: int} */
public function createBulkTransaction(BulkBorrowRequest $request, DateTimeImmutable $dueDate): array;

/** @return list<array{id: int, title_id: int, barcode: string}> */
public function lookupCopyByBarcode(string $barcode): array;
```

- [ ] **Step 1: Write failing repository tests.** Build SQLite fixtures for titles/copies and assert that a three-copy cart creates one transaction, three items, and one shared transaction code; assert that a pending request reserves all copies and an immediate request borrows all copies.

```php
public function testBulkTransactionAllocatesMultipleCopiesAtomically(): void
{
    $result = $this->repository->createBulkTransaction($request, new DateTimeImmutable('2026-09-20'));

    self::assertSame(3, $result['copy_count']);
    self::assertSame(1, $this->pdo->query('SELECT COUNT(*) FROM borrowing_transactions')->fetchColumn());
    self::assertSame(3, $this->pdo->query('SELECT COUNT(*) FROM borrowing_items')->fetchColumn());
    self::assertSame(3, $this->pdo->query("SELECT COUNT(*) FROM book_copies WHERE status = 'Reserved'")->fetchColumn());
}
```

- [ ] **Step 2: Run the focused test and verify RED.**

Run: `C:\xampp\php\php.exe backend\vendor\bin\phpunit backend\tests\Unit\Infrastructure\PdoBorrowingRepositoryTest.php`

Expected: FAIL because normalized tables and repository methods are not implemented.

- [ ] **Step 3: Implement database allocation.** Start a PDO transaction; lock requested titles and explicit copies with `SELECT ... FOR UPDATE`; verify non-archived status; allocate exact barcodes first, then available copies in deterministic `id` order; reject if any line is short; insert one transaction header and one item per selected copy; set copy statuses to `Reserved` or `Borrowed`; commit; roll back on every exception.

- [ ] **Step 4: Connect the service.** Have `BorrowingService::bulkBorrow` calculate the due date, call the repository, format a count-aware success message, and return the transaction code. Never trust client availability or client total counts.

- [ ] **Step 5: Add all-or-nothing and concurrency regression tests.** Assert that one unavailable title leaves zero transaction/item rows and all prior copies `Available`; assert that exact barcodes from another borrower are rejected; assert pending reservation blocks a second allocation.

- [ ] **Step 6: Run focused backend tests and verify GREEN.**

Run: `C:\xampp\php\php.exe backend\vendor\bin\phpunit backend\tests\Unit\Borrowing\BulkBorrowingServiceTest.php backend\tests\Unit\Infrastructure\PdoBorrowingRepositoryTest.php backend\tests\Unit\Borrowing\BorrowingServiceTest.php`

Expected: PASS.

- [ ] **Step 7: Commit.**

```powershell
git add backend/src/Infrastructure/Persistence/PdoBorrowingRepository.php backend/src/Application/Services/BorrowingService.php backend/src/Bootstrap/ApplicationFactory.php backend/tests/Unit/Infrastructure/PdoBorrowingRepositoryTest.php
git commit -m "feat: allocate bulk borrowing copies atomically"
```

## Task 4: Update borrower APIs, search, portal, and returns

**Files:**

- Test: `backend/tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php`
- Test: `backend/tests/Unit/Borrowing/ReturnServiceTest.php`
- Modify: `backend/src/Http/Controllers/BorrowerController.php`
- Modify: `backend/src/Http/Routing/BorrowerRouteTable.php`
- Modify: `backend/src/Http/Controllers/BookController.php`
- Modify: `backend/src/Infrastructure/Persistence/PdoBookRepository.php`
- Modify: `backend/src/Infrastructure/Persistence/BookRepositoryInterface.php`
- Modify: `backend/src/Infrastructure/Persistence/PdoBorrowerPortalRepository.php`
- Modify: `backend/src/Infrastructure/Persistence/PdoBorrowingRepository.php`
- Modify: `backend/src/Application/Services/ReturnService.php`

**Interfaces:**

- `GET /api/student/borrow/lookup?barcode=...` and `GET /api/teacher/borrow/lookup?barcode=...` return `{title_id,title,author,barcode,available_quantity,already_borrowed}`.
- `POST /api/student/borrow`, `/api/student/dashboard`, and `/api/teacher/dashboard` accept `items[]` plus `due_date`; old `book_barcode` remains supported.
- Dashboard/history rows expose `transaction_code`, title, copy barcode, status, due date, and return date.
- Receipt responses contain every item under the transaction.

- [ ] **Step 1: Write failing API/portal/return tests.** Assert bulk parsing, lookup authorization, grouped title availability, receipt item count, single-copy barcode return, and transaction-code return of all active items.

- [ ] **Step 2: Run focused tests and verify RED.**

Run: `C:\xampp\php\php.exe backend\vendor\bin\phpunit backend\tests\Unit\Infrastructure\PdoBorrowerPortalRepositoryTest.php backend\tests\Unit\Borrowing\ReturnServiceTest.php backend\tests\Feature\BookControllerTest.php`

Expected: FAIL on missing normalized query columns/routes and bulk payload behavior.

- [ ] **Step 3: Implement controller parsing and lookup.** Parse `items` from JSON/FormData, validate title IDs and quantities through `BulkBorrowRequest`, preserve `book_barcode` compatibility, and expose the lookup route only to student/teacher session roles with existing CSRF requirements on POST.

- [ ] **Step 4: Implement grouped catalog search.** Return one row per title with `quantity`, `available_quantity`, and title metadata; retain search/filter/sort behavior and expose exact-copy barcode only when lookup is requested.

- [ ] **Step 5: Update portals and returns.** Join transactions/items/copies/titles for current loans, history, and receipts. A barcode return updates one item and its copy; a transaction return updates every active item; copy status becomes `Available`; a transaction becomes complete only after all items are returned.

- [ ] **Step 6: Run focused tests and verify GREEN.**

Run: `C:\xampp\php\php.exe backend\vendor\bin\phpunit backend\tests\Unit\Infrastructure\PdoBorrowerPortalRepositoryTest.php backend\tests\Unit\Borrowing\ReturnServiceTest.php backend\tests\Feature\BookControllerTest.php backend\tests\Feature\BorrowerMarkupParityTest.php`

Expected: PASS.

- [ ] **Step 7: Commit.**

```powershell
git add backend/src/Http backend/src/Infrastructure/Persistence backend/src/Application/Services/ReturnService.php backend/tests
git commit -m "feat: expose bulk borrowing through borrower APIs"
```

## Task 5: Build the shared frontend cart

**Files:**

- Test: `frontend/tests/bulk-borrow-cart.test.js`
- Create: `frontend/app/core/models/bulk-borrow-cart.js`

**Interfaces:**

```js
export class BulkBorrowCart {
  addTitle(book, quantity = 1, barcode = "");
  setQuantity(titleId, quantity);
  removeTitle(titleId);
  clear();
  totalQuantity();
  items(); // [{ title_id, quantity, barcodes }]
  has(titleId);
}
```

- [ ] **Step 1: Write failing cart tests.** Cover adding titles, merging repeated additions, duplicate scans, quantity clamping to available count, removal, clearing, total-copy count, and serialization with exact barcodes.

```js
test("repeated scans merge into one title line and retain barcodes", () => {
  const cart = new BulkBorrowCart();
  cart.addTitle({ id: 12, title: "Clean Code", available_quantity: 3 }, 1, "C-01");
  cart.addTitle({ id: 12, title: "Clean Code", available_quantity: 3 }, 1, "C-02");

  assert.deepEqual(cart.items(), [{ title_id: 12, quantity: 2, barcodes: ["C-01", "C-02"] }]);
});
```

- [ ] **Step 2: Run the focused test and verify RED.**

Run: `npm test -- frontend/tests/bulk-borrow-cart.test.js`

Expected: FAIL because the cart module does not exist.

- [ ] **Step 3: Implement the minimal cart.** Keep state title-keyed, copy caller-provided metadata for rendering, cap quantity at `available_quantity`, deduplicate barcodes, and return a fresh serializable array.

- [ ] **Step 4: Run the focused test and verify GREEN.**

Run: `npm test -- frontend/tests/bulk-borrow-cart.test.js`

Expected: PASS.

- [ ] **Step 5: Commit.**

```powershell
git add frontend/app/core/models/bulk-borrow-cart.js frontend/tests/bulk-borrow-cart.test.js
git commit -m "feat: add shared bulk borrowing cart"
```

## Task 6: Integrate student search and dashboard borrowing

**Files:**

- Test: `frontend/tests/student-pages.test.js`
- Test: `frontend/tests/student-services.test.js`
- Modify: `frontend/features/student/pages/search/student-search.page.js`
- Modify: `frontend/features/student/pages/search/search.html`
- Modify: `frontend/features/student/pages/dashboard/student-dashboard.page.js`
- Modify: `frontend/features/student/pages/dashboard/dashboard.html`

**Interfaces:**

- Search and dashboard instantiate `BulkBorrowCart` and submit `{ action: "borrow", items, csrf, due_date }`.
- The cart modal has `#bulkBorrowCart`, `#bulkBorrowItems`, `#bulkBorrowCount`, and `#bulkBorrowError` hooks.
- Search cards use `data-title-id`, `data-available-quantity`, and `data-book-barcode` metadata.

- [ ] **Step 1: Write failing frontend contract tests.** Assert that the search page renders availability/quantity controls, the dashboard submits `items`, and the success response displays the server-provided copy count and transaction code.

- [ ] **Step 2: Run focused tests and verify RED.**

Run: `npm test -- frontend/tests/student-pages.test.js frontend/tests/student-services.test.js`

Expected: FAIL because the current forms only submit one `book_barcode`.

- [ ] **Step 3: Implement search integration.** Import the cart, render one title card with available count, add/increment/decrement controls, open the shared cart modal, and resolve a scanned barcode through the lookup endpoint before adding it.

- [ ] **Step 4: Implement dashboard integration.** Replace the singular borrow input behavior with the same cart controls and scanner entry; preserve the existing return form; submit one bulk payload; retain existing success receipt behavior while showing total copies.

- [ ] **Step 5: Implement all-or-nothing error rendering.** Keep the cart open on server failure, show the title/quantity error, and clear the cart only after a successful transaction.

- [ ] **Step 6: Run focused tests and verify GREEN.**

Run: `npm test -- frontend/tests/student-pages.test.js frontend/tests/student-services.test.js`

Expected: PASS.

- [ ] **Step 7: Commit.**

```powershell
git add frontend/features/student frontend/tests/student-pages.test.js frontend/tests/student-services.test.js
git commit -m "feat: add bulk borrowing to student flows"
```

## Task 7: Integrate teacher borrowing with due-date support

**Files:**

- Test: `frontend/tests/teacher-services.test.js`
- Modify: `frontend/features/teacher/services/dashboard.service.js`
- Modify: `frontend/features/teacher/pages/dashboard/teacher-dashboard.page.js`
- Modify: `frontend/features/teacher/pages/dashboard/dashboard.html`

- [ ] **Step 1: Write failing teacher payload tests.** Assert that `TeacherDashboardService.borrowBulk(items, dueDate)` posts one request with all lines and preserves an optional due date.

```js
await dashboard.borrowBulk(
  [{ title_id: 12, quantity: 2, barcodes: ["C-01", "C-02"] }],
  "2026-09-20",
);
assert.deepEqual(request.body, {
  action: "borrow",
  items: [{ title_id: 12, quantity: 2, barcodes: ["C-01", "C-02"] }],
  due_date: "2026-09-20",
});
```

- [ ] **Step 2: Run the focused test and verify RED.**

Run: `npm test -- frontend/tests/teacher-services.test.js`

Expected: FAIL because only singular `borrow(bookBarcode, dueDate)` exists.

- [ ] **Step 3: Implement the teacher service/page/cart integration.** Add `borrowBulk`, use the shared cart/scanner, preserve the teacher date control and return behavior, and render the one-transaction success message.

- [ ] **Step 4: Run focused tests and verify GREEN.**

Run: `npm test -- frontend/tests/teacher-services.test.js frontend/tests/student-pages.test.js`

Expected: PASS.

- [ ] **Step 5: Commit.**

```powershell
git add frontend/features/teacher frontend/tests/teacher-services.test.js
git commit -m "feat: add bulk borrowing to teacher flow"
```

## Task 8: Update staff approvals and inventory quantities/copies

**Files:**

- Test: `backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php`
- Test: `backend/tests/Feature/StaffDashboardContractTest.php`
- Test: `frontend/tests/staff-pages.test.js`
- Modify: `backend/src/Infrastructure/Persistence/PdoStaffRepository.php`
- Modify: `backend/src/Http/Controllers/StaffController.php`
- Modify: `frontend/features/staff/pages/dashboard/staff-dashboard.page.js`
- Modify: `frontend/features/staff/pages/dashboard/dashboard.html`
- Modify: `frontend/features/staff/pages/inventory/inventory.page.js`
- Modify: `frontend/features/staff/pages/inventory/inventory.html`
- Modify: `frontend/features/staff/components/book-drawer/book-drawer.component.js`

- [ ] **Step 1: Write failing approval tests.** Assert pending rows are grouped by `transaction_code`, approval reserves-to-borrow transitions all items, rejection releases all copies, and any invalid item causes a rollback.

- [ ] **Step 2: Run focused tests and verify RED.**

Run: `C:\xampp\php\php.exe backend\vendor\bin\phpunit backend\tests\Unit\Infrastructure\PdoStaffRepositoryTest.php backend\tests\Feature\StaffDashboardContractTest.php`; then `npm test -- frontend/tests/staff-pages.test.js`

Expected: FAIL because approval is currently per legacy borrowing row and inventory is copy-row-only.

- [ ] **Step 3: Implement transaction-level approval.** Staff repository methods accept a transaction ID/code, lock the header/items, update every item/copy together, mark related notifications read, and roll back on any failure. Dashboard aggregates pending counts by transaction rather than item.

- [ ] **Step 4: Implement grouped approval markup/actions.** Show borrower, transaction code, title list, copy count, due date, and approve/reject buttons. Keep destructive rejection confirmation through `Scan2BorrowConfirmation`.

- [ ] **Step 5: Implement title/copy inventory management.** Group inventory by title with total/available/reserved/borrowed counts; allow initial quantity creation with generated unique barcodes, and provide copy-level assignment/edit/archive/restore/delete actions with active-loan protections.

- [ ] **Step 6: Run focused tests and verify GREEN.**

Run: `C:\xampp\php\php.exe backend\vendor\bin\phpunit backend\tests\Unit\Infrastructure\PdoStaffRepositoryTest.php backend\tests\Feature\StaffDashboardContractTest.php`; then `npm test -- frontend/tests/staff-pages.test.js`

Expected: PASS.

- [ ] **Step 7: Commit.**

```powershell
git add backend/src/Infrastructure/Persistence/PdoStaffRepository.php backend/src/Http/Controllers/StaffController.php backend/tests frontend/features/staff
git commit -m "feat: manage bulk approvals and copy inventory"
```

## Task 9: Complete contracts, documentation, and compatibility cleanup

**Files:**

- Modify: `backend/tests/Feature/BookControllerTest.php`
- Modify: `backend/tests/Feature/BorrowerMarkupParityTest.php`
- Modify: `backend/tests/Feature/InventoryBrowserParityTest.php`
- Modify: `backend/tests/Feature/StaffDashboardFrontendContractTest.php`
- Modify: `frontend/tests/feature-exports.test.js`
- Modify: `frontend/parity/page-matrix.md` if endpoint/page ownership changes.
- Modify: `README.md` if test/setup commands need refinement.

- [ ] **Step 1: Add compatibility assertions.** Confirm old one-barcode posts create one transaction item, existing receipt URLs remain valid, student/teacher routes remain protected, and no guest flow was changed.

- [ ] **Step 2: Run the complete JavaScript suite.**

Run: `npm test`

Expected: all tests pass with zero failures, skips, or cancellations.

- [ ] **Step 3: Run the complete PHP suite.**

Run: `C:\xampp\php\php.exe backend\vendor\bin\phpunit`

Expected: all available tests pass; MySQL-only tests may explicitly skip only when the configured database is unavailable.

- [ ] **Step 4: Run static and migration checks.**

```powershell
git diff --check
rg -n -i 'book_barcode|books\s+JOIN|FROM books|JOIN books' backend/src frontend/features sql
git status --short
```

Expected: legacy references are either intentional migration/compatibility code or updated; diff check is clean; only intended files are tracked as changed.

- [ ] **Step 5: Commit final contracts/docs.**

```powershell
git add backend/tests frontend/tests frontend/parity README.md
git commit -m "test: verify bulk borrowing compatibility"
```

## Execution checkpoints

After Tasks 1–3, verify the migration and atomic backend allocation before touching UI. After Tasks 4–7, verify both borrower roles can submit the same payload shape. After Task 8, verify staff approval and inventory are consistent with reservations. Task 9 is the final evidence pass before claiming completion.
