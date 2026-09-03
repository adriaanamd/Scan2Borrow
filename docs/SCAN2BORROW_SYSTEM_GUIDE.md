# Scan2Borrow System Guide

This is the practical setup, demonstration, and checking guide for Scan2Borrow. It is written for someone who did not build the system but needs to install it, explain it, and demonstrate the features confidently.

## 1. What the system does

Scan2Borrow is a library management and borrowing system for Binalbagan Catholic College. It separates people, catalog titles, and physical copies:

- A user is a student, teacher, librarian, or administrator.
- A catalog title stores shared book information such as title, author, ISBN, category, and quantity.
- A physical copy is one real item with its own barcode, accession number, location, status, and history.
- A borrowing transaction is one checkout session.
- A business audit event records what happened to a physical copy, when it happened, and who performed it.

The main user groups are:

| User | Main purpose |
| --- | --- |
| Student | Search books, request loans, return books, reserve titles, request renewals, and request profile changes. |
| Teacher | Use the teacher borrower portal with the same circulation capabilities and teacher-specific profile information. |
| Guest | Register as a visitor, browse books, request a guest loan, track returns, and show a visitor pass. |
| Librarian | Operate daily library workflows: inventory, circulation, approvals, returns, reports, printing, and history. |
| Admin | Everything a librarian can do, plus staff management, API documentation, and profile-change approval. |

The application has a vanilla HTML/CSS/JavaScript frontend, a framework-free PHP 8.2 backend, and a MySQL/MariaDB database.

## 2. Required software

Install or have access to:

- Windows
- XAMPP installed at C:\xampp
- Apache and MySQL enabled in XAMPP
- PHP 8.2 or newer
- Node.js and npm
- A modern browser
- A camera if testing barcode or photo capture

All commands in this guide assume the project is here:

~~~text
C:\xampp\htdocs\scan2borrow
~~~

If the project is in another folder, replace the path in the commands and browser URLs.

## 3. Start in the project folder

Open PowerShell and run:

~~~powershell
Set-Location 'C:\xampp\htdocs\scan2borrow'
Get-Location
Get-ChildItem
git status --short --branch
~~~

The important folders are:

~~~text
frontend\   Browser pages, JavaScript, CSS, and frontend tests
backend\    PHP application, routes, services, repositories, and PHPUnit tests
sql\        Schema, seed data, and database upgrade scripts
uploads\    Runtime photo storage
public\     Logo and favicon assets
docs\       Project documentation
~~~

Do not commit config\.env. It contains local database credentials and may contain mail or SMS credentials.

## 4. Configure the local environment

Create the environment file only when it does not already exist:

~~~powershell
Set-Location 'C:\xampp\htdocs\scan2borrow'
if (-not (Test-Path -LiteralPath 'config\.env')) {
    Copy-Item 'config\.env.example' 'config\.env'
}
notepad 'config\.env'
~~~

At minimum, check these values:

~~~dotenv
DB_HOST=localhost
DB_PORT=3306
DB_NAME=scan2borrow_2.0
DB_USER=root
DB_PASS=
~~~

The application also supports SCAN2BORROW_DB_* variables. Those override the ordinary DB_* variables when both exist.

For email/SMS registration testing, configure the values in config\.env using config\.env.example as the template. Gmail requires 2-Step Verification and a Gmail App Password. Never use a real account password in documentation.

Create the photo directory:

~~~powershell
New-Item -ItemType Directory -Force 'uploads\photos' | Out-Null
Get-ChildItem 'uploads\photos'
~~~

## 5. Start XAMPP and test Apache

Open the XAMPP control panel:

~~~powershell
Start-Process 'C:\xampp\xampp-control.exe'
~~~

Start Apache and MySQL in the XAMPP window.

Optional process check:

~~~powershell
Get-Process httpd, mysqld -ErrorAction SilentlyContinue | Select-Object ProcessName, Id, Path
~~~

Test the application root:

~~~powershell
Invoke-WebRequest 'http://localhost/scan2borrow/' -UseBasicParsing | Select-Object StatusCode, Headers
~~~

Expected result: HTTP 200 or a normal redirect to the login page. A connection error means Apache is not running or the project is not under the configured XAMPP htdocs directory.

## 6. Database installation

There are two useful database choices.

### 6.1 Full seeded database for demonstrations

Use scan2borrow_2_0.sql when you want the larger existing sample dataset. It contains legacy users, books, borrowing records, visitors, notifications, and other demo data.

This import is destructive: it replaces database tables and data. Do not run it against a database that contains data you need to keep.

~~~powershell
Set-Location 'C:\xampp\htdocs\scan2borrow'
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
Get-Content -Raw 'scan2borrow_2_0.sql' | & $mysql --protocol=tcp -h localhost -P 3306 -u root
if ($LASTEXITCODE -ne 0) { throw 'The full database import failed.' }
~~~

After the full dump, apply the normalized-library and newer feature migrations:

~~~powershell
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
$upgradeFiles = @(
    'sql\upgrade_bulk_borrowing.sql',
    'sql\upgrade_approval_status_sync.sql',
    'sql\upgrade_barcode_printing.sql',
    'sql\upgrade_copy_audit_trail.sql',
    'sql\upgrade_renewals.sql',
    'sql\upgrade_reservations.sql',
    'sql\upgrade_profile_change_requests.sql'
)

foreach ($file in $upgradeFiles) {
    Write-Host "Applying $file"
    Get-Content -Raw $file | & $mysql --protocol=tcp -h localhost -P 3306 -u root
    if ($LASTEXITCODE -ne 0) { throw "Migration failed: $file" }
}
~~~

This is the recommended path for a complete checking/demo environment because the full dump already contains the older guest, notification, OTP, and security tables.

### 6.2 Clean current base schema

Use sql\database.sql when you want the clean current base schema and smaller seed set:

~~~powershell
Set-Location 'C:\xampp\htdocs\scan2borrow'
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
Get-Content -Raw 'sql\database.sql' | & $mysql --protocol=tcp -h localhost -P 3306 -u root
if ($LASTEXITCODE -ne 0) { throw 'The clean database import failed.' }
~~~

The clean base includes users, books, normalized titles/copies/transactions, copy audit structure, and profile-change structure. For the broadest existing demo data, use the full seeded database.

### 6.3 Existing installation: apply only what is missing

For an existing database, do not re-import either base file. Apply only the missing upgrade script.

For profile approval, the exact repair command is:

~~~powershell
Set-Location 'C:\xampp\htdocs\scan2borrow'
Get-Content -Raw 'sql\upgrade_profile_change_requests.sql' | & 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root
~~~

That script is safe to run more than once. It creates profile_change_requests, stores before/after values and photo paths, and adds reviewer/lookup indexes.

Verify it:

~~~powershell
& 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root --database='scan2borrow_2.0' -e "SHOW TABLES LIKE 'profile_change_requests'; SHOW COLUMNS FROM profile_change_requests;"
~~~

List all tables:

~~~powershell
& 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root --database='scan2borrow_2.0' -e "SHOW TABLES;"
~~~

Important current tables:

~~~text
users
books
book_titles
book_copies
borrowing
borrowing_transactions
borrowing_items
notifications
renewal_requests
reservations
barcode_print_batches
barcode_print_batch_items
audit_events
audit_log
profile_change_requests
visitors
visitor_borrowing
visitor_notifications
visitor_visit_history
visitor_security_logs
~~~

## 7. Open the application

Open:

~~~text
http://localhost/scan2borrow/
~~~

Direct page routes:

| Page | URL | Access |
| --- | --- | --- |
| Borrower login | /login | Students and teachers |
| Staff login | /staff/login | Admins and librarians |
| Borrower registration | /register | New students and teachers |
| Guest registration | /guest/registration | Visitors |
| Student dashboard | /student/dashboard | Students |
| Student search | /student/search | Students |
| Student history | /student/history | Students |
| Student settings | /student/settings | Students |
| Teacher dashboard | /teacher/dashboard | Teachers |
| Teacher borrow | /teacher/borrow | Teachers |
| Teacher history | /teacher/history | Teachers |
| Teacher settings | /teacher/settings | Teachers |
| Borrower receipt | /receipt | Students and teachers |
| Guest dashboard | /guest/dashboard | Verified guests |
| Guest browse | /guest/browse | Verified guests |
| Guest borrow request | /guest/borrow-request | Verified guests |
| Guest borrowed books | /guest/borrowed | Verified guests |
| Guest return | /guest/return-book | Verified guests |
| Guest history | /guest/history | Verified guests |
| Guest pass | /guest/pass | Verified guests |
| Guest receipt | /guest/receipt | Verified guests |
| Staff dashboard | /staff/dashboard | Admins and librarians |
| Staff inventory | /staff/books | Admins and librarians |
| Staff borrowers | /staff/students | Admins and librarians |
| Reservations | /staff/reservations | Admins and librarians |
| Renewals | /staff/renewals | Admins and librarians |
| Overdue loans | /staff/overdue | Admins and librarians |
| Reports | /staff/reports | Admins and librarians |
| Guest requests | /staff/guest-requests | Admins and librarians |
| Copy history | /staff/copy-history | Admins and librarians |
| Barcode printing | /staff/barcodes/print | Admins and librarians |
| Admin staff management | /admin/staff | Admins only |
| Admin profile approvals | /admin/staff | Admins only; inside Admin Staff |
| Admin API documentation | /admin/api-docs | Admins only |

Protected pages are checked by the server. A user cannot gain access just by typing a protected URL.

## 8. Accounts and roles

### Default administrator

The clean SQL seed documents this local-only account:

~~~text
Barcode: ADMIN001
Password: admin123
~~~

Change this password immediately on a real deployment.

### Clean-seed student accounts

The clean SQL seed includes:

~~~text
2024001
2024002
2024003
~~~

Borrowers sign in with their ID barcode. Staff sign in with barcode plus password.

Inspect all accounts:

~~~powershell
& 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root --database='scan2borrow_2.0' -e "SELECT id, barcode, firstname, lastname, role, status, borrowing_status FROM users ORDER BY id;"
~~~

### Permission summary

| Capability | Student | Teacher | Guest | Librarian | Admin |
| --- | ---: | ---: | ---: | ---: | ---: |
| Search/browse books | Yes | Yes | Yes | Yes | Yes |
| Submit borrower loan request | Yes | Yes | No | No | No |
| Submit guest loan request | No | No | Yes | No | No |
| Return own borrower loan | Yes | Yes | No | No | No |
| Reserve a title | Yes | Yes | No | Review/fulfil | Review/fulfil |
| Request loan renewal | Yes | Yes | No | Approve/reject | Approve/reject |
| Request own profile changes | Student | Teacher | No | No | Review only |
| Manage titles and copies | No | No | No | Yes | Yes |
| Approve borrower loans | No | No | No | Yes | Yes |
| Approve guest requests | No | No | No | Yes | Yes |
| View physical-copy history | No | No | No | Yes | Yes |
| Export/print barcodes | No | No | No | Yes | Yes |
| Manage staff accounts | No | No | No | No | Yes |
| Approve profile changes | No | No | No | No | Yes |
| View API docs | No | No | No | No | Yes |

Every state-changing operation requires a valid session and CSRF token.

## 9. Authentication and registration

### Student/teacher registration

1. Open /register.
2. Choose Student or Teacher.
3. Enter the ID barcode and profile information.
4. Choose Email or Phone for OTP delivery.
5. Complete the photo step if shown.
6. Submit the form.
7. Enter the OTP at /verify-otp.
8. Confirm the correct borrower dashboard opens.

### Guest registration

1. Open /guest/registration.
2. Enter visitor information, purpose, government ID barcode, and contact information.
3. Capture the live photo.
4. Submit and complete the guest OTP step.
5. Confirm /guest/dashboard opens.
6. Confirm the visitor pass is available at /guest/pass.

### Session check

~~~powershell
Invoke-WebRequest 'http://localhost/scan2borrow/api/auth/session' -UseBasicParsing | Select-Object StatusCode, Content
~~~

401 means no valid session. 403 means the session exists but the role is not allowed.

## 10. Student and teacher workflows

### Dashboard

The borrower dashboard shows profile summary, active loan count and capacity, pending requests, active loans and due dates, reservation and renewal actions, and recent activity or recommendations.

The normal borrower capacity is three active books.

### Search and borrow

1. Sign in as a student or teacher.
2. Open Search or Borrow.
3. Search by title, author, category, floor, or status.
4. Open a result and check availability.
5. Add one or more copies to the borrow cart.
6. Confirm repeated scans combine into one title line while retaining copy barcodes.
7. Submit the request with the due date.
8. Confirm the request is pending staff approval.
9. Sign in as a librarian or admin.
10. Approve or reject the request from the staff dashboard.
11. Return to the borrower and confirm the status and notification.

Use a title with at least one Available physical copy. Do not exceed the three-book limit during a demo.

### Return

1. Sign in as the borrower who has an active loan.
2. Use the return control.
3. Scan or enter the copy barcode, or use the transaction/receipt route.
4. Submit the return.
5. Confirm the borrowing item becomes Returned.
6. Confirm the physical copy becomes available again.
7. Confirm the borrower's history and staff notifications update.

### Reservations

When a title has no available copy:

1. Borrower reserves the title.
2. Staff opens /staff/reservations.
3. Staff checks borrower, title, queue position, and state.
4. Staff offers or fulfils a copy when one becomes available.
5. Borrower claims the offer.
6. Confirm queued, offered, claimed, fulfilled, expired, and cancelled states are distinct.

### Renewals

1. Borrower opens an active loan.
2. Borrower requests a later due date and optionally enters a reason.
3. Staff opens /staff/renewals.
4. Staff checks borrower, title, old due date, requested due date, and reason.
5. Staff approves or rejects with an optional note.
6. Confirm the due date changes only on approval.
7. Confirm the borrower receives the decision.

### Settings and profile approval

Students use /student/settings. Teachers use /teacher/settings.

Requestable fields:

- First name
- Middle name
- Last name
- Email
- Contact number
- Course
- Year level
- Department
- Position
- Profile photo, JPG or PNG up to 4 MB

Admin-only fields:

- Barcode or student/teacher ID
- Role
- Active/inactive status
- Password and security fields
- System timestamps and other administrative fields

Checking procedure:

1. Sign in as a student or teacher.
2. Open Settings.
3. Change one text field.
4. Select a JPG or PNG photo under 4 MB.
5. Submit for approval.
6. Confirm a Pending request appears.
7. Confirm the form prevents a second pending request for the same borrower.
8. Sign in as an admin.
9. Open Admin > Staff.
10. Find the profile-change request.
11. Compare before and after values and the photo indicator.
12. Approve it with an optional note.
13. Return to the borrower and confirm the approval notification.
14. Confirm the user profile changes after approval.
15. Repeat with rejection and confirm the old profile remains unchanged.
16. Try to edit the barcode and confirm it is read-only.

The profile request is a business record, not merely a PHP/application log.

## 11. Guest workflows

Guests are stored separately from students and teachers.

### Browse and request a loan

1. Complete guest registration and OTP verification.
2. Open /guest/browse.
3. Search or filter the catalog.
4. Open a title.
5. Submit a guest borrow request.
6. Provide the verification photo when requested.
7. Staff opens /staff/guest-requests.
8. Staff reviews the visitor, title, and photo.
9. Staff approves or rejects the request.

### Guest loan lifecycle

- /guest/borrowed shows released guest loans.
- /guest/return-book submits a barcode and return verification photo.
- /guest/history shows visitor borrowing history.
- /guest/receipt shows a visitor receipt.
- /guest/pass shows the visitor pass.
- /guest/profile shows the visitor profile.

Check that students/teachers cannot use guest-only workflows and guests cannot open borrower-only pages.

## 12. Staff operations

### Staff dashboard

Open /staff/dashboard as admin or librarian and check inventory totals, current and overdue loans, pending borrower requests, pending guest requests, recent activity, notifications, and overview analytics.

### Inventory: title versus copy

Open /staff/books.

A catalog title stores shared information. A physical copy stores the information that identifies one actual book:

- Barcode
- Accession number
- Floor
- Section
- Shelf
- Row
- Status
- Due date and return date

Check inventory:

1. Create or edit a title.
2. Set quantity.
3. Open View copies.
4. Create or edit individual copies.
5. Assign identifiers and locations.
6. Change a copy status.
7. Archive a copy and confirm it stops counting as active.
8. Restore it and confirm it returns.
9. Try reducing a quantity while copies are on active loans; unsafe removal should be refused.

The borrower availability number comes from active physical copies, not only title quantity.

### Barcode printing

Open /staff/barcodes/print or the title's copy panel.

1. Select a title with unprinted active copies.
2. Export unprinted barcodes.
3. Confirm an export batch is created.
4. Confirm the copies are marked printed.
5. Open the print-ready page.
6. Use the browser print dialog and choose Save as PDF.
7. Export again and confirm already printed copies are skipped.
8. Reopen an old batch and reprint its immutable snapshot.

The printed marker is intentionally irreversible. Canceling the browser print dialog does not undo the export batch.

### Copy history and audit trail

Open /staff/copy-history, enter a physical-copy barcode, and inspect the timeline.

The history can show acquisition, loan, return, status changes, lost or damaged actions, barcode printing, archive/restore/delete actions, the staff actor, the reason or business note, linked transaction or print-batch context, and exact event time.

This is a business audit trail, not an application error log.

A good explanation is:

> Copy BK-5003-01 changed from Available to Lost at 14:32. Admin Jane Doe performed the action and entered the reason. The event remains visible in the copy's lifetime history.

### Borrower management

Open /staff/students to search student and teacher accounts, open borrower details, inspect borrower history, see active and overdue counts, update a borrower photo, and send a borrower notification.

Admin profile approval is on /admin/staff, not the general borrower list.

### Overdue and reports

Open /staff/overdue to inspect overdue loans and calculated fines.

Open /staff/reports to generate borrowing, return, overdue, and inventory reports.

Checking:

1. Generate a report with no filter.
2. Apply a date range.
3. Confirm headers and rows align.
4. Export CSV.
5. Open the CSV in a spreadsheet program.
6. Print only after the report is ready.

### Staff account management and API docs

Admins only:

- /admin/staff manages staff accounts and contains profile approvals.
- /admin/api-docs displays the API catalog.

A librarian should receive authorization denial for both admin-only surfaces.

## 13. Status vocabulary

### Physical-copy statuses

~~~text
Available   The copy can be borrowed.
Borrowed    The copy is currently on an active loan.
Reserved    The copy is allocated to a reservation flow.
Lost        The copy is missing and is not available.
Damaged     The copy is unusable or held for damage handling.
~~~

### Borrowing statuses

~~~text
Pending     A request is waiting for staff action.
Borrowed    The copy is released/on loan.
Returned    The item has been returned.
Overdue     The due date has passed without return.
~~~

### Request decisions

~~~text
pending     Awaiting review.
approved    Accepted.
rejected    Denied.
~~~

### Reservation statuses

~~~text
queued      Waiting in title order.
offered     A copy was offered.
claimed     The borrower claimed the offer.
fulfilled   Staff completed fulfilment.
expired     The offer window elapsed.
cancelled   The reservation was cancelled.
~~~

## 14. Where features live in the code

### Frontend map

~~~text
frontend/app/bootstrap/       Page registration and role-specific bootstraps
frontend/app/core/api/        API client and API error handling
frontend/app/core/auth/       Sessions and route guards
frontend/app/core/services/   Notifications, modals, reservations, renewals
frontend/app/shared/          Navbar, scanner, camera, tables, toasts, states
frontend/features/student/    Student pages, models, and services
frontend/features/teacher/    Teacher pages, models, and services
frontend/features/guest/      Guest pages, models, and services
frontend/features/staff/      Staff pages, models, and services
frontend/assets/css/          Shared and page-scoped styles
frontend/tests/               Native Node frontend contract tests
~~~

Profile approval frontend files:

~~~text
frontend/features/student/pages/settings/student-settings.page.js
frontend/features/student/pages/settings/settings.html
frontend/features/student/pages/settings/settings.css
frontend/features/student/services/profile-change.service.js
frontend/features/teacher/pages/settings/teacher-settings.page.js
frontend/features/teacher/pages/settings/settings.html
frontend/features/teacher/pages/settings/settings.css
frontend/features/teacher/services/profile-change.service.js
frontend/features/staff/pages/admin-staff/admin-staff.page.js
frontend/features/staff/services/profile-change-request.service.js
~~~

Copy history and barcode frontend files are under:

~~~text
frontend/features/staff/pages/copy-history/
frontend/features/staff/pages/barcodes/
frontend/features/staff/services/
frontend/app/shared/components/barcode-scanner/
~~~

### Backend map

~~~text
backend/public/index.php                Apache entry point
backend/src/Bootstrap/                  Dependency wiring
backend/src/Domain/                     Typed business records and enums
backend/src/Application/Services/       Business workflows
backend/src/Application/Validators/     Input validation
backend/src/Http/Controllers/           HTTP boundaries
backend/src/Http/Routing/               Page and API route tables
backend/src/Infrastructure/Persistence/ PDO/MySQL repositories
backend/src/Infrastructure/Database/    DB configuration and PDO setup
backend/tests/                          PHPUnit tests
~~~

Profile approval backend files:

~~~text
backend/src/Domain/Profile/ProfileFieldPolicy.php
backend/src/Domain/Profile/ProfileChangeRequest.php
backend/src/Domain/Profile/ProfileChangeRequestStatus.php
backend/src/Application/Validators/ProfileChangeRequestValidator.php
backend/src/Application/Services/ProfileChangeRequestService.php
backend/src/Http/Controllers/ProfileChangeRequestController.php
backend/src/Infrastructure/Persistence/PdoProfileChangeRequestRepository.php
backend/src/Infrastructure/Persistence/PdoProfileChangeNotificationRepository.php
backend/src/Http/Routing/BorrowerRouteTable.php
backend/src/Http/Routing/StaffRouteTable.php
backend/src/Http/Controllers/StaffController.php
~~~

## 15. Database table map

| Table | Purpose |
| --- | --- |
| users | Students, teachers, librarians, and admins. |
| books | Legacy one-row-per-book compatibility model. |
| book_titles | Normalized catalog titles. |
| book_copies | Individually barcoded physical copies. |
| borrowing | Legacy borrowing compatibility records. |
| borrowing_transactions | One normalized checkout session. |
| borrowing_items | One physical copy within a transaction. |
| reservations | Title-level queue and hold offers. |
| renewal_requests | Borrower renewals and staff decisions. |
| barcode_print_batches | Immutable barcode export batches. |
| barcode_print_batch_items | Copy snapshot in an export batch. |
| audit_events | Physical-copy business audit timeline. |
| audit_log | Security/application activity records. |
| profile_change_requests | Student/teacher before-and-after approval records. |
| notifications | Staff circulation and workflow notifications. |
| visitors | Guest identity and registration data. |
| visitor_borrowing | Guest loan and return records. |

Main SQL files:

~~~text
sql/database.sql                         Clean current base schema and seed
scan2borrow_2_0.sql                      Existing full seeded database dump
sql/upgrade_bulk_borrowing.sql           Titles, copies, transactions, backfill
sql/upgrade_approval_status_sync.sql     Repair approved/rejected normalized item statuses
sql/upgrade_barcode_printing.sql         Printed markers and print batches
sql/upgrade_copy_audit_trail.sql         Copy statuses and audit backfill
sql/upgrade_renewals.sql                  Renewal request table
sql/upgrade_reservations.sql              Reservation queue and holds
sql/upgrade_profile_change_requests.sql  Profile approval table
~~~

## 16. API groups for technical checking

The admin API docs page contains the full catalog. The most important endpoint groups are:

### Authentication

~~~text
POST /api/auth/borrower/login
POST /api/auth/staff/login
POST /api/auth/register
POST /api/auth/otp
POST /api/auth/guest/register
POST /api/auth/guest/otp
GET  /api/auth/session
POST /api/auth/logout
~~~

### Borrower circulation and settings

~~~text
GET  /api/student/books
POST /api/student/borrow
POST /api/student/return
GET  /api/student/history
GET  /api/student/settings
POST /api/student/settings
GET  /api/teacher/settings
POST /api/teacher/settings
GET  /api/receipt
~~~

### Staff operations

~~~text
GET  /api/staff/dashboard
GET  /api/staff/borrowers
GET  /api/staff/borrower
GET  /api/books
POST /api/books
GET  /api/book-copies
POST /api/book-copies
GET  /api/staff/copy-history
GET  /api/barcode-print-batches
POST /api/barcode-print-batches
GET  /api/staff/reports
GET  /api/staff/overdue
~~~

### Admin operations

~~~text
GET  /api/admin/profile-change-requests
POST /api/admin/profile-change-request-action
GET  /api/admin/staff
POST /api/admin/staff-action
GET  /api/admin/api-docs
~~~

Write operations require a session and CSRF token. Admin operations require the admin role.

## 17. Terminal quality checks

Run from the project root.

### Frontend tests

~~~powershell
Set-Location 'C:\xampp\htdocs\scan2borrow'
npm test
~~~

### Backend PHPUnit tests

~~~powershell
& 'C:\xampp\php\php.exe' 'backend\vendor\bin\phpunit' --configuration='backend\phpunit.xml' --colors=never
~~~

### PHPStan

~~~powershell
& 'C:\xampp\php\php.exe' 'backend\vendor\bin\phpstan' analyse --configuration='backend\phpstan.neon' --level=9 --no-progress
~~~

### PHP syntax check

~~~powershell
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
~~~

### Git checks

~~~powershell
git status --short --branch
git diff --check
git log --oneline -10
~~~

If a check fails, record the exact command and error.

## 18. Complete checking/demo script

Use this order during a presentation.

### Part 1: Public access

1. Open /.
2. Try /admin/staff while logged out.
3. Confirm redirect or denial.
4. Open /login.
5. Open /staff/login.
6. Explain borrower barcode login versus staff barcode-plus-password login.

### Part 2: Borrower circulation

1. Log in as a student.
2. Search for a book.
3. Add a physical copy to the cart.
4. Submit a loan request.
5. Log in as staff.
6. Approve the pending request.
7. Return to the borrower.
8. Show the active loan and receipt.
9. Return the book.
10. Show updated history and availability.

### Part 3: Inventory

1. Open Staff > Books.
2. Show a catalog title.
3. Open View copies.
4. Explain the difference between a title and a physical copy.
5. Edit a location or status.
6. Archive and restore a safe test copy.
7. Search its barcode in Copy History.

### Part 4: Printing and audit

1. Export unprinted barcodes.
2. Save the print page as PDF.
3. Export again and show skipped printed copies.
4. Search one copy in Copy History.
5. Explain acquisition, print, loan, return, status, and actor events.

### Part 5: Reservations and renewals

1. Reserve an unavailable title as a borrower.
2. Show its queue position to staff.
3. Request a renewal on an active loan.
4. Approve or reject it as staff.
5. Show the borrower decision and due-date result.

### Part 6: Guests

1. Register a guest.
2. Complete OTP verification.
3. Browse the catalog.
4. Submit a request with a photo.
5. Review it in Staff > Guest Requests.
6. Show the guest pass, borrowed list, history, receipt, and return flow.

### Part 7: Profile approval

1. Sign in as a student or teacher.
2. Open Settings.
3. Edit a name, contact, academic, or faculty field.
4. Attach a photo.
5. Submit for approval.
6. Show the Pending request.
7. Try to change the barcode and show that it is protected.
8. Sign in as admin.
9. Open Admin > Staff.
10. Compare before/after values.
11. Approve with a note.
12. Confirm the borrower notification and updated profile.
13. Repeat with rejection and show that old values remain.

### Part 8: Reporting and access control

1. Open Overdue.
2. Generate a report.
3. Export CSV.
4. Show staff notifications.
5. Open API docs as admin.
6. Try admin pages as librarian and show authorization denial.

## 19. Troubleshooting

### Missing profile-change table

Error:

~~~text
SQLSTATE[42S02]: Base table or view not found:
1146 Table 'scan2borrow_2.0.profile_change_requests' doesn't exist
~~~

Repair:

~~~powershell
Set-Location 'C:\xampp\htdocs\scan2borrow'
Get-Content -Raw 'sql\upgrade_profile_change_requests.sql' | & 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root
~~~

Verify:

~~~powershell
& 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root --database='scan2borrow_2.0' -e "SHOW TABLES LIKE 'profile_change_requests';"
~~~

### php is not recognized

~~~powershell
& 'C:\xampp\php\php.exe' -v
~~~

Use the full path in PHPUnit, PHPStan, and PHP lint commands.

### mysql is not recognized

~~~powershell
& 'C:\xampp\mysql\bin\mysql.exe' --version
~~~

### Apache returns 404

1. Confirm Apache is running.
2. Confirm the project is under C:\xampp\htdocs\scan2borrow.
3. Open http://localhost/scan2borrow/ first.
4. Check route spelling, such as /student/settings rather than /students/settings.
5. If the root works but clean routes fail, check Apache rewrite configuration.

### API returns 401 or 403

~~~powershell
Invoke-WebRequest 'http://localhost/scan2borrow/api/auth/session' -UseBasicParsing | Select-Object StatusCode, Content
~~~

- 401 means no valid session.
- 403 means a valid session exists but the role is not allowed.
- A CSRF error on a write action means the form/session token is stale or missing.

### OTP does not arrive

1. Check mail/SMS values in config\.env.
2. Use a Gmail App Password for Gmail.
3. Confirm the selected registration channel.
4. Check that the OTP table exists.
5. Inspect recent OTP rows:

~~~powershell
& 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root --database='scan2borrow_2.0' -e "SELECT id, barcode, is_verified, is_used, expires_at, created_at FROM otp_codes ORDER BY id DESC LIMIT 10;"
~~~

### Photo does not display

~~~powershell
Get-ChildItem 'uploads\photos' -Force
Test-Path 'uploads\photos'
~~~

The browser-visible path should normally begin with /scan2borrow/uploads/. Do not put arbitrary filesystem paths into a photo field.

### Notification does not appear

Confirm the main action first, then inspect notifications:

~~~powershell
& 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root --database='scan2borrow_2.0' -e "SELECT id, user_id, type, title, related_id, is_read, created_at FROM notifications ORDER BY id DESC LIMIT 20;"
~~~

For profile requests:

~~~powershell
& 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root --database='scan2borrow_2.0' -e "SELECT id, user_id, status, requested_at, reviewed_at, reviewed_by, review_note FROM profile_change_requests ORDER BY id DESC LIMIT 20;"
~~~

### Approved request still shows Pending

Run the repair migration after the normalized bulk-borrowing migration:

~~~powershell
Get-Content -Raw 'sql\upgrade_approval_status_sync.sql' | & 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root
~~~

Verify that no active approved item remains Pending:

~~~sql
SELECT COUNT(*) AS inconsistent_rows
FROM borrowing_items bi
JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
WHERE bt.approval_status = 'approved'
  AND bi.return_date IS NULL
  AND bi.status = 'Pending';
~~~

The expected count is `0`.

### Copy history is empty

Confirm the barcode belongs to book_copies:

~~~powershell
& 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root --database='scan2borrow_2.0' -e "SELECT id, barcode, status, deleted_at FROM book_copies ORDER BY id LIMIT 20;"
~~~

If normalized tables or events are missing:

~~~powershell
Get-Content -Raw 'sql\upgrade_bulk_borrowing.sql' | & 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root
Get-Content -Raw 'sql\upgrade_copy_audit_trail.sql' | & 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root
~~~

## 20. Short explanation to give during checking

> Scan2Borrow separates people, catalog titles, and physical copies. Students, teachers, and guests use different protected portals. Staff operate inventory and circulation. Loan, guest, reservation, renewal, printing, and profile workflows have clear approval boundaries. Each physical copy has a lifetime history, so staff can answer what happened, when it happened, and who performed it. Profile edits are requests rather than direct changes, so an administrator remains responsible for approving identity information.

