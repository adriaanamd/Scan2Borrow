# Frontend Page Matrix

This matrix records the route-to-feature ownership model for the vanilla Angular-like frontend. Every route keeps its existing authorization, markup, API, and UI/UX contract while loading one feature-owned HTML template and one native module entry.

| Route | Policy | Feature template | Page entry | Status |
| --- | --- | --- | --- | --- |
| / | public, redirect authenticated | features/auth/pages/login/login.html | features/auth/pages/login/entry.js | canonical |
| /login | public, redirect authenticated | features/auth/pages/login/login.html | features/auth/pages/login/entry.js | canonical |
| /staff/login | public, redirect authenticated | features/auth/pages/staff-login.html | features/auth/pages/staff-entry.js | canonical |
| /register | public | features/auth/pages/register/register.html | features/auth/pages/register/entry.js | canonical |
| /verify-otp | public | features/auth/pages/otp/otp.html | features/auth/pages/otp/entry.js | canonical |
| /guest/registration | public | features/auth/pages/guest-registration/guest-registration.html | features/auth/pages/guest-registration/entry.js | canonical |
| /guest/verify-otp | public | features/auth/pages/guest-otp/guest-otp.html | features/auth/pages/guest-otp/entry.js | canonical |
| /settings | guest | features/guest/pages/profile/profile.html | features/guest/pages/profile/entry.js | canonical |
| /student/settings | student | features/student/pages/settings/settings.html | features/student/pages/settings/student-settings.page.js | canonical |
| /teacher/settings | teacher | features/teacher/pages/settings/settings.html | features/teacher/pages/settings/teacher-settings.page.js | canonical |
| /staff/dashboard | admin, librarian | features/staff/pages/dashboard/dashboard.html | features/staff/pages/dashboard/staff-dashboard.page.js | canonical |
| /staff/books | admin, librarian | features/staff/pages/inventory/inventory.html | features/staff/pages/inventory/inventory.page.js | canonical |
| /staff/copy-history | admin, librarian | features/staff/pages/copy-history/copy-history.html | features/staff/pages/copy-history/entry.js | canonical |
| /staff/students | admin, librarian | features/staff/pages/borrowers/borrowers.html | features/staff/pages/borrowers/entry.js | canonical |
| /staff/borrower | admin, librarian | features/staff/pages/borrower-detail/borrower-detail.html | features/staff/pages/borrower-detail/entry.js | canonical |
| /staff/notify | admin, librarian | features/staff/pages/notify/notify.html | features/staff/pages/notify/entry.js | canonical |
| /staff/overdue | admin, librarian | features/staff/pages/overdue/overdue.html | features/staff/pages/overdue/entry.js | canonical |
| /staff/reports | admin, librarian | features/staff/pages/reports/reports.html | features/staff/pages/reports/entry.js | canonical |
| /staff/guest-requests | admin, librarian | features/staff/pages/guest-requests/guest-requests.html | features/staff/pages/guest-requests/entry.js | canonical |
| /student/dashboard | student | features/student/pages/dashboard/dashboard.html | features/student/pages/dashboard/student-dashboard.page.js | canonical |
| /student/search | student | features/student/pages/search/search.html | features/student/pages/search/student-search.page.js | canonical |
| /student/history | student | features/student/pages/history/history.html | features/student/pages/history/student-history.page.js | canonical |
| /student/activity | student | features/student/pages/activity/activity.html | features/student/pages/activity/student-activity.page.js | canonical |
| /receipt | student, teacher | features/student/pages/receipt/receipt.html | features/student/pages/receipt/receipt.page.js | canonical |
| /teacher/dashboard | teacher | features/teacher/pages/dashboard/dashboard.html | features/teacher/pages/dashboard/teacher-dashboard.page.js | canonical |
| /teacher/borrow | teacher | features/teacher/pages/borrow/borrow.html | features/teacher/pages/borrow/teacher-borrow.page.js | canonical |
| /teacher/history | teacher | features/teacher/pages/history/history.html | features/teacher/pages/history/teacher-history.page.js | canonical |
| /teacher/activity | teacher | features/teacher/pages/activity/activity.html | features/teacher/pages/activity/teacher-activity.page.js | canonical |
| /guest/dashboard | guest | features/guest/pages/dashboard/dashboard.html | features/guest/pages/dashboard/guest-dashboard.page.js | canonical |
| /guest/profile | guest | features/guest/pages/profile/profile.html | features/guest/pages/profile/guest-profile.page.js | canonical |
| /guest/profile-verify-otp | guest | features/auth/pages/profile-otp/profile-otp.html | features/auth/pages/profile-otp/entry.js | canonical |
| /guest/browse | guest | features/guest/pages/browse/browse.html | features/guest/pages/browse/guest-browse.page.js | canonical |
| /guest/borrowed | guest | features/guest/pages/borrowed/borrowed.html | features/guest/pages/borrowed/guest-borrowed.page.js | canonical |
| /guest/history | guest | features/guest/pages/history/history.html | features/guest/pages/history/guest-history.page.js | canonical |
| /guest/borrow-request | guest | features/guest/pages/borrow-request/borrow-request.html | features/guest/pages/borrow-request/guest-borrow-request.page.js | canonical |
| /guest/return-book | guest | features/guest/pages/return/return.html | features/guest/pages/return/guest-return.page.js | canonical |
| /guest/pass | guest | features/guest/pages/pass/pass.html | features/guest/pages/pass/guest-pass.page.js | canonical |
| /guest/receipt | guest | features/guest/pages/receipt/receipt.html | features/guest/pages/receipt/guest-receipt.page.js | canonical |
| /admin/staff | admin | features/staff/pages/admin-staff/admin-staff.html | features/staff/pages/admin-staff/entry.js | canonical |
| /admin/api-docs | admin | features/staff/pages/api-docs/api-docs.html | features/staff/pages/api-docs/entry.js | canonical |

## Retired duplicate trees

The following runtime trees no longer exist and must not be recreated:

- `frontend/pages/` — duplicate static page templates.
- `frontend/assets/js/pages/` — monolithic/page-specific controllers superseded by feature modules.
- `frontend/assets/js/guest/` — guest controllers superseded by guest and auth feature modules.

`frontend/assets/js/core/` remains intentionally because its navbar, auth-brand, icon, media, and scanner helpers are shared browser infrastructure. The route table still denies direct access to old HTML URLs for compatibility and defense in depth.
