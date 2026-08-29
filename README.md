# Scan2Borrow

Scan2Borrow is a vanilla HTML/CSS/JavaScript frontend with a framework-free PHP 8.2 modular-monolith backend and the existing MySQL schema.

## Run locally

1. Put the project in `C:\xampp\htdocs\scan2borrow`.
2. For a fresh install, import `sql/database.sql`, then run `sql/upgrade_bulk_borrowing.sql` to backfill the seeded legacy rows into the catalog/copy model. For an existing database, run the applicable upgrade scripts in this order: `upgrade.sql`, `upgrade_add_teacher_fields.sql`, `upgrade_approval_system.sql`, `upgrade_borrowing_control.sql`, `upgrade_notification_system.sql`, `upgrade_pending_status.sql`, `upgrade_security.sql`, then `upgrade_bulk_borrowing.sql`. The bulk migration is required in both cases.
3. Set `SCAN2BORROW_DB_HOST`, `SCAN2BORROW_DB_PORT`, `SCAN2BORROW_DB_NAME`, `SCAN2BORROW_DB_USER`, and `SCAN2BORROW_DB_PASSWORD` when the defaults are not suitable.
4. Start Apache and MySQL in XAMPP and open `http://localhost/scan2borrow/`.

Apache sends clean page and API routes through `backend/public/index.php`. Page files are feature-owned static HTML under `frontend/features`; they are streamed only after the server-side session and role policy has allowed the request.

## Structure

```text
frontend/
  app/                   Application bootstrap, API, session, guards, and shared components
  features/              Auth, student, teacher, guest, and staff pages, entries, services, and models
  assets/css/            Preserved stylesheet and page styles
  assets/js/core/        Shared navbar, auth-brand, icon, media, and scanner helpers
  tests/                 Native Node frontend contract and parity tests
backend/
  public/index.php       Thin Apache entry point
  src/                   OOP application, domain, HTTP, and PDO modules
  tests/                 PHPUnit contract and unit tests
sql/                     Preserved schema, seed, dump, and upgrade scripts
uploads/                 Runtime photo storage
```

### Bulk borrowing database model

Bulk borrowing uses `book_titles` for one catalog title and its total `quantity`, `book_copies` for individually barcoded physical copies, `borrowing_transactions` for one checkout session, and `borrowing_items` for each copy in that session. Available, reserved, and borrowed quantities are calculated from non-archived copy statuses. Run `sql/upgrade_bulk_borrowing.sql` whenever an existing or freshly imported legacy schema is being prepared for the bulk-borrowing application.

### Inventory management

Staff inventory is managed in two levels: a catalog title stores shared metadata and total quantity, while each physical copy stores its own barcode, accession number, status, location, and loan dates. New titles may be created with any quantity; blank seed identifiers are generated for every copy. Edit title quantity from the inventory row, then use `View copies` to assign or correct individual identifiers and locations. A title quantity reduction archives available copies only and will refuse to remove copies involved in active loans. If the normalized tables are missing, inventory quantity operations return `Run sql/upgrade_bulk_borrowing.sql before managing quantities.`

## Quality checks

```text
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml
C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon
```

The tests cover route authorization, clean-route gateway behavior, CSRF, authentication, borrower and guest workflows, inventory behavior, staff workflows, frontend markup/interaction contracts, and SQL/schema preservation.

## Security and compatibility

- Protected pages and APIs require a server-side session and role policy; typing a protected URL does not expose its HTML.
- State-changing requests require CSRF validation.
- PDO repositories retain the existing table names, columns, status values, and SQL behavior wherever possible.
- Credentials are read from environment variables; secrets are not stored in application source.
- The old procedural root entry points and duplicate `frontend/pages`, `frontend/assets/js/pages`, and `frontend/assets/js/guest` trees are removed. The public application surface is the clean route gateway plus static frontend assets.
