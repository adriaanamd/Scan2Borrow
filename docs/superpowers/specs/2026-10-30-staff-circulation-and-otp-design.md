# Staff ID-Scan Circulation and One-Minute OTP Design

## Goal

Add a librarian/admin-controlled in-person circulation workflow that identifies borrowers by scanning their student ID barcode or QR code, processes exact book copies by barcode, and keeps existing remote approval workflows intact. Also change OTP validity from five minutes to exactly one minute.

## Scope and decisions

This change contains two bounded workstreams:

1. Staff circulation: direct in-person borrowing and returning through a dedicated staff workflow.
2. OTP expiry: one-minute validity across registration and other existing OTP consumers.

Pending approval is retained for student/teacher requests submitted through the existing borrower portal. Those requests continue to be reviewed by staff. The new scan-first workflow is separate and creates an approved transaction only after the librarian confirms it.

The existing 60-second resend cooldown remains unchanged. Only code validity changes to one minute.

## Existing integration points

- Borrower self-service uses `BorrowingService` and `ReturnService`; the configured borrower policy currently requires approval and return verification.
- Staff approval and return-review actions are exposed through `StaffController`, `StaffRouteTable`, `PdoStaffRepository`, and `PdoReturnApprovalRepository`.
- Borrower IDs are stored in `users.barcode`. Staff authorization accepts the `admin` and `librarian` roles.
- Exact physical copies are represented by `book_copies.barcode` in the normalized schema. The legacy compatibility path uses `books.barcode` and `borrowing`.
- The normalized transaction model stores `processed_by`, `approved_by`, `approval_status`, `borrowing_items`, copy status, and audit events.
- Camera barcode/QR scanning is already provided by the shared `BarcodeScannerComponent` and the existing html5-qrcode library.
- OTP validity is currently hardcoded to five minutes in `OtpService`, its messages, the OTP frontend countdown, and its tests.

## Recommended architecture

Create a dedicated staff circulation boundary rather than adding operator flags to borrower services or expanding the already large dashboard controller.

### Backend units

- `StaffCirculationService` owns staff circulation validation and use-case orchestration.
- `StaffCirculationRepositoryInterface` defines borrower lookup, exact-copy availability, direct borrow, active-loan lookup, and direct return operations.
- `PdoStaffCirculationRepository` implements those operations with the existing PDO connection and supports both normalized and legacy schemas using the same compatibility pattern as current repositories.
- `StaffController` receives the new staff circulation service and exposes staff-only JSON endpoints through `StaffRouteTable`.
- The existing `BorrowingService`, `ReturnService`, `ReturnApprovalService`, borrower endpoints, pending approval queries, and guest workflows remain unchanged.

### Frontend units

Add a dedicated `/staff/circulation` page using the existing staff shell, navbar, Bootstrap modal/confirmation conventions, blue/white surfaces, and scanner component. Keep the interface focused on one physical desk workflow rather than adding a second large workflow to the dashboard.

The page has two explicit modes:

- Borrow
- Return

The visual direction follows a Swiss-style information grid because it matches the existing staff surface: white/neutral panels, a single blue accent, left-aligned high-density content, and hairline separators. The differentiator is a persistent borrower identity panel beside a live scanned-copy list, so staff can keep identity and transaction context visible throughout the interaction.

## Borrow flow

1. Staff opens Staff → Circulation and chooses Borrow.
2. Staff scans or manually enters the student ID barcode/QR value.
3. The page requests the student record from the staff circulation lookup endpoint.
4. The response displays the student’s name, ID barcode, role, department/course/year information, account status, borrowing status, active-loan count, and borrowing eligibility.
5. Staff scans each physical book barcode. A manual barcode field remains available as a fallback. Each successful lookup adds the exact available copy to the review list and prevents duplicate copies.
6. The page displays the selected copies, titles, due date, and any validation feedback before mutation.
7. `Approve and borrow` opens the existing confirmation pattern. On confirmation, the page posts the student and exact copy barcodes to the direct borrow endpoint.
8. The backend atomically locks and revalidates the borrower and copies, creates an approved transaction, marks copies borrowed, sets the librarian/admin as processor and approver, records copy audit events, and returns the transaction code and due date.
9. `Reject` clears the draft without creating a pending or rejected borrowing record. The interface explains that no transaction was created.

Direct borrow rules:

- Only active student/teacher accounts with active borrowing status may borrow.
- Existing maximum-loan and role-specific due-date rules are reused.
- The submitted copy barcodes must be unique, available, undeleted, and belong to the requested borrower transaction.
- A stale copy, duplicate, unavailable copy, or policy violation fails the entire transaction and leaves all inventory unchanged.

## Return flow

1. Staff chooses Return and scans or manually enters the student ID barcode/QR value.
2. The lookup response displays the student identity and all active loans, including transaction code, exact copy barcode, title, author, due date, overdue status, and current fine context.
3. Staff scans each physical returned book barcode. A matching active loan is automatically selected. The page visibly marks unknown, already-returned, or wrong-student barcodes as errors without mutating data.
4. Manual checkboxes remain available as a fallback for damaged or unreadable book barcodes. Only active loans belonging to the scanned student can be selected.
5. Staff confirms the selected returns through a confirmation dialog.
6. The backend atomically revalidates the student and selected active loan IDs/copy barcodes, calculates fines using the existing 20.00-per-day policy, records return timestamps and staff actor, marks the selected copies available, updates transaction/item statuses, advances reservation availability where applicable, and records returned-copy audit events.
7. The response returns the processed books, fines, and a concise confirmation. Unselected active loans remain active.

## API shape

The exact PHP signatures will follow existing typed DTO and repository conventions, but the external contracts are:

### Student lookup

`GET /api/staff/circulation/borrower?barcode=<student-id>`

Returns:

```json
{
  "ok": true,
  "data": {
    "borrower": {
      "id": 7,
      "barcode": "2024004",
      "name": "Borrower Name",
      "role": "student",
      "status": "active",
      "borrowing_status": "active"
    },
    "eligibility": {
      "can_borrow": true,
      "active_loans": 1,
      "remaining_capacity": 2,
      "reason": ""
    },
    "active_loans": []
  }
}
```

The frontend will render real response data only; empty fields remain empty rather than being replaced with fabricated values.

### Direct borrow

`POST /api/staff/circulation/borrow`

Payload:

```text
csrf=<token>
borrower_barcode=<student-id>
book_barcodes[]=<copy-barcode>
book_barcodes[]=<copy-barcode>
```

The response includes the transaction code, borrower, processed copy count, due date, and message.

### Direct return

`POST /api/staff/circulation/return`

Payload:

```text
csrf=<token>
borrower_id=<verified-student-id>
loan_ids[]=<active-loan-id>
copy_barcodes[]=<matching-copy-barcode>
```

The backend requires the selected loan/copy pair to belong to the same active borrower record. It rejects mismatched or stale selections before committing.

All endpoints require an authenticated admin or librarian session and valid CSRF tokens.

## OTP expiry

Set the single OTP validity period to 60 seconds in `OtpService` and use that value consistently for:

- Initial OTP records.
- Resent OTP records.
- SMS copy.
- Email HTML copy.
- The registration OTP countdown.
- Unit and frontend contract tests.

The existing one-minute resend cooldown remains in place. Verification continues to compare the code and reject records at or after their expiry timestamp. The change must not weaken one-time-use behavior or delivery error handling.

## Error handling and security

- Unknown student ID returns a staff-safe not-found response without exposing account internals.
- Inactive or borrowing-disabled accounts can be displayed but cannot be borrowed against.
- Missing, duplicate, unavailable, deleted, wrong-student, and stale copy values produce actionable validation messages.
- CSRF failures continue to return the existing 419 response contract.
- Staff role checks occur in the controller and the mutation service boundary; borrower sessions cannot call the endpoints successfully.
- All SQL uses prepared statements. Direct borrow and return operations use transactions and row locks where the MySQL driver supports them.
- Audit events identify the staff actor, borrower, exact copy, transaction/item, old status, new status, and operation context.
- No schema migration is expected because the current install and upgrade schemas already contain the required users, transaction, copy, processor, approval, return, and audit fields. The implementation must preserve the current legacy fallback for installations that have not completed normalization.

## Testing and CI gates

Use TDD for every new behavior and regression:

- Backend unit tests for circulation validation, eligibility, duplicate/unavailable copy handling, direct borrow success/rejection, direct return selection, fine calculation, authorization, and stale transaction safety.
- Backend repository/feature tests for normalized and legacy SQL paths, atomic rollback, route registration, CSRF, and staff role protection.
- Frontend tests for circulation service payloads, scanner callback behavior, borrower identity rendering, exact-copy borrow review, automatic return selection, manual fallback selection, rejection without mutation, and error states.
- OTP tests updated to prove one-minute expiry, exact boundary rejection, one-time use, resend cooldown, and consistent one-minute copy/countdown.

Required verification gates remain:

```text
npm test
cd backend
composer run quality
```

The GitHub Actions backend quality workflow remains blocking. No tests, assertions, lint/type analysis, or workflow lanes may be weakened or skipped.

## Compatibility and rollout

The change is additive for staff circulation. Existing borrower pages, pending approval cards, return approval cards, guest requests, reservations, renewals, notifications, and reports continue using their current endpoints and status semantics. The new page can be enabled through the staff navbar and page route after backend endpoints are available. OTP behavior changes for all existing OTP consumers at once because they share `OtpService`; the user-facing copy will explicitly say the code expires in one minute.
