# Librarian-Approved Returns Design

## Goal

Require a librarian or administrator to verify every book return before the system releases the physical copy. This prevents a student, teacher, or guest from self-reporting a return and making a still-missing book appear available.

## Scope

The change covers all return paths:

- Student and teacher unified returns by copy barcode or transaction code.
- Guest returns with a return-verification photo.
- Librarian and administrator review and completion.
- Normalized borrowing tables and the legacy borrowing fallback used by older installations.

Borrow approval remains unchanged. This design only changes return completion and the staff review workload.

## Current behavior and risk

Student and teacher requests call `ReturnService` and immediately update the loan, copy status, return date, fine, and reservation availability. The client does not have authority to perform that mutation, but the current server endpoint grants it to the authenticated borrower.

Guest requests already become `Return Verification Pending`, but staff can only review borrow requests. There is no staff action that completes the guest return, so the return state does not have a complete approval workflow.

## Design

### State transition

Borrower-originated requests create or preserve a pending return request and do not alter inventory availability:

```text
Active loan
    |
    | borrower submits barcode/transaction code
    v
Return pending review
    |                         \
    | librarian/admin approves \\ librarian/admin rejects
    v                           v
Returned + copy Available       Active loan remains active
```

For guests, the existing `Return Verification Pending` state is the pending review state. For normalized student/teacher loans, a return-request marker and request metadata will be added to the existing borrowing item/header model. Legacy borrowing rows will use the corresponding legacy-compatible fields or a migration-backed equivalent.

The actual return mutation is performed only by the staff-side service/repository while holding the relevant row(s) in a transaction. The mutation must be conditional on the request still being pending and the loan still being active, making repeated or stale approvals harmless.

### Staff authorization and endpoint

Add a staff-only return decision endpoint under the existing staff route family. It will:

1. Require an authenticated librarian or administrator.
2. Validate CSRF.
3. Validate a positive return/loan identifier and an allowlisted `approve` or `reject` action.
4. Require a rejection reason.
5. Delegate the state transition to a service/repository method that receives the authenticated staff ID.

The borrower return endpoint remains available for submitting requests, but its success response changes to explain that the return is awaiting librarian verification. It must never call the completion mutation.

### Staff review workload

The staff dashboard’s pending workload will include pending borrower returns and guest return-verification requests. Each item will show enough information to identify the physical copy and borrower. Guest items will expose the submitted return photo through the existing escaped/media-resolved review surface.

Approval and rejection controls will use the existing confirmation pattern. After a decision, the list and count refresh so a completed request cannot be approved twice from a stale screen.

### Data and audit behavior

The implementation will preserve existing status values and fallback behavior where possible. New request fields/statuses will be introduced through an idempotent SQL upgrade migration and reflected in the schema contract tests.

On approval, the transaction will atomically:

- set the loan/item return timestamp and `Returned` status;
- set the physical copy/book to `Available` and clear active due-date state;
- calculate and store the overdue fine using the existing return clock/policy;
- advance the next reservation when the existing availability service is configured;
- record the staff actor and decision timestamp;
- record the existing physical-copy `returned` audit event when the normalized audit trail is available;
- mark the guest borrowing row `Returned` when the request belongs to a guest loan.

On rejection, the active loan and inventory state remain unchanged. The rejection reason and staff actor are retained for the review history. No availability advancement occurs.

### Error handling

The API will return the existing JSON error shape. Invalid input, missing CSRF, missing records, already-decided requests, and active-state conflicts will return a safe 4xx response without partially changing the loan or copy. Database failures will roll back the complete decision and use the existing generic persistence error behavior without exposing SQL details.

## Testing

Tests will be written before production changes and run through the smallest relevant lane first, followed by the complete local gates.

Backend coverage will include:

- borrower submission creates pending state and does not complete the return;
- transaction-code submissions create the expected pending work for every active item;
- guest submission remains pending and preserves evidence;
- only librarian/admin sessions can decide returns;
- CSRF and action validation are enforced;
- approval completes normalized, legacy, and guest returns atomically;
- rejection keeps loans active and requires a reason;
- duplicate/stale decisions do not repeat mutations;
- fine calculation, reservation advancement, actor attribution, and audit behavior remain correct.

Frontend coverage will include the borrower-facing pending message, staff return-review markup/actions, and service endpoint contracts. Existing tests and CI quality commands remain required gates.

## Non-goals

- Changing borrow-request approval rules.
- Adding a separate general-purpose workflow engine.
- Allowing borrowers to override or self-approve a return.
- Removing the guest return photo requirement.
