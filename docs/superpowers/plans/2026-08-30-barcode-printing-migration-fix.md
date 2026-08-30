# Barcode Printing Migration Fix Implementation Plan

> **For agentic workers:** Execute this plan task-by-task with verification checkpoints.

**Goal:** Restore the barcode-printing API by applying the existing schema migration to the configured MariaDB database and verify the real endpoint no longer returns HTTP 500.

**Architecture:** The application already owns the barcode-printing repository and routes. The failure is environmental schema drift: the configured database has the bulk-borrowing schema but not the barcode-printing migration. Apply the idempotent migration once, then exercise both authenticated GET forms through `ApplicationFactory`.

**Tech Stack:** PHP 8.2, PDO MySQL, MariaDB 10.4, PHPUnit 11.

## Global Constraints

- Apply only `sql/upgrade_barcode_printing.sql`; do not alter or reset existing data.
- Preserve the unrelated modification in `frontend/features/guest/pages/profile/guest-profile.page.js`.
- Verify migration objects, endpoint responses, focused tests, and the full backend test suite before claiming completion.

### Task 1: Confirm the precondition and apply the existing migration

**Files:**
- Read: `sql/upgrade_barcode_printing.sql`
- Read: `backend/src/Infrastructure/Database/DatabaseConfig.php`

- [ ] **Step 1: Confirm the target database and missing objects**

Use the configured PDO connection to check the MariaDB version and whether `book_copies.printed_at`, `barcode_print_batches`, and `barcode_print_batch_items` are absent.

- [ ] **Step 2: Execute the existing migration**

Run every statement from `sql/upgrade_barcode_printing.sql` against the configured database using the project’s PDO credentials. Do not rewrite the migration or run destructive SQL.

- [ ] **Step 3: Verify migration objects**

Query `SHOW COLUMNS FROM book_copies LIKE 'printed_at'`, `SHOW TABLES LIKE 'barcode_print_batches'`, and `SHOW TABLES LIKE 'barcode_print_batch_items'`; each must be present.

### Task 2: Verify the real API behavior

**Files:**
- Read: `backend/src/Bootstrap/ApplicationFactory.php`
- Read: `backend/src/Http/Controllers/BarcodePrintController.php`
- Read: `backend/src/Application/Services/BarcodePrintService.php`

- [ ] **Step 1: Exercise the authenticated title-history request**

Invoke `ApplicationFactory::create()->handle()` as an admin session for `GET /api/barcode-print-batches?title_id=64`; expect HTTP 200 and a JSON `data.history` list.

- [ ] **Step 2: Exercise the bare endpoint request**

Invoke the same application path for `GET /api/barcode-print-batches`; expect the controller’s documented client validation response, HTTP 422, not HTTP 500.

### Task 3: Run regression verification

**Files:**
- Read: `backend/tests/Feature/BarcodePrintControllerTest.php`
- Read: `backend/tests/Unit/Infrastructure/PdoBarcodePrintRepositoryTest.php`

- [ ] **Step 1: Run focused barcode tests**

Run `C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml backend\tests\Feature\BarcodePrintControllerTest.php backend\tests\Unit\Infrastructure\PdoBarcodePrintRepositoryTest.php` and require zero failures.

- [ ] **Step 2: Run the full backend suite**

Run `C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml` and report the exact result.

- [ ] **Step 3: Check the worktree**

Run `git status --short` and confirm only intentional plan/documentation changes are present in addition to the user’s pre-existing frontend edit.
