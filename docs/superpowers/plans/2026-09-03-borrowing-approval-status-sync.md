# Borrowing Approval Status Synchronization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make approved normalized borrowing requests display as `Borrowed` for students, teachers, and Admin while repairing existing inconsistent rows.

**Architecture:** Keep the current transaction-level approval entry point in `PdoStaffRepository::changeBorrowing()`. Synchronize active `borrowing_items` inside the same PDO transaction as the transaction header and physical-copy updates, because borrower portals derive their displayed status from both header and item state. Add an idempotent SQL repair migration for rows created before the code fix; no frontend API or renderer change is required.

**Tech Stack:** PHP 8.2+, PDO/MySQL, SQLite-backed PHPUnit unit tests, vanilla JavaScript ES modules, Node’s built-in test runner.

## Global Constraints

- Students and teachers use the same bulk-borrow capability.
- Approval-enabled requests reserve copies immediately; staff approves or rejects the complete transaction.
- Borrower authorization, CSRF checks, parameterized SQL, and existing output escaping remain in force.
- The transaction/item model remains the source of truth for normalized borrowing.
- Legacy one-row borrowing approval behavior remains unchanged.
- The repair migration must be safe to run more than once.

---

## File Map

- Modify `backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php` with normalized approval and rejection regression fixtures.
- Modify `backend/src/Infrastructure/Persistence/PdoStaffRepository.php` so active normalized item statuses follow the staff decision.
- Create `sql/upgrade_approval_status_sync.sql` to repair existing normalized rows.
- Modify `backend/tests/Feature/SchemaContractTest.php` to require the repair migration and its key statements.
- Modify `README.md` to add the repair migration to the installation order and explain when to run it.
- Modify `docs/SCAN2BORROW_SYSTEM_GUIDE.md` to include the repair migration in operational setup and troubleshooting.
- Run the existing student/teacher frontend contract tests without changing frontend source, because both renderers already support `Borrowed`.

## Task 1: Add a failing normalized approval regression

**Files:**
- Modify: `backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php`
- Test integration: `backend/tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php` is an existing reference for the borrower dashboard projection; no production frontend files are changed in this task.

**Interfaces:**
- Consumes: `PdoStaffRepository::approveBorrowing(int $borrowingId, int $staffId)` and `PdoBorrowerPortalRepository::dashboard(int $userId)`.
- Produces: a regression that proves one staff approval synchronizes transaction, item, copy, pending-workload, and borrower-dashboard state.

- [ ] **Step 1: Add the normalized fixture helper and approval test.**

Add `use App\Infrastructure\Persistence\PdoBorrowerPortalRepository;` to the test imports and add the following methods to `PdoStaffRepositoryTest`:

```php
private function normalizedApprovalFixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, barcode TEXT, firstname TEXT, middlename TEXT, lastname TEXT, department TEXT, position TEXT, course TEXT, year_level TEXT, photo TEXT, role TEXT)');
    $pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY, title TEXT, author TEXT, category_name TEXT, created_at TEXT)');
    $pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY, title_id INTEGER, barcode TEXT, status TEXT, due_date TEXT, deleted_at TEXT)');
    $pdo->exec('CREATE TABLE borrowing_transactions (id INTEGER PRIMARY KEY, transaction_code TEXT, user_id INTEGER, processed_by INTEGER, approval_status TEXT, borrow_date TEXT, due_date TEXT, return_date TEXT, status TEXT, fine_amount NUMERIC, requested_at TEXT, approved_at TEXT, approved_by INTEGER)');
    $pdo->exec('CREATE TABLE borrowing_items (id INTEGER PRIMARY KEY, transaction_id INTEGER, copy_id INTEGER, return_date TEXT, status TEXT, fine_amount NUMERIC)');
    $pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INTEGER, type TEXT, title TEXT, message TEXT, related_id INTEGER, is_read INTEGER, created_at TEXT)');
    $pdo->exec("INSERT INTO users VALUES (2, 'STU-1', 'Grace', NULL, 'Hopper', 'IT', NULL, 'CS', '4', NULL, 'student')");
    $pdo->exec("INSERT INTO book_titles VALUES (1, 'Clean Code', 'Martin', 'Computer Science', '2026-08-01')");
    $pdo->exec("INSERT INTO book_copies VALUES (1, 1, 'COPY-1', 'Reserved', '2026-09-10', NULL)");
    $pdo->exec("INSERT INTO borrowing_transactions VALUES (1, 'TX-PENDING', 2, NULL, 'pending', '2026-09-03 10:00:00', '2026-09-10', NULL, 'Pending', 0, '2026-09-03 10:00:00', NULL, NULL)");
    $pdo->exec("INSERT INTO borrowing_items VALUES (1, 1, 1, NULL, 'Pending', 0)");
    return $pdo;
}

public function testNormalizedApprovalSynchronizesAllBorrowingStatusProjections(): void
{
    $pdo = $this->normalizedApprovalFixture();
    $repository = new PdoStaffRepository($pdo);

    $repository->approveBorrowing(1, 99);

    $transaction = $pdo->query('SELECT approval_status, status FROM borrowing_transactions WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    $itemStatus = $pdo->query('SELECT status, return_date FROM borrowing_items WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    $copyStatus = $pdo->query('SELECT status FROM book_copies WHERE id = 1')->fetchColumn();
    self::assertSame(['approval_status' => 'approved', 'status' => 'Borrowed'], $transaction);
    self::assertSame('Borrowed', $itemStatus['status']);
    self::assertNull($itemStatus['return_date']);
    self::assertSame('Borrowed', $copyStatus);
    self::assertSame([], $repository->pendingBorrowings());

    $dashboard = (new PdoBorrowerPortalRepository($pdo))->dashboard(2);
    self::assertSame('Borrowed', $dashboard['current_loans'][0]['status']);
}
```

- [ ] **Step 2: Run the new test and verify the failure is the missing item transition.**

Run:

```powershell
& 'C:\xampp\php\php.exe' 'backend\vendor\bin\phpunit' --configuration='backend\phpunit.xml' --colors=never backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php --filter NormalizedApprovalSynchronizes
```

Expected: FAIL because the transaction and copy become `Borrowed`, but `borrowing_items.status` remains `Pending`, causing the borrower dashboard assertion to receive `Pending`.

## Task 2: Synchronize normalized items during approval and rejection

**Files:**
- Modify: `backend/src/Infrastructure/Persistence/PdoStaffRepository.php:789-867`
- Modify: `backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php`

**Interfaces:**
- Consumes: the existing guarded transaction update in `changeBorrowing()`.
- Produces: approved active items with `Borrowed` status; rejected active items with `Returned` status and a return timestamp; unchanged legacy fallback behavior.

- [ ] **Step 1: Add a failing rejection regression using the same fixture.**

Add this test after the approval test:

```php
public function testNormalizedRejectionClosesItemsAndLeavesCopiesAvailable(): void
{
    $pdo = $this->normalizedApprovalFixture();
    $repository = new PdoStaffRepository($pdo);

    $repository->rejectBorrowing(1, 99);

    $transaction = $pdo->query('SELECT approval_status, status FROM borrowing_transactions WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    $item = $pdo->query('SELECT status, return_date FROM borrowing_items WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    $copyStatus = $pdo->query('SELECT status FROM book_copies WHERE id = 1')->fetchColumn();
    self::assertSame(['approval_status' => 'rejected', 'status' => 'Returned'], $transaction);
    self::assertSame('Returned', $item['status']);
    self::assertNotNull($item['return_date']);
    self::assertSame('Available', $copyStatus);
    self::assertSame([], (new PdoBorrowerPortalRepository($pdo))->dashboard(2)['current_loans']);
}
```

- [ ] **Step 2: Run both new tests to confirm the rejection failure is isolated.**

Run:

```powershell
& 'C:\xampp\php\php.exe' 'backend\vendor\bin\phpunit' --configuration='backend\phpunit.xml' --colors=never backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php --filter Normalized
```

Expected: FAIL in the rejection test because the current implementation leaves the item `Pending` and its `return_date` null.

- [ ] **Step 3: Implement the minimal item-state synchronization.**

In the normalized branch of `PdoStaffRepository::changeBorrowing()`, immediately after the successful guarded `borrowing_transactions` update and before updating `book_copies`, add:

```php
$itemStatement = $this->pdo->prepare($approve
    ? "UPDATE borrowing_items SET status = 'Borrowed' WHERE transaction_id = :transaction_id AND return_date IS NULL"
    : "UPDATE borrowing_items SET status = 'Returned', return_date = CURRENT_TIMESTAMP WHERE transaction_id = :transaction_id AND return_date IS NULL"
);
$itemStatement->execute(['transaction_id' => $borrowingId]);
```

Keep the existing `rowCount() < 1` early return, copy update, notification update, audit loop, commit, and exception rollback unchanged. The new update is inside the existing transaction, so a failed item update rolls back the header and copy changes.

- [ ] **Step 4: Run the focused tests and verify the green result.**

Run:

```powershell
& 'C:\xampp\php\php.exe' 'backend\vendor\bin\phpunit' --configuration='backend\phpunit.xml' --colors=never backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php backend/tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php
```

Expected: PASS for the existing focused suite plus both new normalized tests; the approved borrower projection is `Borrowed`, and the rejected request is absent from active loans.

- [ ] **Step 5: Commit the backend transition and regression tests.**

```powershell
git add -- backend/src/Infrastructure/Persistence/PdoStaffRepository.php backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php
git commit -m "fix: synchronize normalized approval item statuses"
```

## Task 3: Add the existing-data repair migration and schema contract

**Files:**
- Create: `sql/upgrade_approval_status_sync.sql`
- Modify: `backend/tests/Feature/SchemaContractTest.php:53-66`

**Interfaces:**
- Consumes: normalized tables created by `sql/upgrade_bulk_borrowing.sql`.
- Produces: an idempotent repair script that makes pre-existing approved items `Borrowed` and rejected active items closed as `Returned`.

- [ ] **Step 1: Add a failing migration contract test.**

Add this test to `SchemaContractTest`:

```php
public function testApprovalStatusRepairMigrationIsAvailableAndIdempotentByPredicate(): void
{
    $migration = str_replace(["\r\n", "\r"], "\n", $this->readSql('upgrade_approval_status_sync.sql'));

    foreach ([
        'USE `scan2borrow_2.0`;',
        'UPDATE `borrowing_items` AS item_record',
        'JOIN `borrowing_transactions` AS transaction_record',
        "transaction_record.`approval_status` = 'approved'",
        "item_record.`status` = 'Pending'",
        "item_record.`status` = 'Borrowed'",
        "transaction_record.`approval_status` = 'rejected'",
        "item_record.`status` = 'Returned'",
        'CURRENT_TIMESTAMP',
    ] as $marker) {
        self::assertStringContainsString($marker, $migration, 'Approval status repair migration missing marker: ' . $marker);
    }
}
```

Also append `'upgrade_approval_status_sync.sql'` to the filename list in `testAllExistingUpgradeScriptsRemainAvailable()`.

- [ ] **Step 2: Run the contract test and verify it fails because the migration does not exist.**

Run:

```powershell
& 'C:\xampp\php\php.exe' 'backend\vendor\bin\phpunit' --configuration='backend\phpunit.xml' --colors=never backend/tests/Feature/SchemaContractTest.php --filter ApprovalStatusRepair
```

Expected: FAIL with the missing migration path assertion from `readSql()`.

- [ ] **Step 3: Create the idempotent repair migration.**

Create `sql/upgrade_approval_status_sync.sql` with:

```sql
-- ============================================================================
-- Scan2Borrow: Repair normalized borrowing item statuses after approval.
-- Run after upgrade_bulk_borrowing.sql. Safe to run more than once.
-- ============================================================================

USE `scan2borrow_2.0`;

UPDATE `borrowing_items` AS item_record
JOIN `borrowing_transactions` AS transaction_record
  ON transaction_record.`id` = item_record.`transaction_id`
SET item_record.`status` = 'Borrowed'
WHERE transaction_record.`approval_status` = 'approved'
  AND item_record.`return_date` IS NULL
  AND item_record.`status` = 'Pending';

UPDATE `borrowing_items` AS item_record
JOIN `borrowing_transactions` AS transaction_record
  ON transaction_record.`id` = item_record.`transaction_id`
SET item_record.`status` = 'Returned',
    item_record.`return_date` = COALESCE(item_record.`return_date`, CURRENT_TIMESTAMP)
WHERE transaction_record.`approval_status` = 'rejected'
  AND item_record.`return_date` IS NULL;
```

- [ ] **Step 4: Run the schema contract tests and verify the migration is recognized.**

Run:

```powershell
& 'C:\xampp\php\php.exe' 'backend\vendor\bin\phpunit' --configuration='backend\phpunit.xml' --colors=never backend/tests/Feature/SchemaContractTest.php
```

Expected: PASS, including the new migration contract and complete migration-file list.

- [ ] **Step 5: Commit the migration and contract coverage.**

```powershell
git add -- sql/upgrade_approval_status_sync.sql backend/tests/Feature/SchemaContractTest.php
git commit -m "fix: add borrowing approval status repair migration"
```

## Task 4: Document migration order and verify all role surfaces

**Files:**
- Modify: `README.md:8,39`
- Modify: `docs/SCAN2BORROW_SYSTEM_GUIDE.md:149-156,722-728`
- Test: `frontend/tests/*.test.js` existing suite, with no frontend source change.

**Interfaces:**
- Consumes: `sql/upgrade_approval_status_sync.sql` and the synchronized backend response.
- Produces: installation/troubleshooting instructions and verified student, teacher, and Admin behavior.

- [ ] **Step 1: Update README migration order.**

Change the fresh-install order in `README.md` so `upgrade_approval_status_sync.sql` follows `upgrade_bulk_borrowing.sql`, and change the existing-database order so the same migration follows `upgrade_bulk_borrowing.sql`. Extend the bulk-borrowing paragraph to state that the repair migration is needed when an older approved request still has a `Pending` item row.

The resulting order must include:

```text
upgrade_bulk_borrowing.sql, upgrade_approval_status_sync.sql, upgrade_barcode_printing.sql
```

- [ ] **Step 2: Update the system guide setup and migration map.**

Add `'sql\upgrade_approval_status_sync.sql'` immediately after `'sql\upgrade_bulk_borrowing.sql'` in the `$upgradeFiles` array in section 4, and add this row in the SQL map:

```text
sql/upgrade_approval_status_sync.sql  Repair approved/rejected normalized item statuses
```

Add a troubleshooting section after the notification troubleshooting section:

```text
### Approved request still shows Pending

Run the repair migration after the normalized bulk-borrowing migration:

Get-Content -Raw 'sql\upgrade_approval_status_sync.sql' | & 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root

Verify that no active approved item remains Pending:

SELECT COUNT(*) AS inconsistent_rows
FROM borrowing_items bi
JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
WHERE bt.approval_status = 'approved'
  AND bi.return_date IS NULL
  AND bi.status = 'Pending';
```

The expected count is `0`.

- [ ] **Step 3: Run the full frontend contract suite.**

Run:

```powershell
npm test
```

Expected: PASS. In particular, existing student and teacher dashboard contracts continue to recognize the `Borrowed` badge, and no Admin approval markup/API contract regresses.

- [ ] **Step 4: Run the full backend PHPUnit suite.**

Run:

```powershell
& 'C:\xampp\php\php.exe' 'backend\vendor\bin\phpunit' --configuration='backend\phpunit.xml' --colors=never
```

Expected: PASS with zero failures, errors, risky tests, or warnings.

- [ ] **Step 5: Run PHPStan and PHP syntax checks.**

Run:

```powershell
& 'C:\xampp\php\php.exe' 'backend\vendor\bin\phpstan' analyse --configuration='backend\phpstan.neon' --level=9 --no-progress
$php = 'C:\xampp\php\php.exe'
$phpFiles = @(rg --files 'backend\src' 'backend\tests' | Where-Object { $_ -match '\.php$' })
$failed = @()
foreach ($file in $phpFiles) {
    & $php -l $file 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) { $failed += $file }
}
Write-Host "PHP files checked: $($phpFiles.Count)"
Write-Host "PHP lint failures: $($failed.Count)"
if ($failed.Count -gt 0) { $failed; throw 'PHP lint failed.' }
```

Expected: PHPStan exits `0`; lint reports `PHP lint failures: 0`.

- [ ] **Step 6: Apply the repair migration to the local database.**

Run the migration against the local `scan2borrow_2.0` database:

```powershell
Get-Content -Raw 'sql\upgrade_approval_status_sync.sql' | & 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root
```

Expected: the command exits `0` without SQL errors.

- [ ] **Step 7: Verify the live repair and all status projections.**

Run:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root --database='scan2borrow_2.0' -e "SELECT COUNT(*) AS inconsistent_rows FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id WHERE bt.approval_status = 'approved' AND bi.return_date IS NULL AND bi.status = 'Pending'; SELECT bt.transaction_code, bt.approval_status, bt.status, bi.status AS item_status, c.status AS copy_status FROM borrowing_transactions bt JOIN borrowing_items bi ON bi.transaction_id = bt.id JOIN book_copies c ON c.id = bi.copy_id WHERE bt.approval_status = 'approved' ORDER BY bt.id DESC LIMIT 10;"
```

Expected: `inconsistent_rows` is `0`, and approved rows show `Borrowed` for both `bt.status`, `item_status`, and `copy_status`.

- [ ] **Step 8: Review the final diff and commit documentation.**

Run:

```powershell
git diff --check
git status --short
```

Confirm only the planned source, test, migration, and documentation files changed; leave the pre-existing untracked files untouched. Then commit:

```powershell
git add -- README.md docs/SCAN2BORROW_SYSTEM_GUIDE.md
git commit -m "docs: document borrowing approval status repair"
```

## Final verification checklist

- [ ] New normalized approval test failed before the production change.
- [ ] Approval sets transaction, item, and copy to `Borrowed`.
- [ ] Rejection closes items and releases copies.
- [ ] Admin pending workload no longer returns approved transactions.
- [ ] Borrower portal returns `Borrowed` for approved normalized loans.
- [ ] Repair migration is idempotent and removes existing inconsistent rows.
- [ ] Frontend tests pass without frontend behavior changes.
- [ ] Backend PHPUnit, PHPStan, PHP lint, and `git diff --check` pass.
