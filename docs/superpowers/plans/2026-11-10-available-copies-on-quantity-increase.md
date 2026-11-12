# Available Copies on Quantity Increase Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow normalized catalog quantity increases while preserving borrowed copies and creating each new physical copy as available and unloaned.

**Architecture:** Keep the existing atomic `PdoBookRepository::updateTitle()` synchronization flow. Change only the quantity-increase insert branch so new copies use fixed inventory state (`Available`, with null loan dates), independent of the submitted title-level status. Lock the behavior with a repository-level SQLite regression test.

**Tech Stack:** PHP 8.2+, PDO with SQLite/MySQL support, PHPUnit 11.5, PHPStan 2.1, Composer.

## Global Constraints

- The operation remains allowed even when existing copies are borrowed.
- Every newly created physical copy is `Available`.
- Newly created copies have no `due_date` or `return_date`.
- Existing copies keep their current status and loan dates unchanged.
- The title-level submitted `status` does not control the status of new copies during an update.
- No frontend or payload changes are required.
- No schema migration or borrowing/return workflow changes are allowed for this fix.
- Preserve the existing transaction and rollback behavior.
- Run the targeted PHPUnit test first, then `composer run quality` from `backend/`.

---

### Task 1: Guard quantity increases with a failing repository regression test

**Files:**
- Modify: `backend/tests/Unit/Infrastructure/PdoBookRepositoryTest.php` (add one normalized-update test near the existing quantity synchronization tests)
- Read: `docs/superpowers/specs/2026-11-10-available-copies-on-quantity-increase-design.md`

**Interfaces:**
- Consumes: `PdoBookRepository::update(int $id, BookMutationRequest $request): void` and the existing SQLite test conventions in `PdoBookRepositoryTest`.
- Produces: A regression test that fails against the current implementation because the newly inserted copy inherits `status = 'Borrowed'`.

- [ ] **Step 1: Write the failing test**

Add this method after `testNormalizedUpdatePersistsQuantityAndCreatesMissingCopies()`:

```php
public function testNormalizedUpdateCreatesNewCopiesAsAvailableWhenExistingCopyIsBorrowed(): void
{
    $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY AUTOINCREMENT, isbn VARCHAR(30), title VARCHAR(200) NOT NULL, author VARCHAR(150), publisher VARCHAR(150), description TEXT, cover_file VARCHAR(255), category_name VARCHAR(100), quantity INTEGER NOT NULL, created_at DATETIME)');
    $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY AUTOINCREMENT, title_id INTEGER NOT NULL, barcode VARCHAR(50) NOT NULL, accession_no VARCHAR(50), floor_no VARCHAR(20), section_name VARCHAR(80), shelf_no VARCHAR(20), row_no VARCHAR(20), due_date DATE, return_date DATE, status VARCHAR(20) NOT NULL, deleted_at DATETIME)');
    $this->pdo->exec("INSERT INTO book_titles (id, title, author, category_name, quantity) VALUES (1, 'Clean Code', 'Robert Martin', 'Computer Science', 2)");
    $this->pdo->exec("INSERT INTO book_copies (id, title_id, barcode, accession_no, due_date, return_date, status) VALUES (11, 1, 'COPY-1', 'ACC-1', '2026-11-12', NULL, 'Borrowed'), (12, 1, 'COPY-2', 'ACC-2', NULL, NULL, 'Available')");

    (new PdoBookRepository($this->pdo))->update(
        1,
        new BookMutationRequest(title: 'Clean Code', quantity: 3, status: 'Borrowed'),
    );

    self::assertSame(3, (int) $this->pdo->query('SELECT quantity FROM book_titles WHERE id = 1')->fetchColumn());
    self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM book_copies WHERE title_id = 1 AND deleted_at IS NULL')->fetchColumn());

    $borrowed = $this->pdo->query("SELECT status, due_date, return_date FROM book_copies WHERE id = 11")->fetch(PDO::FETCH_ASSOC);
    self::assertIsArray($borrowed);
    self::assertSame('Borrowed', $borrowed['status']);
    self::assertSame('2026-11-12', $borrowed['due_date']);
    self::assertNull($borrowed['return_date']);

    $newCopy = $this->pdo->query('SELECT status, due_date, return_date FROM book_copies WHERE title_id = 1 ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    self::assertIsArray($newCopy);
    self::assertSame('Available', $newCopy['status']);
    self::assertNull($newCopy['due_date']);
    self::assertNull($newCopy['return_date']);
}
```

- [ ] **Step 2: Run the new test and verify it fails for the intended reason**

Run from the repository root:

```powershell
Set-Location backend
php vendor/bin/phpunit tests/Unit/Infrastructure/PdoBookRepositoryTest.php --filter testNormalizedUpdateCreatesNewCopiesAsAvailableWhenExistingCopyIsBorrowed
```

Expected result: `FAIL`, with the new-copy assertion reporting that the actual status is `Borrowed` instead of `Available`. If the test errors before the assertion, fix the test setup or syntax and rerun until it reaches that expected failure.

### Task 2: Make quantity-increase copies available and unloaned

**Files:**
- Modify: `backend/src/Infrastructure/Persistence/PdoBookRepository.php:441-458` (the insert branch inside `updateTitle()`)
- Test: `backend/tests/Unit/Infrastructure/PdoBookRepositoryTest.php` (the regression test from Task 1)

**Interfaces:**
- Consumes: The failing repository regression test and the existing `updateTitle()` transaction.
- Produces: Quantity increases that create available, unloaned physical copies without changing existing copies.

- [ ] **Step 1: Change only the new-copy insert values**

Keep the existing barcode, accession, location, transaction, and audit logic. In the `$copyInsert->execute()` parameter array inside the quantity-increase loop, replace the request-driven loan fields:

```php
'due_date' => null,
'return_date' => null,
'status' => 'Available',
```

Do not change the create-title path, the existing-copy update path, quantity-decrease protection, or the surrounding transaction handling.

- [ ] **Step 2: Run the focused regression test and verify it passes**

Run:

```powershell
Set-Location backend
php vendor/bin/phpunit tests/Unit/Infrastructure/PdoBookRepositoryTest.php --filter testNormalizedUpdateCreatesNewCopiesAsAvailableWhenExistingCopyIsBorrowed
```

Expected result: `PASS` with one test and all assertions passing.

- [ ] **Step 3: Run the complete repository unit test file**

Run:

```powershell
Set-Location backend
php vendor/bin/phpunit tests/Unit/Infrastructure/PdoBookRepositoryTest.php
```

Expected result: all tests in `PdoBookRepositoryTest.php` pass, including existing create, update, quantity-decrease, copy-status, and keyword tests.

- [ ] **Step 4: Run the backend CI-equivalent quality gate**

Run:

```powershell
Set-Location backend
composer run quality
```

Expected result: PHPUnit and PHPStan both complete successfully within the repository's configured CI timeout. Preserve any unrelated pre-existing worktree changes and do not modify CI configuration.

- [ ] **Step 5: Review the final diff and commit the implementation**

Run:

```powershell
$repoPath = 'C:\Users\Adriaan M. Dimate\Desktop\development\personal\scan2borrow'
Set-Location $repoPath
git diff -- backend/src/Infrastructure/Persistence/PdoBookRepository.php backend/tests/Unit/Infrastructure/PdoBookRepositoryTest.php
git status --short
```

Confirm only the repository implementation and its regression test belong to this task, then commit them together:

```powershell
git add -- backend/src/Infrastructure/Persistence/PdoBookRepository.php backend/tests/Unit/Infrastructure/PdoBookRepositoryTest.php
git commit -m "fix: keep added copies available during borrowed updates"
```

