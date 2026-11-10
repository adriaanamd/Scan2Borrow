# Library Aide Workspace Implementation Plan

> For agentic workers: REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

Goal: Add a seeded LIBRARY001 Admin account with a restricted library_aide access profile and a dedicated circulation-oriented workspace, while preserving ADMIN001 as the full system administrator.

Architecture: Keep the existing database admin role for staff login and operational compatibility, then add a nullable access_profile value to distinguish system_admin from library_aide. Carry that profile through the user account, authenticated principal, session identity, session API, page authorizer, and controller policy. Build a new aide dashboard and profile-aware navigation while reusing existing circulation, borrower, inventory, reports, and notification services.

Tech Stack: PHP 8.5.12, framework-free PHP modular monolith, PDO/MySQL SQL scripts, PHPUnit 11, PHPStan 2, vanilla HTML/CSS/JavaScript, Bootstrap 5.3, Node native test runner.

## Global Constraints

- ADMIN001 remains role = 'admin' with access_profile = 'system_admin'.
- Seed LIBRARY001 with role = 'admin', access_profile = 'library_aide', display name Library Aide, and temporary password library123; require a first-login change in documentation.
- Do not hardcode LIBRARY001 in authorization logic; use the persisted access profile.
- Existing admin accounts with a missing profile default to system_admin for backward compatibility.
- Aides may use circulation, borrowers, inventory, reports, and notifications.
- Aides may not manage staff accounts, change roles or permissions, approve profile changes, manage courses, view API docs, archive inventory, or delete inventory.
- Enforce denied actions in backend policies and controllers; hiding frontend controls is not sufficient.
- Preserve all existing tests and existing user changes in the main checkout.
- Use the explicit patched runtime: C:\Users\Adriaan M. Dimate\AppData\Local\Programs\pharmacy-backend-toolchain\php-8.5.12\php.exe.
- Do not add dependencies.
- Use the Swiss visual direction already present in circulation and copy-history surfaces: #F7F7F8, #FFFFFF, #002FA7, #101114, #62666F, hairline rules, compact tables, and Helvetica Neue/system fallback.

## File Map

Create:

- backend/src/Domain/Auth/StaffAccessProfile.php
- backend/src/Domain/Auth/StaffAccessPolicy.php
- sql/upgrade_library_aide_access.sql
- backend/tests/Unit/Auth/StaffAccessPolicyTest.php
- backend/tests/Feature/LibraryAideAuthorizationTest.php
- backend/tests/Feature/LibraryAideSchemaContractTest.php
- frontend/features/staff/pages/aide-dashboard/aide-dashboard.html
- frontend/features/staff/pages/aide-dashboard/aide-dashboard.css
- frontend/features/staff/pages/aide-dashboard/aide-dashboard.page.js
- frontend/features/staff/pages/aide-dashboard/entry.js
- frontend/tests/library-aide.test.js

Modify:

- backend/src/Domain/Auth/Principal.php
- backend/src/Domain/Auth/SessionIdentity.php
- backend/src/Domain/Auth/UserAccount.php
- backend/src/Application/Services/AuthenticationService.php
- backend/src/Application/Services/SessionService.php
- backend/src/Http/Controllers/AuthController.php
- backend/src/Http/Middleware/AuthorizationMiddleware.php
- backend/src/Http/Middleware/SessionPageAccessAuthorizer.php
- backend/src/Http/Routing/PageRoute.php
- backend/src/Http/Routing/PageRouteTable.php
- backend/src/Infrastructure/Persistence/PdoUserRepository.php
- backend/src/Bootstrap/ApplicationFactory.php
- backend/src/Http/Controllers/StaffController.php
- backend/src/Http/Controllers/CourseController.php
- backend/src/Http/Controllers/ApiDocumentationController.php
- backend/src/Http/Controllers/BookController.php
- backend/src/Http/Controllers/BookCopyController.php
- sql/install.sql
- sql/database.sql
- README.md
- frontend/assets/js/core/app-navbar.js
- frontend/app/shared/components/app-navbar/app-navbar.component.js
- frontend/tests/session.test.js
- frontend/tests/layout-components.test.js or frontend/tests/navbar-cache.test.js
- backend/tests/Feature/InstallSchemaContractTest.php
- backend/tests/Feature/SchemaContractTest.php
- backend/tests/Feature/PageRouteTableTest.php
- backend/tests/Feature/AuthControllerTest.php
- backend/tests/Unit/Auth/AuthContractsTest.php
- backend/tests/Unit/Auth/AuthenticationServiceTest.php

---

### Task 1: Add access-profile domain and session plumbing

Files:

- Create backend/src/Domain/Auth/StaffAccessProfile.php
- Create backend/src/Domain/Auth/StaffAccessPolicy.php
- Modify backend/src/Domain/Auth/Principal.php
- Modify backend/src/Domain/Auth/SessionIdentity.php
- Modify backend/src/Domain/Auth/UserAccount.php
- Modify backend/src/Infrastructure/Persistence/PdoUserRepository.php
- Modify backend/src/Application/Services/SessionService.php
- Modify backend/src/Application/Services/AuthenticationService.php
- Modify backend/src/Http/Controllers/AuthController.php
- Test backend/tests/Unit/Auth/StaffAccessPolicyTest.php
- Test backend/tests/Unit/Auth/AuthContractsTest.php
- Test backend/tests/Unit/Auth/AuthenticationServiceTest.php
- Test backend/tests/Feature/AuthControllerTest.php

Interfaces:

- StaffAccessProfile has SYSTEM_ADMIN = system_admin and LIBRARY_AIDE = library_aide.
- StaffAccessPolicy::isStaff(?SessionIdentity): bool allows active Admin or Librarian identities.
- StaffAccessPolicy::isSystemAdmin(?SessionIdentity): bool allows Admin identities with null or system_admin profile, but not library_aide.
- StaffAccessPolicy::isLibraryAide(?SessionIdentity): bool allows only Admin identities with library_aide.
- StaffAccessPolicy::canMutateInventory(?SessionIdentity, string): bool rejects archive and delete for library_aide.
- AuthenticationService::loginStaff() redirects library_aide to /staff/aide/dashboard and other staff to /staff/dashboard.
- AuthController::session() adds access_profile to the authenticated payload.

- [ ] Step 1: Add a failing unit test:

~~~php
public function testLibraryAideIsOperationalStaffButNotSystemAdmin(): void
{
    $policy = new StaffAccessPolicy();
    $identity = new SessionIdentity(
        11,
        Role::ADMIN,
        'library-aide-session',
        StaffAccessProfile::LIBRARY_AIDE,
    );

    self::assertTrue($policy->isStaff($identity));
    self::assertTrue($policy->isLibraryAide($identity));
    self::assertFalse($policy->isSystemAdmin($identity));
    self::assertFalse($policy->canMutateInventory($identity, 'archive'));
    self::assertFalse($policy->canMutateInventory($identity, 'delete'));
    self::assertTrue($policy->canMutateInventory($identity, 'update'));
}
~~~

- [ ] Step 2: Verify the expected red state:

~~~powershell
$php = 'C:\Users\Adriaan M. Dimate\AppData\Local\Programs\pharmacy-backend-toolchain\php-8.5.12\php.exe'
& $php backend\vendor\bin\phpunit --configuration=backend\phpunit.xml --filter StaffAccessPolicyTest
~~~

Expected: missing StaffAccessPolicy and profile-aware SessionIdentity constructor errors.

- [ ] Step 3: Implement the enum and append optional ?StaffAccessProfile $accessProfile = null to Principal, SessionIdentity, and UserAccount. Add accessProfile() accessors. Treat a null profile on an Admin principal as system_admin in StaffAccessPolicy so existing fixtures retain behavior.

- [ ] Step 4: Make PdoUserRepository select access_profile and parse it with StaffAccessProfile::tryFrom(). Carry the profile through AuthenticationService::completeLogin() and SessionService::login(). Make AuthController::sessionData() return authenticated, user_id, role, and access_profile. Redirect a library aide to /staff/aide/dashboard.

- [ ] Step 5: Run the focused auth tests and commit:

~~~powershell
& $php backend\vendor\bin\phpunit --configuration=backend\phpunit.xml --filter 'StaffAccessPolicyTest|AuthContractsTest|AuthenticationServiceTest|AuthControllerTest'
git add backend/src/Domain/Auth backend/src/Infrastructure/Persistence/PdoUserRepository.php backend/src/Application/Services/AuthenticationService.php backend/src/Application/Services/SessionService.php backend/src/Http/Controllers/AuthController.php backend/tests/Unit/Auth/StaffAccessPolicyTest.php backend/tests/Unit/Auth/AuthContractsTest.php backend/tests/Unit/Auth/AuthenticationServiceTest.php backend/tests/Feature/AuthControllerTest.php
git commit -m "feat: carry staff access profiles through auth"
~~~

Expected: focused auth tests pass.

### Task 2: Add schema upgrade and seeded accounts

Files:

- Create sql/upgrade_library_aide_access.sql
- Modify sql/install.sql
- Modify sql/database.sql
- Modify README.md
- Create backend/tests/Feature/LibraryAideSchemaContractTest.php
- Modify backend/tests/Feature/InstallSchemaContractTest.php
- Modify backend/tests/Feature/SchemaContractTest.php

- [ ] Step 1: Add failing SQL contract assertions:

~~~php
self::assertStringContainsString("ENUM('system_admin','library_aide')", $install);
self::assertStringContainsString("'ADMIN001'", $install);
self::assertStringContainsString("'LIBRARY001'", $install);
self::assertStringContainsString("'library_aide'", $install);
self::assertStringContainsString('$2y$12$6wjeleYKh0lntzDAIf7fuOhQN3KG9.tIQjd1x42uAnW3ECy2nUmSe', $install);
self::assertStringContainsString('UPDATE users SET access_profile', $upgrade);
self::assertStringContainsString('INSERT INTO users', $upgrade);
~~~

- [ ] Step 2: Run the red schema tests:

~~~powershell
& $php backend\vendor\bin\phpunit --configuration=backend\phpunit.xml --filter 'LibraryAideSchemaContractTest|InstallSchemaContractTest|SchemaContractTest'
~~~

- [ ] Step 3: Add access_profile ENUM('system_admin','library_aide') NULL after role in both canonical schema definitions. Set ADMIN001 to system_admin and add the LIBRARY001 seed:

~~~sql
('LIBRARY001', 'Library', '', 'Aide', NULL, NULL, 'library@scan2borrow.local', NULL, 'admin',
 '$2y$12$6wjeleYKh0lntzDAIf7fuOhQN3KG9.tIQjd1x42uAnW3ECy2nUmSe', 'active', 'library_aide');
~~~

Keep the existing ADMIN001 password/hash unchanged.

- [ ] Step 4: Create sql/upgrade_library_aide_access.sql:

~~~sql
USE scan2borrow_2.0;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS access_profile ENUM('system_admin','library_aide') NULL AFTER role;

UPDATE users
SET access_profile = 'system_admin'
WHERE role = 'admin' AND (access_profile IS NULL OR access_profile = '');

INSERT INTO users
    (barcode, firstname, middlename, lastname, course, year_level, email, contact_no, role, password_hash, status, access_profile)
SELECT
    'LIBRARY001', 'Library', '', 'Aide', NULL, NULL, 'library@scan2borrow.local', NULL, 'admin',
    '$2y$12$6wjeleYKh0lntzDAIf7fuOhQN3KG9.tIQjd1x42uAnW3ECy2nUmSe', 'active', 'library_aide'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE barcode = 'LIBRARY001');
~~~

Update README.md immediately after upgrade_security.sql and document both staff barcodes, the temporary library123 password, and first-login change requirement.

- [ ] Step 5: Run schema tests again and commit:

~~~powershell
git add sql/install.sql sql/database.sql sql/upgrade_library_aide_access.sql README.md backend/tests/Feature/LibraryAideSchemaContractTest.php backend/tests/Feature/InstallSchemaContractTest.php backend/tests/Feature/SchemaContractTest.php
git commit -m "feat: seed library aide access profile"
~~~

Expected: focused schema assertions pass.

### Task 3: Enforce aide/system-admin permissions in backend routes and controllers

Files:

- Modify backend/src/Http/Middleware/AuthorizationMiddleware.php
- Modify backend/src/Http/Middleware/SessionPageAccessAuthorizer.php
- Modify backend/src/Http/Routing/PageRoute.php
- Modify backend/src/Http/Routing/PageRouteTable.php
- Modify backend/src/Http/Controllers/StaffController.php
- Modify backend/src/Http/Controllers/CourseController.php
- Modify backend/src/Http/Controllers/ApiDocumentationController.php
- Modify backend/src/Http/Controllers/BookController.php
- Modify backend/src/Http/Controllers/BookCopyController.php
- Modify backend/src/Bootstrap/ApplicationFactory.php
- Create backend/tests/Feature/LibraryAideAuthorizationTest.php
- Modify backend/tests/Feature/PageRouteTableTest.php

- [ ] Step 1: Add failing denial tests with an Admin identity carrying StaffAccessProfile::LIBRARY_AIDE:

~~~php
self::assertSame(401, $staffController->adminStaff($request)->status());
self::assertSame(401, $staffController->adminAction($request)->status());
self::assertSame(401, $staffController->profileChangeRequests($request)->status());
self::assertSame(401, $courseController->adminIndex($request)->status());
self::assertSame(401, $courseController->store($request)->status());
self::assertSame(403, $apiDocumentationController->index($request)->status());
self::assertSame(401, $bookController->mutate($archiveRequest)->status());
self::assertSame(401, $bookCopyController->mutate($deleteRequest)->status());
~~~

Also assert the aide can use staff dashboard, circulation, inventory read/update, reports, and notification paths. Keep existing Admin fixtures with null profile allowed.

- [ ] Step 2: Verify the red state:

~~~powershell
& $php backend\vendor\bin\phpunit --configuration=backend\phpunit.xml --filter 'LibraryAideAuthorizationTest|PageRouteTableTest|CourseControllerTest|ApiDocumentationControllerTest|BookControllerTest|BookCopyControllerTest'
~~~

- [ ] Step 3: Extend PageRoute with optional ?StaffAccessProfile $requiredProfile = null and requiredProfile(). Update SessionPageAccessAuthorizer to carry the session profile into Principal and reject a route when its required profile is not satisfied. Mark /admin/staff and /admin/api-docs as SYSTEM_ADMIN; add /staff/aide/dashboard with LIBRARY_AIDE and the Admin role.

Update AuthorizationMiddleware::homeFor() so an Admin principal with library_aide resolves to /staff/aide/dashboard, while other Admin/Librarian principals resolve to /staff/dashboard.

- [ ] Step 4: Instantiate one StaffAccessPolicy in ApplicationFactory and inject it into the page authorizer, StaffController, CourseController, ApiDocumentationController, BookController, and BookCopyController. Replace role-only Admin checks with access->isSystemAdmin(identity). Keep operational staff checks role-based through the policy. Before BookController and BookCopyController execute archive, restore, or delete, check canMutateInventory(); reject aide archive/delete.

- [ ] Step 5: Run the focused command again and commit:

~~~powershell
git add backend/src/Domain/Auth/StaffAccessPolicy.php backend/src/Http/Middleware backend/src/Http/Routing/PageRoute.php backend/src/Http/Routing/PageRouteTable.php backend/src/Http/Controllers/StaffController.php backend/src/Http/Controllers/CourseController.php backend/src/Http/Controllers/ApiDocumentationController.php backend/src/Http/Controllers/BookController.php backend/src/Http/Controllers/BookCopyController.php backend/src/Bootstrap/ApplicationFactory.php backend/tests/Feature/LibraryAideAuthorizationTest.php backend/tests/Feature/PageRouteTableTest.php
git commit -m "feat: enforce library aide permissions"
~~~

Expected: aide denial/allowance assertions pass and existing Admin/Librarian assertions remain green.

### Task 4: Add the aide shell and profile-aware navigation

Files:

- Create frontend/features/staff/pages/aide-dashboard/aide-dashboard.html
- Create frontend/features/staff/pages/aide-dashboard/aide-dashboard.css
- Create frontend/features/staff/pages/aide-dashboard/aide-dashboard.page.js
- Create frontend/features/staff/pages/aide-dashboard/entry.js
- Modify frontend/assets/js/core/app-navbar.js
- Modify frontend/app/shared/components/app-navbar/app-navbar.component.js
- Modify frontend/tests/session.test.js
- Modify frontend/tests/layout-components.test.js
- Modify frontend/tests/navbar-cache.test.js
- Create frontend/tests/library-aide.test.js

- [ ] Step 1: Add failing frontend contracts:

~~~js
assert.equal(session.data.access_profile, 'library_aide');
assert.match(aideTemplate, /data-app-page="staff-aide-dashboard"/);
assert.match(aideTemplate, /data-page="staff-aide-dashboard"/);
assert.match(aideTemplate, /data-aide-scan-lane/);
assert.match(aideTemplate, /Borrower/);
assert.match(aideTemplate, /Copies/);
assert.match(aideTemplate, /Confirm/);
assert.doesNotMatch(aideTemplate, /Staff Management|College Settings|API Docs/);
assert.match(navbarSource, /library_aide/);
assert.match(navbarSource, /renderLibraryAide/);
~~~

Add a navbar test with session payload { role: 'admin', access_profile: 'library_aide' } and assert links for Dashboard, Circulation, Borrowers, Inventory, Reports, Notifications, and Logout, with no Admin/Staff/API Docs link.

- [ ] Step 2: Verify the red state:

~~~powershell
npm test -- frontend/tests/library-aide.test.js frontend/tests/session.test.js frontend/tests/layout-components.test.js frontend/tests/navbar-cache.test.js
~~~

- [ ] Step 3: Change both navbar implementations to resolve the full session identity. For role admin plus access_profile library_aide, render a dedicated aide nav with these paths:

~~~text
/scan2borrow/staff/aide/dashboard
/scan2borrow/staff/circulation
/scan2borrow/staff/students
/scan2borrow/staff/books
/scan2borrow/staff/reports
/scan2borrow/staff/notify
/scan2borrow/logout
~~~

Retain the existing Admin/Librarian nav for other staff. Cache the profile alongside role or cache the complete identity so a stale Admin role cannot render system-admin links for an aide account. Recognize /staff/aide/ as a staff path.

- [ ] Step 4: Build the aide template with existing style.css, Bootstrap, confirmation, navbar, scanner, and icons assets. Add staff-aide-dashboard markers, page header, four real-data stat mounts, scan-lane mount, pending-activity mount, quick links to operational pages, and explicit loading/empty/error states. Use scoped aide-dashboard__ classes and no fabricated names or counts.

entry.js must use createPageContext({ document, window }), instantiate StaffDashboardService and StaffNotificationService, then start AideDashboardPage.

- [ ] Step 5: Run focused frontend contracts and commit:

~~~powershell
git add frontend/features/staff/pages/aide-dashboard frontend/assets/js/core/app-navbar.js frontend/app/shared/components/app-navbar/app-navbar.component.js frontend/tests/library-aide.test.js frontend/tests/session.test.js frontend/tests/layout-components.test.js frontend/tests/navbar-cache.test.js
git commit -m "feat: add library aide workspace shell"
~~~

Expected: all aide template/navigation/session tests pass.

### Task 5: Wire aide behavior and operational surfaces

Files:

- Modify frontend/features/staff/pages/aide-dashboard/aide-dashboard.page.js
- Modify frontend/features/staff/pages/aide-dashboard/aide-dashboard.html
- Modify frontend/features/staff/pages/aide-dashboard/aide-dashboard.css
- Modify frontend/features/staff/pages/inventory/inventory.html and/or its page controller only for aide-specific destructive-control presentation
- Modify frontend/features/staff/pages/reports/reports.html only for aide-shell styling hooks if needed
- Modify frontend/features/staff/pages/notify/notify.html only for aide-shell styling hooks if needed
- Modify frontend/tests/library-aide.test.js
- Modify frontend/tests/staff-pages.test.js

- [ ] Step 1: Add failing behavior tests:

~~~js
assert.equal(nodes.get('[data-aide-stat="available-copies"]').textContent, '42');
assert.equal(nodes.get('[data-aide-stat="pending-requests"]').textContent, '3');
assert.equal(nodes.get('[data-aide-scan-step="borrower"]').classList.contains('is-active'), true);
assert.match(nodes.get('[data-aide-activity]').innerHTML, /No pending activity|Pending/);
~~~

Add a markup contract that inventory archive/delete controls are absent or marked data-system-admin-only, while reversible edit controls remain present.

- [ ] Step 2: Verify the red state:

~~~powershell
npm test -- frontend/tests/library-aide.test.js frontend/tests/staff-pages.test.js
~~~

- [ ] Step 3: Use StaffDashboardService.load() for stats/activity and StaffNotificationService.load() for notification counts. Render only fields returned by those services. Keep the scan lane keyboard-friendly with borrower and book-copy inputs, a review list, and a confirmation action that links to the existing circulation page rather than reimplementing circulation business logic. Add teardown for polling intervals on destroy().

- [ ] Step 4: Use profile-aware navbar and backend policy as the main boundary. Add only small markup/style hooks to inventory, reports, and notifications so the aide shell retains the Swiss surface. Do not copy existing inventory/report/notification controllers. Ensure no aide frontend link points to /admin/staff, /admin/api-docs, course settings, or profile approvals.

- [ ] Step 5: Run focused tests and commit:

~~~powershell
git add frontend/features/staff/pages/aide-dashboard frontend/features/staff/pages/inventory frontend/features/staff/pages/reports frontend/features/staff/pages/notify frontend/tests/library-aide.test.js frontend/tests/staff-pages.test.js
git commit -m "feat: wire aide desk workflows"
~~~

### Task 6: Run complete verification and merge

- [ ] Step 1: Run targeted backend tests:

~~~powershell
$php = 'C:\Users\Adriaan M. Dimate\AppData\Local\Programs\pharmacy-backend-toolchain\php-8.5.12\php.exe'
& $php backend\vendor\bin\phpunit --configuration=backend\phpunit.xml --filter 'StaffAccessPolicyTest|LibraryAideAuthorizationTest|LibraryAideSchemaContractTest|AuthContractsTest|AuthenticationServiceTest|AuthControllerTest|PageRouteTableTest|CourseControllerTest|ApiDocumentationControllerTest|BookControllerTest|BookCopyControllerTest'
~~~

- [ ] Step 2: Run targeted frontend tests:

~~~powershell
npm test -- frontend/tests/library-aide.test.js frontend/tests/session.test.js frontend/tests/layout-components.test.js frontend/tests/navbar-cache.test.js frontend/tests/staff-pages.test.js
~~~

- [ ] Step 3: Run the full root frontend gate:

~~~powershell
npm test
~~~

Expected: no new failures beyond documented pre-feature baseline failures.

- [ ] Step 4: Run the full backend CI-equivalent gate with PHP 8.5.12:

~~~powershell
$php = 'C:\Users\Adriaan M. Dimate\AppData\Local\Programs\pharmacy-backend-toolchain\php-8.5.12\php.exe'
$composer = 'C:\Users\Adriaan M. Dimate\AppData\Local\Programs\pharmacy-backend-toolchain\composer.phar'
& $php $composer validate --working-dir=backend --no-check-publish
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
& $php $composer run quality --working-dir=backend
exit $LASTEXITCODE
~~~

The ignored PHPMailer directory is not present in the worktree. If mail tests require it, create a local junction only for verification and do not stage it:

~~~powershell
$source = 'C:\Users\Adriaan M. Dimate\Desktop\development\personal\scan2borrow\PHPMailer'
$target = 'C:\Users\Adriaan M. Dimate\Desktop\development\personal\scan2borrow\.worktrees\library-aide-workspace\PHPMailer'
if (-not (Test-Path -LiteralPath $target)) { New-Item -ItemType Junction -Path $target -Target $source | Out-Null }
~~~

- [ ] Step 5: Inspect and merge only after evidence:

~~~powershell
git diff master...HEAD --check
git diff master...HEAD --stat
git status --short --branch
git status --short --branch
git merge --no-ff feat/library-aide-workspace -m "Merge library aide workspace"
~~~

Confirm only approved feature/spec/plan files are present and no package-lock.json, PHPMailer junction, uploads, or unrelated user files are staged. After merging, rerun npm test and the PHP 8.5.12 Composer quality gate from the main checkout. Report any pre-existing baseline failures explicitly; do not claim the merge is green without fresh output.

