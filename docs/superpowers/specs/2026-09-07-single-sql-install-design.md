# Single SQL Install Design

## Goal

Provide one SQL file that a new Scan2Borrow installation can import once to create the complete current database schema, seed the default/sample records, and apply the normalized-model backfills required by the application.

## Current problem

Fresh setup currently requires importing `sql/database.sql` and then running several upgrade files in a specific order. Literal concatenation is unsafe because `database.sql` already contains some fields and tables that historical migrations attempt to add again. The repository also contains an older phpMyAdmin dump that does not represent the current normalized schema.

## Selected approach

Add `sql/install.sql` as a canonical fresh-install snapshot. It will be a standalone MySQL/MariaDB script suitable for phpMyAdmin or the MySQL CLI; it will not depend on `SOURCE` commands or other files being present.

The file will:

1. Select/create the configured `scan2borrow_2.0` database and reset only the application tables in foreign-key-safe order.
2. Define the final schema directly, including the legacy compatibility tables and all normalized tables used by the current application.
3. Include the current seed data from `sql/database.sql`.
4. Include the data backfills needed to move the seeded legacy books/loans into the normalized bulk-borrowing model.
5. Include the final indexes, constraints, enum values, approval/return fields, recommendation tables, reservation and renewal tables, notification/security tables, barcode-printing tables, profile-change tables, and audit-trail tables represented by the current migrations.

The existing `sql/database.sql` and individual `sql/upgrade_*.sql` files will remain unchanged so existing installations can continue using the documented incremental upgrade path.

## Documentation change

Update the fresh-install section of `README.md` to use `sql/install.sql` as the one-file procedure. Keep the existing ordered migration procedure documented for databases that already contain data, and clearly state that `sql/install.sql` is for a fresh/reset installation because it recreates application tables and seeds.

## Compatibility and safety

- The install file targets the repository's existing database name and MariaDB/XAMPP syntax.
- It will not drop the whole database or unrelated tables.
- It will not alter the behavior of incremental migrations or application code.
- The older `scan2borrow_2_0.sql` dump will not be used as the source of truth because it predates the current schema.

## Verification

Verification will include:

- Static checks that the install file is self-contained, ordered, and contains every final application table.
- A disposable MariaDB/MySQL import when a local server is available, followed by schema and seed-data smoke queries.
- The relevant repository quality checks: frontend tests, PHPUnit, and PHPStan when their local dependencies/tools are available.

