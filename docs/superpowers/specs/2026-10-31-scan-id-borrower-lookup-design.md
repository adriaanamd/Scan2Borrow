# Scan ID Borrower Lookup Design

**Date:** 2026-10-31  
**Status:** Approved design; implementation pending

## Problem

Library staff need a fast, reliable way to scan a student or teacher ID and see that borrower’s currently borrowed books. The repository already contains a protected circulation desk that loads a borrower and active loans before borrow/return actions. The feature should finish that workflow and add a separate read-only quick lookup so staff can check a borrower without entering a transaction flow.

## Goals

1. Preserve and verify the existing `/staff/circulation` workflow: scan or enter an ID barcode, load the borrower, show active loans, and keep borrow/return actions available.
2. Add a `Scan ID` action to `/staff/students` that opens a modal for a quick borrower lookup.
3. Let the modal accept both hardware-scanner/manual input and camera barcode/QR scanning.
4. Show authoritative data from the existing staff-only borrower lookup endpoint:
   - borrower name;
   - ID barcode;
   - role and account status;
   - active-loan count;
   - each active loan’s title, copy barcode, and due date.
5. Keep the modal read-only. It may link to the borrower detail page and the circulation desk, but it must not borrow, return, or alter borrower data.
6. Preserve the current dirty working-tree changes in `frontend/features/staff/services/index.js`, `frontend/tests/scanner.test.js`, and `uploads/202310449-606bb73417da81df.jpg`.

## Non-goals

- No new database table, column, migration, or borrowing model.
- No new backend endpoint; reuse `GET /api/staff/circulation/borrower?barcode=...`.
- No borrower-facing or public lookup surface.
- No historical-loan report in the quick modal.
- No automatic borrow or return action after a scan.
- No unrelated restyling of staff pages.

## User experience

### Existing circulation desk

The `/staff/circulation` page remains the transaction surface. Staff can scan an ID or submit a manually entered barcode, then see the borrower identity and active-loan list in the page. The existing borrow and return panels remain unchanged in intent and continue to use the exact scanned borrower/copy values for mutations.

The completion work includes keeping the staff-service barrel export for `StaffCirculationService` and adding or tightening contract coverage where needed. Existing user changes remain untouched unless a test requires a narrowly scoped adjacent edit.

### Quick lookup modal

The Borrowers directory gains one `Scan ID` button near the existing search controls. Activating it opens a Bootstrap modal with these states:

1. **Ready:** a focused barcode input, a camera scan control, and a `Load borrower` action.
2. **Loading:** the lookup controls are disabled and the modal exposes a live status message.
3. **Found with loans:** borrower identity is shown above a compact loan ledger. Each row shows title, physical-copy barcode, and due date. The modal includes `View borrower`, `Open circulation`, and `Close` actions.
4. **Found without loans:** borrower identity is shown with an explicit empty-loan message; no fabricated rows or sample values are rendered.
5. **Not found or invalid:** the modal keeps the input available, shows the server’s actionable error message, and does not retain stale borrower data.

The modal supports Escape and the Bootstrap close control, returns focus to the `Scan ID` trigger after close, and uses a scrollable body on narrow or short viewports. Camera scanning starts only while the modal is open and is stopped when it closes so the browser camera is not held by a hidden surface. Manual input remains the fallback for USB barcode scanners and devices without camera support.

### Visual direction

The new lookup surface uses the Swiss anchor already established by the circulation desk: neutral white/near-white surfaces, Helvetica Neue/Arial sans typography, Yves Klein blue `#002FA7`, left-aligned content, and 1px hairline rules. It does not add warm paper, grain, decorative shadows, or invented status copy.

The differentiator is a scan-first modal that transitions in place from borrower identification to a focused active-loan ledger. The scan control stays visually primary, while the result state pins the borrower identity above the exact loan records staff need to act on.

## Architecture and data flow

### Backend

- Keep `StaffRouteTable`’s existing `GET /api/staff/circulation/borrower` route.
- Keep `StaffController::circulationBorrower()` as the authorization boundary. It must continue to require an authenticated staff role and return `401` for unauthorized callers, `422` for an empty barcode, and `404` for an unknown borrower.
- Keep `StaffCirculationService::lookup()` as the domain lookup. It must continue to normalize the barcode, return the borrower record and active loans, and avoid mutation.
- Keep `PdoStaffCirculationRepository::findBorrowerByBarcode()` as the source for current loans across the repository’s supported normalized/legacy storage compatibility.
- Keep the API documentation entry aligned with the actual endpoint contract.

### Frontend

- Keep `StaffCirculationService.lookupStudent(barcode)` as the circulation page’s client method.
- Add `BorrowerService.lookupByBarcode(barcode)`, a read-only client method that calls the same endpoint. The final implementation must not create a second HTTP route or duplicate response normalization.
- Add a focused `BorrowerLookupModal` component under the staff feature components. It owns modal state, scanner lifecycle, loading/error/empty/result rendering, escaping of server data, and focus restoration. It receives an API-backed lookup function and navigation URLs rather than reaching into global application state.
- Extend `BorrowersPage` only to render the trigger, construct the modal component, and connect the existing borrower-directory service. The directory search and row rendering remain separate responsibilities.
- Add the modal markup to the Borrowers feature template and create a scoped `borrowers.css` stylesheet for the new lookup surface. Reuse the page’s existing Bootstrap bundle for modal behavior and the shared `BarcodeScannerComponent` for camera input.

### Request flow

```text
Staff clicks Scan ID
        |
        v
BorrowerLookupModal opens and focuses barcode input
        |
        +-- manual submit / camera decode
        |
        v
GET /api/staff/circulation/borrower?barcode=<trimmed value>
        |
        +-- 200: render borrower + active_loans
        +-- 422/404: render actionable error and keep input available
        +-- 401/other: render safe failure message without stale results
```

## Security and data handling

- The modal is available only on the already protected `/staff/students` page; no client-side hiding is treated as authorization.
- The endpoint remains staff-authorized server-side.
- All borrower and loan fields are escaped before insertion into HTML. Prefer `textContent`/DOM construction where practical.
- A failed lookup clears the previous borrower and active-loan state before showing the error.
- The modal performs a GET only, so it does not need CSRF; any future mutation remains in the existing CSRF-protected circulation endpoints.
- Do not log or persist scanned IDs in browser storage.

## Testing strategy

### Frontend tests

- Verify `BorrowerService` calls the existing endpoint with a trimmed barcode.
- Verify the modal renders borrower identity, active-loan rows, and the explicit no-loans state without fabricating data.
- Verify server error responses clear stale results and expose the error message.
- Verify scanner decode invokes the same lookup path as manual form submission and scanner teardown occurs on close.
- Verify the Borrowers template exposes the `Scan ID` trigger, accessible modal labels, scanner root, result container, and navigation actions.
- Preserve the existing circulation service/page/scanner tests and add coverage only for the new behavior.

### Backend tests

- Run the existing circulation service/controller/repository tests.
- If a contract is missing, add a focused assertion that a successful lookup includes active loans and that unauthorized/empty/unknown lookups keep their existing status codes.
- Do not weaken or skip existing tests.

### CI-aligned verification

Run targeted tests first, then the complete local gates matching repository CI:

```text
npm test
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml
C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon
```

The backend test and quality lanes must remain bounded and must not be made advisory. Remote GitHub Actions status will be reported separately if it is not available from the current session.

## Acceptance criteria

- A librarian or administrator can open `/staff/students`, click `Scan ID`, scan or type a borrower ID, and see the correct borrower and current borrowed books in a modal.
- A borrower with zero active loans gets a clear empty state with no invented loan rows.
- An unknown or blank barcode leaves no stale borrower data visible and shows a useful error.
- Closing the modal releases camera resources and restores focus to the trigger.
- The existing `/staff/circulation` scan-first lookup and transaction flow remains functional.
- The backend endpoint remains staff-protected and uses the existing normalized/legacy repository compatibility.
- All new behavior is covered by deterministic tests, and the complete local CI-aligned checks pass with fresh output.
