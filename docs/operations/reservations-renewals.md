# Reservations and renewals operations

## Apply the migrations

Run the migrations in this order on an existing installation. The order matters because reservations and renewals depend on the newer borrowing and approval tables:

1. `sql/upgrade_bulk_borrowing.sql`
2. `sql/upgrade_approval_system.sql`
3. `sql/upgrade_reservations.sql`
4. `sql/upgrade_renewals.sql`

### XAMPP on Windows

1. Start Apache and MySQL from the XAMPP Control Panel.
2. Confirm the database name and credentials in `config/.env`. The default local setup is database `scan2borrow_2.0`, user `root`, and no password.
3. Open PowerShell in the project folder: `C:\xampp\htdocs\scan2borrow`.
4. Run the following. Each command stops the process if its migration fails:

```powershell
$mysqlPath = 'C:\xampp\mysql\bin\mysql.exe'
$database = 'scan2borrow_2.0'

function Invoke-Scan2BorrowMigration {
    param([string] $Path)

    $sql = Get-Content -LiteralPath $Path -Raw
    $sql | & $mysqlPath --protocol=tcp -h 127.0.0.1 -P 3306 -u root $database
    if ($LASTEXITCODE -ne 0) {
        throw "Migration failed: $Path"
    }
}

Invoke-Scan2BorrowMigration '.\sql\upgrade_bulk_borrowing.sql'
Invoke-Scan2BorrowMigration '.\sql\upgrade_approval_system.sql'
Invoke-Scan2BorrowMigration '.\sql\upgrade_reservations.sql'
Invoke-Scan2BorrowMigration '.\sql\upgrade_renewals.sql'
```

If the MySQL `root` account has a password, add `-p` after `-u root` in the function. MySQL will prompt for it; do not put the password directly in the command.

The reservation and renewal scripts are safe to rerun: their tables use `CREATE TABLE IF NOT EXISTS`, and the notification type update is additive.

### Verify the installation

Run this from the same PowerShell window:

```powershell
& $mysqlPath --protocol=tcp -h 127.0.0.1 -P 3306 -u root $database -N -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('reservations','renewal_requests') ORDER BY TABLE_NAME;"
```

The output must contain both `renewal_requests` and `reservations`. If either table is missing, borrower dashboard calls to `/api/student/holds`, `/api/teacher/holds`, `/api/student/renewals`, or `/api/teacher/renewals` can return HTTP 500.

Reservations are title-level FIFO queues. When a copy is returned, or when the maintenance command expires an unclaimed offer, the oldest active borrower account receives the copy. The offer window is exactly 24 hours. Borrowers can claim or cancel from their dashboard; librarians complete the hand-off from `/staff/reservations`.

Run the expiry command from the application root on a frequent schedule (every 5–15 minutes):

```text
php backend/bin/expire-reservations.php
```

Renewals are borrower-submitted requests. A librarian or administrator approves or rejects them from `/staff/renewals`. Approval extends the loan by one standard seven-day period, only when the loan is active, the account is in good standing, the title has no active holds, and that loan has not already used its renewal. Decisions are recorded and notify the borrower in-app.

The new notification types are `hold_available`, `renewal_approved`, and `renewal_rejected`. Email delivery remains best-effort; the in-app notification is the source of truth.
