# Scan2Borrow
### An Automated Library Borrowing and Return System (barcode integration)

A web-based library management system for borrowing and returning books using
barcode scanning, built with PHP, MySQL and Bootstrap. Designed to run on a
school's local network (XAMPP-friendly), aligned to the capstone Chapter 1
scope.

---

## Features

**Librarian / Admin**
- Secure login (barcode + password) with role-based access control.
- Dashboard with live counts (books, available, borrowed, borrowers, active loans, overdue).
- **Modern inventory console** — AJAX management grid (no page reloads): slide-over
  drawer for add/edit, live search, sortable columns, pagination, bulk actions,
  and **Archive/Restore** (soft delete) with toast notifications.
- **Desk Borrow** — scan borrower ID barcode + book barcode to issue a loan.
- **Desk Return** — scan book barcode to process a return (auto-computes fine).
- Borrower management with per-student borrowing history monitoring.
- Automatic due dates + overdue monitoring with auto-computed fines.
- Email notifications / overdue reminders (PHPMailer, SMTP via config).
- Reports (borrowed / returned / overdue / inventory) with CSV export.
- Digital borrowing receipts (printable, with transaction code).

**Student / Teacher (borrower)**
- Kiosk login by scanning ID barcode.
- Self-service dashboard: current loans, due dates, pending fines.
- Book availability search.
- Full personal borrowing history + receipts.

**Barcode input methods (per Chapter 1):**
1. USB / handheld barcode scanner (keyboard wedge — works out of the box).
2. Webcam scanning (html5-qrcode).
3. Manual typing fallback (for materials without barcode labels).

---

## Setup (XAMPP / local)

1. **Copy the project** into your web root, e.g. `C:\xampp\htdocs\scan2borrow`.
2. **Create the database.** In phpMyAdmin, *Import* `database.sql`
   (or run `mysql -u root -p < database.sql`). This creates the
   `scan2borrow_2.0` database, all tables, and seed data.
   *(If you already imported an older version, also import `upgrade.sql`
   to add the `books.deleted_at` column used by archive/restore.)*
3. **Configure.** Copy `.env.example` to `.env` and adjust values:
   - `DB_*` — your MySQL host / user / password.
   - `LOAN_DAYS`, `FINE_PER_DAY`, `MAX_BOOKS_PER_USER` — library policy.
   - `MAIL_*` — SMTP credentials for email (see below).
   If you skip `.env`, sensible defaults in `config/config.php` are used
   (root / no password / `scan2borrow_2.0`).
4. **Open** `http://localhost/scan2borrow/` in your browser.

### Default login

Students and staff log in on **separate pages**:
- **Students** — `index.php` (scan Student ID barcode, no password).
- **Staff/Librarian** — `staff_login.php` (barcode + password); linked from the student page.

| Role            | Login page        | Barcode    | Password   |
|-----------------|-------------------|------------|------------|
| Admin/Librarian | `staff_login.php` | `ADMIN001` | `admin123` |
| Student (demo)  | `index.php`       | `2024001`  | *(none)*   |

> **Change the admin password after first login** (it is only the seed default).

### Email (optional)

Email uses **PHPMailer**. Put the PHPMailer library in a `PHPMailer/` folder at
the project root (so `PHPMailer/src/PHPMailer.php` exists), then set
`MAIL_USERNAME` / `MAIL_PASSWORD` in `.env`. For Gmail, create an
**App Password** (Google Account → Security → App passwords) — never hardcode it
in the source. If PHPMailer isn't installed, the system falls back to PHP `mail()`.

---

## Project structure

```
scan2borrow/
├── config/
│   ├── config.php        # settings + .env loader
│   └── db.php            # PDO connection (prepared statements everywhere)
├── includes/
│   ├── auth.php          # session, role guards, CSRF
│   ├── functions.php     # helpers: due dates, overdue, fines, badges
│   ├── mailer.php        # PHPMailer wrapper (config-based credentials)
│   ├── header.php        # shared app shell + sidebar
│   └── footer.php
├── assets/
│   ├── css/style.css     # single design system
│   ├── js/scanner.js     # webcam barcode scanning
│   └── js/inventory.js   # inventory console (grid, drawer, bulk actions)
├── index.php             # student login (barcode only)
├── staff_login.php       # staff/librarian login (barcode + password)
├── register.php          # borrower registration
├── logout.php
├── adboard.php           # librarian dashboard
├── managebooks.php       # modern inventory console (AJAX)
├── books_api.php         # JSON API powering the console (CRUD + archive/restore)
├── adstud.php            # borrowers list
├── view_student.php      # borrower detail + history
├── overdue.php           # overdue monitoring
├── reports.php           # reports + printable report + CSV export
├── send_notification.php # email notifications
├── receipt.php           # digital receipt
├── studhome.php          # student dashboard
├── student_search.php    # book availability search
├── student_history.php   # student borrowing history
└── database.sql          # schema + seed
```

---

## Security notes

- All database access uses **PDO prepared statements** (no SQL injection).
- **Role-based guards** on every page (`require_staff()` / `require_borrower()`).
- **CSRF tokens** on all state-changing forms.
- Output escaped with `htmlspecialchars` (XSS-safe).
- **No secrets in source** — DB and SMTP credentials live in `.env` (git-ignored).

## Scope alignment (Chapter 1)

Out-of-scope items are intentionally **not** included: RFID, SMS notifications,
online reservations, e-books/audiobooks, online fine payment, and graphical
analytics dashboards. The system is optimized for desktop/laptop on a local
network. Fines are computed and displayed for monitoring only (no online
payment).
