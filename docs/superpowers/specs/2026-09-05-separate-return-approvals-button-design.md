# Separate Return Approvals Button

## Goal

Make librarian return verification discoverable from the staff dashboard by giving it its own top-level button and modal, separate from borrower-request approvals.

## User experience

- The staff dashboard page header shows two sibling actions: `Pending Approvals` for borrow requests and `Return Approvals` for return requests.
- `Return Approvals` has its own badge containing only the number of pending return requests.
- Clicking `Return Approvals` opens a dedicated modal titled `Return Approvals`.
- The modal lists pending student, teacher, and guest return requests with the existing evidence, borrower, book, barcode, due date, and requested-at details.
- Each request keeps the existing `Approve return` and `Reject` actions. Rejecting still requires a reason; approving still confirms that staff physically received the book.
- The generic borrow-approval modal no longer contains the return-verification section, so its content and count represent borrow approvals only.
- Empty and error states are explicit: no requests displays an empty message, while a failed load displays a useful error toast/state.

## Implementation boundary

- Reuse the existing `GET /api/staff/return-approvals` and `POST /api/staff/return-action` endpoints.
- Keep polling, CSRF handling, confirmation dialogs, authorization, and return state transitions unchanged.
- Change only the staff dashboard markup, dashboard client rendering/binding, and frontend contract tests needed to enforce the new discoverability.
- Do not change the SQL schema or unrelated dashboard features.

## Verification

- Add a frontend regression assertion for a separate `Return Approvals` trigger and dedicated modal, and for the absence of the return section from the borrow-approval modal.
- Run the focused frontend tests, the full JavaScript test suite, and the relevant backend return-approval tests.
