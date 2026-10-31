# Staff Workflow Repair Implementation Plan

> **Execution note:** Implement this plan in the current approved workspace. Preserve unrelated dirty files and commit each completed task separately. Use test-first development for behavior changes.

## Goal

Repair notification actions, desk checkout/approval coverage, and approved borrower-photo display across staff workflow surfaces.

## Task 1: Record the design and baseline

Files:

- `docs/superpowers/specs/2026-10-31-staff-workflow-repair-design.md`
- `docs/superpowers/plans/2026-10-31-staff-workflow-repair.md`

Steps:

1. Confirm the existing dirty worktree files and leave them unstaged.
2. Review the diff and run `git diff --check`.
3. Commit only these two planning documents as `docs: plan staff workflow repair`.

## Task 2: Add notification regression tests first

Files:

- `frontend/tests/staff-utils.test.js`
- `backend/tests/Unit/Mail/BorrowerNotificationServiceTest.php`

Steps:

1. Add a browser-page test proving email and SMS controls invoke the injected notification service, disable unavailable channels, and render the returned status.
2. Add backend tests for successful SMS delivery, missing contact numbers, and provider failures.
3. Run the focused frontend test file and the focused PHP notification test; confirm the new behavior fails before implementation.
4. Commit tests as `test: cover staff notification actions`.

## Task 3: Implement notification page actions

Files:

- `frontend/features/staff/pages/notify/notify.page.js`

Steps:

1. Bind the page's email and SMS buttons during startup.
2. Disable a channel when its borrower data is absent.
3. Call `StaffNotificationService.notify` with the borrower id and channel.
4. Render success and error feedback into the existing alert nodes without allowing duplicate submissions.
5. Run the focused frontend tests and commit as `feat: wire staff notification actions`.

## Task 4: Implement SMS delivery in the backend

Files:

- `backend/src/Application/Services/BorrowerNotificationService.php`
- `backend/src/Bootstrap/ApplicationFactory.php`
- `backend/tests/Unit/Mail/BorrowerNotificationServiceTest.php`

Steps:

1. Inject an optional `SmsSenderInterface` for compatibility with existing callers and use `SmsSenderFactory::create()` in the application factory.
2. Validate the borrower contact number before delivery.
3. Compose a concise borrower/loan message and send it through the configured adapter.
4. Convert provider exceptions into a safe failed result while preserving the existing email path.
5. Run the focused PHP notification tests, static analysis, and commit as `feat: enable borrower SMS notifications`.

## Task 5: Add borrower-photo regression tests

Files:

- `frontend/tests/staff-pages.test.js`
- `frontend/tests/staff-circulation.test.js`
- `backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php`

Steps:

1. Add markup assertions for a circulation borrower-photo target and dashboard borrower avatar support.
2. Add circulation rendering coverage for a resolved photo URL and initials fallback.
3. Add a repository assertion that pending borrowing rows include the borrower's stored photo.
4. Run the focused frontend and PHP tests; confirm the new assertions fail before implementation.
5. Commit as `test: cover staff borrower photo surfaces`.

## Task 6: Implement borrower-photo display

Files:

- `backend/src/Infrastructure/Persistence/PdoStaffRepository.php`
- `frontend/features/staff/pages/circulation/circulation.html`
- `frontend/features/staff/pages/circulation/circulation.page.js`
- `frontend/features/staff/pages/dashboard/staff-dashboard.page.js`
- `frontend/features/staff/pages/dashboard/dashboard.html` (only if scoped styling is required)

Steps:

1. Select `u.photo` in normalized and legacy pending-borrowing queries.
2. Render the photo in circulation with an accessible fallback.
3. Render a borrower avatar in dashboard approval cards without removing book-cover artwork.
4. Reuse the shared media URL resolver and add scoped styles only as needed.
5. Run focused tests, then commit as `feat: show borrower photos in staff workflows`.

## Task 7: Harden and verify circulation controls

Files:

- `frontend/features/staff/pages/circulation/circulation.page.js`
- `frontend/tests/staff-circulation.test.js`

Steps:

1. Exercise the real checkout form and approval-button events with the page's DOM fixture.
2. If the test exposes a wiring or duplicate-binding issue, fix the smallest root cause and keep the existing confirmation and API contract intact.
3. Run the complete frontend suite and the relevant backend circulation tests.
4. Commit any required code/test change as `fix: harden circulation checkout actions`; if no code change is required, retain the test coverage in the nearest circulation commit and do not create an empty commit.

## Final verification

Run:

```powershell
npm.cmd test
Set-Location backend
vendor/bin/phpunit tests/Unit/Mail/BorrowerNotificationServiceTest.php tests/Unit/Infrastructure/PdoStaffRepositoryTest.php tests/Unit/Infrastructure/PdoStaffCirculationRepositoryTest.php tests/Feature/StaffCirculationControllerTest.php
vendor/bin/phpstan analyse --configuration=phpstan.neon
composer run quality
```

Report exact results. Do not claim the backend gate passes if the known baseline failures remain.
