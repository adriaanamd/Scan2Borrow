# Single SQL Install Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Add one standalone sql/install.sql import for a complete fresh Scan2Borrow database while preserving the existing incremental migrations for existing installations.

**Architecture:** Build a canonical final-schema SQL snapshot rather than replaying historical ALTER TABLE statements. The snapshot creates the legacy compatibility model and normalized circulation model in dependency order, seeds the base records, and runs the normalized backfills needed by the current application. A PHPUnit schema contract protects self-containment, object coverage, seed/backfill markers, and dependency order; README documents fresh/reset imports separately from existing-database upgrades.

**Tech Stack:** MariaDB 10.4/XAMPP SQL, PHP 8.2, PHPUnit, PHPStan, Node.js native test runner, Markdown.

## Global Constraints

- Target the existing database name scan2borrow_2.0 and MariaDB/XAMPP syntax.
- Do not use SOURCE commands or depend on other files being present.
- Leave sql/database.sql and every individual sql/upgrade*.sql file unchanged.
- Do not drop the whole database or unrelated tables; reset only named Scan2Borrow tables.
- Preserve existing test coverage and run the documented local quality gates.
- Clearly label the installer as a fresh/reset installer because it recreates application tables and seed rows.

## Files and responsibilities

- Create: sql/install.sql — final standalone schema, seeds, normalized backfills, indexes, and constraints.
- Create: backend/tests/Feature/InstallSchemaContractTest.php — static installer contract.
- Modify: README.md — one-file fresh setup and the separate existing-database migration path.
- Preserve: sql/database.sql, sql/upgrade*.sql, and scan2borrow_2_0.sql.

### Task 1: Add the failing installer schema contract

**Files:**
- Create: backend/tests/Feature/InstallSchemaContractTest.php

**Interfaces:**
- Consumes: sql/install.sql and README.md.
- Produces: PHPUnit assertions for standalone execution, the final table manifest, seed/backfill markers, and foreign-key dependency order.

- [ ] **Step 1: Write the failing contract**

The test must read sql/install.sql through the repository root, assert these executable markers, and assert that SOURCE is absent:

~~~php
public function testInstallScriptIsStandaloneAndContainsTheFinalTableManifest(): void
{
    $sql = $this->readInstallSql();

    foreach ([
        'CREATE DATABASE IF NOT EXISTS scan2borrow_2.0',
        'USE scan2borrow_2.0',
        'SET FOREIGN_KEY_CHECKS = 0',
        'SET FOREIGN_KEY_CHECKS = 1',
        'INSERT INTO users',
        'INSERT INTO books',
        'INSERT INTO book_titles',
        'INSERT INTO book_copies',
        'INSERT INTO borrowing_transactions',
        'INSERT INTO borrowing_items',
        'CREATE TABLE book_title_keywords',
        'CREATE TABLE barcode_print_batches',
        'CREATE TABLE barcode_print_batch_items',
        'CREATE TABLE reservations',
        'CREATE TABLE renewal_requests',
        'CREATE TABLE audit_events',
    ] as $marker) {
        self::assertStringContainsString($marker, $sql);
    }
}
~~~

Use the exact SQL quoting used by the installer when implementing the assertions. Add the 28-table manifest: users, books, borrowing, book_titles, book_copies, borrowing_transactions, borrowing_items, keywords, book_keywords, book_title_keywords, search_history, book_views, visitors, visitor_borrowing, visitor_notifications, visitor_visit_history, visitor_security_logs, notifications, sms_logs, otp_codes, return_notifications, audit_log, profile_change_requests, barcode_print_batches, barcode_print_batch_items, reservations, renewal_requests, and audit_events.

Add a second test that finds each CREATE TABLE position and asserts this order: users before books and borrowing; book_titles before book_copies; book_copies before borrowing_items and reservations; barcode_print_batches before barcode_print_batch_items; borrowing_items before renewal_requests; barcode_print_batch_items before audit_events. Add a third test asserting README.md contains sql/install.sql and the phrase existing database.

- [ ] **Step 2: Run the focused test and verify the expected failure**

~~~powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml --filter InstallSchemaContractTest
~~~

Expected: PHPUnit reports failures because sql/install.sql does not exist. Do not skip or weaken the contract.

- [ ] **Step 3: Commit the failing contract**

~~~powershell
git add -- backend/tests/Feature/InstallSchemaContractTest.php
git commit -m "test: define single SQL installer contract"
~~~

### Task 2: Create the canonical standalone SQL installer

**Files:**
- Create: sql/install.sql

**Interfaces:**
- Consumes: final definitions and seed/backfill SQL from sql/database.sql and the current upgrade files.
- Produces: one executable script that selects scan2borrow_2.0 and creates all 28 manifest tables directly.

- [ ] **Step 1: Add the database and reset boundary**

Start with CREATE DATABASE IF NOT EXISTS and USE for scan2borrow_2.0. Set FOREIGN_KEY_CHECKS to 0, drop only the 28 named application tables in reverse dependency order, then restore FOREIGN_KEY_CHECKS to 1. Do not use DROP DATABASE. Include a prominent comment that this is for a fresh/reset installation and recreates Scan2Borrow-owned data.

- [ ] **Step 2: Add final DDL in dependency order**

Define direct final CREATE TABLE statements, not historical ALTER TABLE replays. Use this order and final content:

1. users, including borrowing_status, failed_attempts, locked_until, last_login, reset_token, and reset_expires.
2. books and borrowing, including publisher, due/return fields, soft-delete, transaction/approval fields, return approval metadata, fine amount, final enums, and foreign keys.
3. book_titles, book_copies, borrowing_transactions, and borrowing_items, including all final return metadata, printed_at, final copy statuses, quantities, indexes, and foreign keys.
4. keywords, book_keywords, book_title_keywords, search_history, and book_views, including recommendation mapping keys and the four final book_titles FULLTEXT keys.
5. visitors, visitor_borrowing, visitor_notifications, visitor_visit_history, and visitor_security_logs, including visitor identity, expiration, request, verification, and return-decision fields.
6. notifications, sms_logs, otp_codes, return_notifications, and audit_log, using the final notification enum values and relationships.
7. profile_change_requests, barcode_print_batches, barcode_print_batch_items, reservations, and renewal_requests, with their final keys and foreign keys.
8. audit_events last, after every referenced table exists, with its event enum, five copy statuses, legacy-source uniqueness guard, and transaction/item/print-batch relations.

Use the exact current column definitions and index names from the source files, resolving duplicate historical additions into one declaration. The installer must directly contain return_status on borrowing, borrowing_transactions, and borrowing_items, and printed_at on book_copies.

- [ ] **Step 3: Add seeds and normalized backfills**

Copy the admin, three sample users, and five sample books INSERT statements from sql/database.sql. Then include the complete current bulk backfill statements in this order: insert grouped book_titles from books; insert book_copies with duplicate-barcode prevention; recalculate book_titles.quantity; insert grouped borrowing_transactions; insert borrowing_items; update copy status from approval/return state. Retain the migration's ISBN grouping, title/author/publisher grouping, collation-safe comparisons, and duplicate guards.

Add the approval-status repair predicates from sql/upgrade_approval_status_sync.sql. Add the audit-event historical backfill statements from sql/upgrade_copy_audit_trail.sql so freshly seeded copies receive acquired audit events and any migrated loans receive loaned/returned events.

- [ ] **Step 4: Run the focused contract and inspect the script**

~~~powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml --filter InstallSchemaContractTest
rg -n "^(CREATE DATABASE|USE |SET FOREIGN_KEY_CHECKS|DROP TABLE|CREATE TABLE|INSERT INTO|UPDATE )" sql\install.sql
~~~

Expected: the contract passes; the output shows the reset boundary before DDL, all 28 tables, seed inserts, and normalized backfills; no SOURCE directive exists.

- [ ] **Step 5: Commit the installer**

~~~powershell
git add -- sql/install.sql backend/tests/Feature/InstallSchemaContractTest.php
git commit -m "feat: add canonical single SQL installer"
~~~

### Task 3: Update fresh-install documentation

**Files:**
- Modify: README.md

**Interfaces:**
- Consumes: sql/install.sql as the fresh/reset setup entry point.
- Produces: unambiguous fresh and existing-database procedures.

- [ ] **Step 1: Replace the fresh setup procedure**

Change the Run locally section so fresh or disposable reset setup imports sql/install.sql once, states that it creates the complete schema, seed data, and normalized backfills, and warns that it recreates Scan2Borrow-owned tables. Retain the full existing-database migration order beginning with upgrade.sql and ending with upgrade_search_recommendations.sql. Mention upgrade_reservations.sql after approval plus bulk schema and upgrade_renewals.sql after the bulk schema when those features are needed.

- [ ] **Step 2: Remove contradictory feature guidance**

In the bulk-borrowing section, state that fresh installs already include normalized tables/backfills through sql/install.sql and that existing databases still need upgrade_bulk_borrowing.sql followed by upgrade_approval_status_sync.sql. In the recommendations section, state that sql/install.sql includes the recommendation schema for fresh installs and existing databases must apply upgrade_search_recommendations.sql last.

- [ ] **Step 3: Run the documentation contract**

~~~powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml --filter InstallSchemaContractTest
~~~

Expected: all installer contract tests pass, including the README assertions.

- [ ] **Step 4: Commit documentation**

~~~powershell
git add -- README.md
git commit -m "docs: document single SQL fresh install"
~~~

### Task 4: Verify a disposable import and run quality gates

**Files:**
- Verify: sql/install.sql, README.md, backend/tests/Feature/InstallSchemaContractTest.php

**Interfaces:**
- Consumes: the committed installer and documentation.
- Produces: evidence of MariaDB execution, schema completeness, seed counts, and green repository gates.

- [ ] **Step 1: Import into a disposable database**

Do not run against a database containing user data. Use an isolated database name and rewrite only the identifier in memory:

~~~powershell
$mysqlPath = 'C:\xampp\mysql\bin\mysql.exe'
$verifyDatabase = 'scan2borrow_install_verify'
& $mysqlPath --protocol=tcp -h 127.0.0.1 -P 3306 -u root -e "DROP DATABASE IF EXISTS $verifyDatabase;"
$verifySql = (Get-Content -Raw 'sql\install.sql') -replace 'scan2borrow_2\.0', $verifyDatabase
$verifySql | & $mysqlPath --protocol=tcp -h 127.0.0.1 -P 3306 -u root
if ($LASTEXITCODE -ne 0) { throw "Disposable installer import failed with exit code $LASTEXITCODE" }
~~~

Expected: the import exits 0 and creates only the disposable verification database.

- [ ] **Step 2: Run schema and seed smoke queries, then clean up**

Query the count of manifest tables, users, books, book_titles, book_copies, and audit_events. Expected counts are 28, 4, 5, 5, 5, and 5. Drop only scan2borrow_install_verify afterward and confirm it no longer exists.

- [ ] **Step 3: Run targeted and full gates**

~~~powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml --filter InstallSchemaContractTest
npm test
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml
C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon
~~~

Expected: each command exits 0. If MariaDB is unavailable, report the exact connection failure and label SQL execution unverified.

- [ ] **Step 4: Review status and final diff**

~~~powershell
git diff HEAD~3..HEAD --stat
git status --short --branch
~~~

Expected: only the approved installer, contract, README, spec, and plan are present; no unrelated changes or disposable database remain.

## Self-review checklist

- Spec coverage: installer, README fresh path, preserved incremental path, seed/backfill behavior, compatibility boundary, and SQL/PHPUnit/MariaDB verification are assigned.
- Placeholder scan: no deferred implementation steps or unfinished sections.
- Marker consistency: the 28-table manifest matches the DDL table list and dependency assertions.
- Safety: only named application tables are reset; verification uses a separate database and removes it afterward.
