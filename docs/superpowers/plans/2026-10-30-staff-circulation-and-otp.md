# Staff ID-Scan Circulation and One-Minute OTP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan.

**Goal:** Add a librarian/admin circulation workspace that identifies borrowers by student ID barcode/QR scan, supports direct in-person borrowing and hybrid returns, preserves remote pending approval, and makes OTP codes expire after exactly one minute.

**Architecture:** Keep the existing framework-free PHP modular monolith and vanilla frontend. Add a protected staff circulation API and page backed by a dedicated service/repository boundary. Direct in-person borrowing is approved only after staff confirmation; remote student/teacher requests continue through the existing pending approval flow. Returns remain staff-managed and can be selected by scan or manual fallback.

**Tech Stack:** PHP 8.2+, PDO/MySQL-compatible schema, PHPUnit, PHPStan, vanilla ES modules, HTML/CSS, Node’s built-in test runner, GitHub Actions.

## Global constraints

- Do not remove or alter remote pending approval. Requests initiated outside the library continue to be pending; staff can use the existing approval flow.
- Staff circulation is restricted to `admin` and `librarian` roles and must use existing session, CSRF, prepared-query, audit, transaction, and locking conventions.
- Borrowing requires a borrower ID scan or manual ID fallback, followed by exact book-copy barcode scans or manual barcode fallback, then a review confirmation modal. Approve creates an immediately approved transaction; reject only discards the draft.
- Returning requires the borrower ID, lists that borrower’s active loans, supports scan-to-select by exact copy barcode and manual checkbox selection, and processes selected returns transactionally.
- Keep the existing Swiss-style staff UI, responsive behavior, accessibility patterns, and no fabricated demo records.
- OTP expiry is exactly 60 seconds from issuance. The existing one-minute resend cooldown remains one minute and must not be shortened or lengthened.
- No schema migration is expected; verify the existing normalized and legacy schema paths before implementation. Preserve compatibility with current tests and fixtures.
- Run focused tests while implementing, then the complete frontend and backend CI-equivalent checks before completion.

## Existing integration points

- OTP: `backend/src/Application/Registration/OtpService.php`, OTP unit tests, and `frontend/features/auth/pages/otp/otp.page.js` / `.html`.
- Staff HTTP layer: `backend/src/Http/Controllers/StaffController.php`, `backend/src/Http/Routing/StaffRouteTable.php`, `backend/src/Application/ApplicationFactory.php`.
- Existing borrowing/return domain: `backend/src/Application/Borrowing/*`, `backend/src/Infrastructure/Persistence/PdoBorrowingRepository.php`, `backend/src/Application/Returns/*`, and `PdoReturnApprovalRepository`.
- Staff shell and scanner: `frontend/features/staff/pages/copy-history/*`, `frontend/features/staff/services/*`, `frontend/assets/js/core/app-navbar.js`, and `frontend/app/shared/components/barcode-scanner/barcode-scanner.component.js`.
- Page routing and parity: `backend/src/Http/Routing/PageRouteTable.php`, `frontend/parity/page-matrix.md`, and their route tests.

## Task 1: Define one-minute OTP expiry with failing tests

**Files:**

- Modify `backend/tests/Unit/Registration/OtpServiceTest.php`.
- Modify `frontend/tests/auth-pages.test.js`.

1. Change OTP fixtures and assertions from five minutes to one minute.
2. Add a boundary assertion that a code is valid before the 60-second expiration instant and expired at/after it.
3. Change the OTP page expectations to `1:00` initially and `0:59` after one tick.
4. Run the focused backend and frontend OTP tests and confirm they fail against the current five-minute implementation. Do not weaken assertions.

Commit: `test: define one-minute otp expiry`

## Task 2: Implement one-minute OTP expiry

**Files:**

- Modify `backend/src/Application/Registration/OtpService.php`.
- Modify `frontend/features/auth/pages/otp/otp.page.js`.
- Modify `frontend/features/auth/pages/otp/otp.html`.

1. Add a single public expiry constant of 60 seconds in the service.
2. Use the constant for both initial issuance and resend expiration timestamps.
3. Update user-facing SMS/HTML text to say the code expires in 1 minute.
4. Change the OTP page countdown to 60 seconds while retaining the existing resend cooldown behavior.
5. Run the focused OTP tests and confirm they pass.

Commit: `fix: expire otp codes after one minute`

## Task 3: Define staff circulation contracts and service behavior

**Files:**

- Create `backend/src/Application/StaffCirculation/StaffCirculationBorrowRequest.php`.
- Create `backend/src/Application/StaffCirculation/StaffCirculationReturnRequest.php`.
- Create `backend/src/Application/StaffCirculation/StaffCirculationRepositoryInterface.php`.
- Create `backend/src/Application/StaffCirculation/StaffCirculationService.php`.
- Create `backend/tests/Unit/StaffCirculation/StaffCirculationServiceTest.php`.

1. Define typed request DTOs for borrower barcode plus book-copy barcodes and requested due date, and borrower ID plus loan IDs/copy barcodes for returns.
2. Define repository results sufficient for borrower details, active loans, selected copy details, created transaction details, and return results without coupling the controller to PDO.
3. Make lookup trim and validate the scanned ID and return a clear not-found/disabled result.
4. Make direct borrowing validate borrower status, borrower type policy, duplicate copy scans, copy availability, maximum active loans, and due date bounds. Use the existing policy defaults (student 7 days, teacher up to 30 days) and the existing clock.
5. Make direct returns require at least one selected item, validate that every selected item belongs to the scanned borrower and is active, and return the existing fine calculation/result shape.
6. Keep reservation availability callbacks and audit behavior aligned with existing borrow/return services.
7. Write tests first for successful lookup, disabled/not-found borrower, duplicate/unavailable copies, capacity and due-date rejection, empty returns, borrower mismatch, and successful direct return.

Commit: `feat: define staff circulation service contracts`

## Task 4: Persist direct staff transactions through PDO

**Files:**

- Create `backend/src/Infrastructure/Persistence/PdoStaffCirculationRepository.php`.
- Create `backend/tests/Integration/Infrastructure/Persistence/PdoStaffCirculationRepositoryTest.php` (or the nearest existing integration-test location).

1. Implement normalized-schema lookup by `users.barcode`, with safe role/status fields and active `borrowing_transactions`/`borrowing_items` joins.
2. Implement normalized direct borrowing in one transaction: lock the exact requested `book_copies`, verify availability, create an approved transaction with `processed_by`/`approved_by`, insert borrowed items, update copy status/due date, and append audit events. Roll back on any failure.
3. Implement normalized returns with borrower/item/barcode validation, fine calculation, copy/item updates, parent transaction status update when no active items remain, reservation availability notification, and audit events.
4. Preserve legacy fallback behavior for installations using `users`, `books`, and `borrowing`; keep unique legacy transaction codes by adding a deterministic per-copy suffix when one scan contains multiple copies.
5. Use the existing MySQL locking/transaction style and never interpolate scanned values into SQL.
6. Test normalized success and rollback paths plus the legacy path using existing fixtures/helpers.

Commit: `feat: persist direct staff circulation transactions`

## Task 5: Expose protected backend endpoints and the page route

**Files:**

- Modify `backend/src/Http/Controllers/StaffController.php`.
- Modify `backend/src/Http/Routing/StaffRouteTable.php`.
- Modify `backend/src/Application/ApplicationFactory.php`.
- Modify `backend/src/Http/Documentation/ApiEndpointCatalog.php`.
- Modify `backend/src/Http/Routing/PageRouteTable.php`.
- Modify `frontend/parity/page-matrix.md`.
- Add/update route, controller, API documentation, and clean-route-matrix tests.

1. Add an optional `?StaffCirculationService $circulation = null` constructor dependency at the end of `StaffController` to preserve existing test/factory construction compatibility.
2. Add role-protected handlers with existing JSON/error/CSRF conventions:
   - `GET /api/staff/circulation/borrower?barcode=...`
   - `POST /api/staff/circulation/borrow`
   - `POST /api/staff/circulation/return`
3. Wire the PDO repository and service in `ApplicationFactory` using the existing policy, clock, audit repository, and reservation availability callback.
4. Add `/staff/circulation` for `admin` and `librarian` in page routing and parity documentation.
5. Add controller and route tests covering authorization, validation, lookup, direct borrow, direct return, CSRF, and response payloads.

Commit: `feat: expose protected staff circulation routes`

## Task 6: Extend the barcode scanner without breaking current pages

**Files:**

- Modify `frontend/app/shared/components/barcode-scanner/barcode-scanner.component.js`.
- Modify `frontend/tests/scanner.test.js`.

1. Add an optional `onDecoded` callback invoked after a successful decode, after the scanner has stopped and the target input is populated.
2. Generate a scanner view element ID from the root ID so multiple scanner instances can coexist on the circulation page; retain the legacy ID behavior when no root ID is available.
3. Preserve current automatic form-submit behavior when no callback is supplied, existing camera permission/error toasts, and cleanup semantics.
4. Add tests for two distinct scanner roots, callback invocation, populated input, and legacy page behavior.

Commit: `feat: support callbacks for circulation barcode scans`

## Task 7: Build the frontend circulation state, API service, and tests

**Files:**

- Create `frontend/features/staff/services/staff-circulation.service.js`.
- Create `frontend/features/staff/pages/circulation/circulation.page.js`.
- Create `frontend/features/staff/pages/circulation/entry.js`.
- Add `frontend/tests/staff-circulation.test.js`.

1. Add service methods `lookupStudent`, `borrow`, and `returnBooks`; use `FormData` for repeated `book_barcodes[]`, `loan_ids[]`, and `copy_barcodes[]` values and rely on the existing API client for CSRF.
2. Implement page state for mode, scanned borrower, borrow-copy draft, active return loans, selected return IDs, busy/error/success status, and modal state.
3. Route both scanner callbacks and manual submissions through the same validation methods.
4. Borrow flow: scan/enter borrower ID, display account and borrowing status, scan/enter exact book copy barcodes, review in modal, approve through API, and reset draft on success. Reject closes/clears the draft without an API call.
5. Return flow: scan/enter borrower ID, list active loans, scan/enter copy barcodes to select exact loans, allow manual checkbox selection, submit selected returns, and display returned items/fines.
6. Use `window.Scan2BorrowConfirmation.confirm` when available and keep a safe browser confirmation fallback for testability.
7. Add tests for request bodies, scanner/manual parity, mode switching, selection, approve/reject, success reset, error rendering, and no request on reject.

Commit: `feat: add staff circulation frontend workflow`

## Task 8: Add the staff circulation UI and navigation

**Files:**

- Create `frontend/features/staff/pages/circulation/circulation.html`.
- Create `frontend/features/staff/pages/circulation/circulation.css`.
- Modify `frontend/assets/js/core/app-navbar.js`.
- Modify staff page and navigation tests.

1. Reuse the `copy-history` staff shell, page metadata, CSRF marker, module entry pattern, icon system, and existing typography/layout conventions.
2. Add clear borrow/return mode controls, borrower ID scanner/manual input, borrower detail panel, book-copy scanner/manual input, active loan list, review/confirmation modal, approval/rejection actions, and return submission action.
3. Give every scanner root/input a stable unique ID and accessible label; ensure the page works at narrow widths and with keyboard/manual fallbacks.
4. Add the Circulation navigation link for staff and ensure the page route is represented in parity tests.
5. Keep strings tied to real workflow concepts; do not add fabricated student/book records.

Commit: `feat: add staff circulation page`

## Task 9: Run the complete quality gates

1. Run `npm test` from the repository root.
2. Run `composer run quality` from `backend/` (this includes PHPUnit and PHPStan with the project’s blocking configuration).
3. Run `git diff --check` and inspect the final diff/stat.
4. Confirm the only pre-existing unrelated worktree item remains `uploads/202310449-606bb73417da81df.jpg` and is not staged.
5. If a check fails, diagnose the first failing command and fix the root cause before claiming completion. Do not skip tests, weaken assertions, or make CI lanes advisory.

## Review checklist

- Remote borrower requests still remain pending until existing staff approval.
- Direct in-person borrow is only possible through authorized staff routes and only after explicit confirmation.
- Scanning a borrower ID retrieves the correct account and active loans without manual student search.
- Borrow barcode scans select exact available copies; manual entry is a fallback, not a separate business path.
- Return selection cannot cross borrower boundaries or return inactive/already-returned copies.
- Direct borrow/return operations are transactional, audited, CSRF-protected, prepared, and race-safe.
- OTP code expiration is exactly one minute in backend validation, messages, and the OTP countdown.
- Frontend and backend tests pass, with fresh command output recorded in the final handoff.
