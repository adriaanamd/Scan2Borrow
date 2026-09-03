# Borrower Catalog Waitlist Design

**Date:** 2026-09-03  
**Status:** Approved for planning  
**Scope:** Student Search Books and Teacher Borrow Books catalogs

## Problem

Unavailable book cards currently render a disabled `Unavailable` button. The existing reservation system already supports joining a hold queue, but the student and teacher catalog surfaces do not expose that action. Borrowers need a clear, confirmed way to join the queue from the same card where they discover an unavailable title.

## Goals

- Show `Join waitlist` for an unavailable title that the current borrower has not reserved.
- Confirm the action with the existing application confirmation modal.
- Submit the existing role-specific hold request after confirmation.
- Show a success toast and change the card action to `On waitlist` after a successful join.
- Preserve the `Your holds` dashboard queue and its claim/cancel behavior.
- Prevent duplicate joins after a page refresh by loading the borrower’s active holds when the catalog starts.
- Keep student cards on the Organic surface and teacher cards on the Swiss surface.

## Non-goals

- No new reservation statuses, database tables, or backend endpoints.
- No changes to staff reservation fulfilment.
- No changes to dashboard reservation markup or queue actions.
- No waitlist action for a title already borrowed by the current borrower; that card keeps `You have this`.

## Experience

The unavailable-card action states are:

| Card state | Action | Behavior |
| --- | --- | --- |
| Available | `Add to Borrow Cart` | Keep the current borrow modal flow. |
| Unavailable, not reserved | `Join waitlist` | Open confirmation modal; submit only after confirmation. |
| Unavailable, already reserved | `On waitlist` | Disabled status; no second join request. |
| Already borrowed | `You have this` | Keep the current informational state. |

The confirmation modal uses the existing global `Scan2BorrowConfirmation` service with:

- Title: `Join waitlist`
- Message: `Join the waitlist for "<book title>"?`
- Confirm label: `Join waitlist`
- Confirm style: the role’s primary action style

Canceling closes the modal without a request. While the request is pending, the triggering card button is disabled and reads `Joining…`. On success, the action becomes `On waitlist` and a success toast uses the API response message. On failure, the button is restored and the API error is shown in a danger toast.

## Architecture and data flow

The shared `BorrowerSearchPage` controller owns the catalog interaction because both recommendation cards and paged All books cards use the same `bookCard(book)` renderer.

1. Student and teacher page constructors provide their role to the shared controller.
2. The shared controller creates the existing `ReservationService` with an `ApiClient` configured with the page CSRF token and the current window fetch function.
3. On startup, the controller loads active holds from `/scan2borrow/api/{role}/holds` and stores the returned `title_id` values in an in-memory set. A failed hold-list request does not block catalog loading.
4. `bookAction(book)` returns the existing borrow action, the existing borrowed state, or the new waitlist action based on availability and the active-hold set.
5. Delegated click handlers on both `book-recommendations` and `book-results` detect `data-waitlist-title-id` buttons. Delegation is required because both collections are rendered asynchronously and the catalog is replaced on every page change.
6. The handler calls the confirmation service. Its `onConfirm` callback calls `ReservationService.join(titleId)`.
7. A successful response adds the title ID to the active-hold set, updates that button to `On waitlist`, and shows the success toast. The current card collection is not refetched, so paging and recommendation context remain stable.

The role-specific endpoints already exist:

- Student: `GET/POST /scan2borrow/api/student/holds`
- Teacher: `GET/POST /scan2borrow/api/teacher/holds`

The existing `ReservationService` supplies `list()` and `join(titleId)`, including CSRF handling through `ApiClient`.

## Markup and styling

- Replace the disabled unavailable-card button with a standard button carrying the title ID and title as data attributes.
- Add the existing `toast-host` Bootstrap container to both catalog templates so the shared `ToastService` has a bounded presentation mount.
- Keep confirmation markup global and dynamically created by the existing confirmation service.
- Add role-scoped waitlist button styling only where the existing role surface needs a visual distinction; do not introduce a new visual anchor or modify navigation styles.
- Escape all book-derived values before inserting them into HTML. Confirmation and toast body text use text-node APIs or service payload text handling.

## Error and accessibility behavior

- Confirmation is not bypassed by keyboard activation; the same button opens the modal and remains the focus trigger.
- The join button is disabled while submitting to prevent duplicate clicks.
- Success and error feedback is exposed through the existing toast host with alert semantics.
- Duplicate or no-longer-eligible joins use the existing API error message and restore the action for retry where appropriate.
- If active-hold loading fails, the catalog still loads and a duplicate attempt is handled safely by the backend response.

## Testing

Add focused frontend contracts for:

- Student and teacher catalog templates exposing a toast host and waitlist action contract.
- Student and teacher constructors providing role-specific hold endpoints/roles.
- Shared unavailable-card rendering exposing `Join waitlist` and avoiding a disabled `Unavailable` action.
- Active-hold state rendering `On waitlist` and preventing a duplicate action.
- Confirmation flow calling the reservation service only after acceptance and preserving the button on cancel/error.

Run the focused catalog/reservation tests and the complete frontend suite, then run JavaScript syntax and whitespace checks. No backend source changes are expected for this feature.
