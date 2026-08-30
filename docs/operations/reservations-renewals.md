# Reservations and renewals operations

Run the migrations in this order on an existing installation:

1. `sql/upgrade_bulk_borrowing.sql`
2. `sql/upgrade_approval_system.sql`
3. `sql/upgrade_reservations.sql`
4. `sql/upgrade_renewals.sql`

Reservations are title-level FIFO queues. When a copy is returned, or when the maintenance command expires an unclaimed offer, the oldest active borrower account receives the copy. The offer window is exactly 24 hours. Borrowers can claim or cancel from their dashboard; librarians complete the hand-off from `/staff/reservations`.

Run the expiry command from the application root on a frequent schedule (every 5–15 minutes):

```text
php backend/bin/expire-reservations.php
```

Renewals are borrower-submitted requests. A librarian or administrator approves or rejects them from `/staff/renewals`. Approval extends the loan by one standard seven-day period, only when the loan is active, the account is in good standing, the title has no active holds, and that loan has not already used its renewal. Decisions are recorded and notify the borrower in-app.

The new notification types are `hold_available`, `renewal_approved`, and `renewal_rejected`. Email delivery remains best-effort; the in-app notification is the source of truth.
