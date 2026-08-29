# Frontend Page Parity Matrix

This matrix is the migration inventory for the Angular-like vanilla frontend refactor. Existing route behavior, UI/UX, DOM contracts, and API calls are the baseline for every row.

| Route | Policy | Legacy template | Legacy script | Canonical template | Page module | Bootstrap | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| / | public, redirect authenticated | pages/login.html | assets/js/pages/auth.js | features/auth/pages/login/login.html | auth/login | auth-page.js | legacy |
| /login | public, redirect authenticated | pages/login.html | assets/js/pages/auth.js | features/auth/pages/login/login.html | auth/login | auth-page.js | legacy |
| /staff/login | public, redirect authenticated | pages/staff-login.html | assets/js/pages/auth.js | features/auth/pages/staff-login/staff-login.html | auth/staff-login | auth-page.js | legacy |
| /register | public | pages/register.html | assets/js/pages/registration.js | features/auth/pages/register/register.html | auth/register | auth-page.js | legacy |
| /verify-otp | public | pages/verify-otp.html | assets/js/pages/otp.js | features/auth/pages/otp/otp.html | auth/otp | auth-page.js | legacy |
| /guest/registration | public | pages/guest-registration.html | assets/js/guest/registration.js | features/auth/pages/guest-registration/guest-registration.html | auth/guest-registration | auth-page.js | legacy |
| /guest/verify-otp | public | pages/guest-verify-otp.html | assets/js/guest/otp.js | features/auth/pages/guest-otp/guest-otp.html | auth/guest-otp | auth-page.js | legacy |
| /settings | guest | pages/guest-profile.html | assets/js/guest/profile.js | features/guest/pages/profile/profile.html | guest/profile | guest-page.js | legacy |
| /student/settings | student | pages/student-settings.html | assets/js/pages/student-settings.js | features/student/pages/settings/settings.html | student/settings | student-page.js | legacy |
| /teacher/settings | teacher | pages/teacher-settings.html | assets/js/pages/student-settings.js | features/teacher/pages/settings/settings.html | teacher/settings | teacher-page.js | legacy |
| /staff/dashboard | admin,librarian | pages/staff-dashboard.html | assets/js/pages/staff.js | features/staff/pages/dashboard/dashboard.html | staff/dashboard | staff-page.js | legacy |
| /staff/books | admin,librarian | pages/staff-books.html | assets/js/pages/inventory.js | features/staff/pages/inventory/inventory.html | staff/inventory | staff-page.js | legacy |
| /staff/students | admin,librarian | pages/staff-students.html | assets/js/pages/staff.js | features/staff/pages/borrowers/borrowers.html | staff/borrowers | staff-page.js | legacy |
| /staff/borrower | admin,librarian | pages/staff-borrower.html | assets/js/pages/staff.js | features/staff/pages/borrower-detail/borrower-detail.html | staff/borrower-detail | staff-page.js | legacy |
| /staff/notify | admin,librarian | pages/staff-notify.html | assets/js/pages/staff.js | features/staff/pages/notifications/notifications.html | staff/notifications | staff-page.js | legacy |
| /staff/overdue | admin,librarian | pages/staff-overdue.html | assets/js/pages/staff.js | features/staff/pages/overdue/overdue.html | staff/overdue | staff-page.js | legacy |
| /staff/reports | admin,librarian | pages/staff-reports.html | assets/js/pages/staff.js | features/staff/pages/reports/reports.html | staff/reports | staff-page.js | legacy |
| /staff/guest-requests | admin,librarian | pages/staff-guest-requests.html | assets/js/pages/staff.js | features/staff/pages/guest-requests/guest-requests.html | staff/guest-requests | staff-page.js | legacy |
| /student/dashboard | student | pages/student-dashboard.html | assets/js/pages/borrower-dashboard.js | features/student/pages/dashboard/dashboard.html | student/dashboard | student-page.js | legacy |
| /student/search | student,teacher | pages/student-search.html | assets/js/pages/student-search.js | features/student/pages/search/search.html | student/search | student-page.js | legacy |
| /student/history | student,teacher | pages/student-history.html | assets/js/pages/student-history.js | features/student/pages/history/history.html | student/history | student-page.js | legacy |
| /receipt | student,teacher | pages/receipt.html | assets/js/pages/receipt.js | features/student/pages/receipt/receipt.html | student/receipt | student-page.js | legacy |
| /teacher/dashboard | teacher | pages/teacher-dashboard.html | assets/js/pages/teacher-dashboard.js | features/teacher/pages/dashboard/dashboard.html | teacher/dashboard | teacher-page.js | legacy |
| /guest/dashboard | guest | pages/guest-dashboard.html | assets/js/guest/dashboard.js | features/guest/pages/dashboard/dashboard.html | guest/dashboard | guest-page.js | legacy |
| /guest/profile | guest | pages/guest-profile.html | assets/js/guest/profile.js | features/guest/pages/profile/profile.html | guest/profile | guest-page.js | legacy |
| /guest/profile-verify-otp | guest | pages/guest-profile-verify-otp.html | assets/js/guest/otp.js | features/guest/pages/profile-otp/profile-otp.html | guest/profile-otp | guest-page.js | legacy |
| /guest/browse | guest | pages/guest-browse-books.html | assets/js/guest/browse.js | features/guest/pages/browse/browse.html | guest/browse | guest-page.js | legacy |
| /guest/borrowed | guest | pages/guest-borrowed-books.html | assets/js/guest/borrowed.js | features/guest/pages/borrowed/borrowed.html | guest/borrowed | guest-page.js | legacy |
| /guest/history | guest | pages/guest-borrowing-history.html | assets/js/guest/history.js | features/guest/pages/history/history.html | guest/history | guest-page.js | legacy |
| /guest/borrow-request | guest | pages/guest-borrow-request.html | assets/js/guest/borrow-request.js | features/guest/pages/borrow-request/borrow-request.html | guest/borrow-request | guest-page.js | legacy |
| /guest/return-book | guest | pages/guest-return-book.html | assets/js/guest/return-book.js | features/guest/pages/return-book/return-book.html | guest/return-book | guest-page.js | legacy |
| /guest/pass | guest | pages/guest-pass.html | assets/js/guest/pass.js | features/guest/pages/pass/pass.html | guest/pass | guest-page.js | legacy |
| /guest/receipt | guest | pages/guest-receipt.html | assets/js/guest/receipt.js | features/guest/pages/receipt/receipt.html | guest/receipt | guest-page.js | legacy |
| /admin/staff | admin | pages/admin-staff.html | assets/js/pages/staff.js | features/staff/pages/staff-management/staff-management.html | staff/staff-management | staff-page.js | legacy |
| /admin/api-docs | admin | pages/admin-api-docs.html | assets/js/pages/api-docs.js | features/staff/pages/api-docs/api-docs.html | staff/api-docs | staff-page.js | legacy |

## Current extraction risks

- assets/js/pages/staff.js combines dashboard, approvals, borrowers, reports, notifications, guest requests, and admin staff behavior.
- assets/js/pages/inventory.js combines inventory API calls, filters, selection state, drawer rendering, upload preview, and mutation forms.
- assets/js/pages/borrower-dashboard.js combines dashboard rendering, barcode generation, borrow/return forms, and toast behavior.
- Several HTML pages repeat script ordering and inline styles that must remain visually equivalent after module cutover.
- The existing navbar and auth-brand modules are reusable but currently self-start through document-level listeners; page bootstraps must own lifecycle.
