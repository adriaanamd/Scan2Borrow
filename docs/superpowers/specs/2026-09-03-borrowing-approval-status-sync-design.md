# Borrowing Approval Status Synchronization

Status: Approved for implementation planning

## Problem

Approval-enabled normalized borrowing requests update the transaction header and
physical copy to `Borrowed`, but leave their `borrowing_items.status` value as
`Pending`. Student and teacher portal queries intentionally prioritize an active
item-level `Pending` value, so an approved request continues to display as
Pending. Existing approved rows can contain the same inconsistency.

## Approved behavior

When staff approves a normalized borrowing transaction:

- `borrowing_transactions.approval_status` becomes `approved`.
- `borrowing_transactions.status` becomes `Borrowed`.
- Every active `borrowing_items` row becomes `Borrowed`.
- Every associated physical copy becomes `Borrowed`.
- The request disappears from the Admin pending-approval workload after the
  existing dashboard refresh.
- Student and teacher dashboards show the loan as `Borrowed`.

When staff rejects a normalized transaction, every active item is closed as
`Returned` with a return timestamp, and every associated copy is released as
`Available`. This keeps rejected requests out of active borrower loans while
preserving the existing normalized status vocabulary.

Legacy one-row borrowing approval behavior remains unchanged.

## Design

### Backend transition

Extend the existing normalized branch of
`PdoStaffRepository::changeBorrowing()` so the transaction header, item rows,
physical copies, notifications, and audit events remain within the same PDO
transaction. After the guarded header update succeeds, synchronize active item
rows according to the decision. The update must remain idempotent when a
request has already been decided.

No borrower-facing JavaScript change is needed: the student and teacher
dashboard renderers already map the backend `Borrowed` value to the correct
badge, and the Admin approval action already reloads the staff dashboard.

### Existing-data repair

Add a forward SQL migration that repairs normalized rows left behind by the
defect:

- approved transactions with active `Pending` items become active `Borrowed`
  items;
- rejected transactions with active items become closed `Returned` items;
- no physical-copy allocation is changed by the repair because approved copies
  are already `Borrowed` and rejected copies are already released by the
  approval workflow.

Document the migration in the schema/migration list and include a schema
contract marker so it is not omitted from installation checks.

## Data flow

```text
Staff approval request
        |
        v
PdoStaffRepository transaction
  | header decision
  | item status synchronization
  | copy status synchronization
  | notification + audit updates
        |
        v
Student/teacher portal query -> Borrowed
Admin pending query          -> no longer pending
```

## Testing

Add a failing normalized repository regression test before implementation. It
will create a pending transaction with pending items and reserved copies, call
approval, and assert all of the following:

- transaction approval/status values are `approved`/`Borrowed`;
- every item status is `Borrowed`;
- every copy status is `Borrowed`;
- `pendingBorrowings()` returns no row;
- `PdoBorrowerPortalRepository::dashboard()` reports `Borrowed` for the
  borrower’s current loan.

Add rejection coverage asserting active items are closed and released copies
are `Available`. Run the existing frontend contract tests to verify the shared
student/teacher renderers need no API or markup changes, and run the full PHP
and frontend suites after implementation.

## Scope boundaries

This change does not redesign approval UX, alter borrowing limits, change the
legacy fallback schema, or add a new approval service. It only synchronizes the
existing normalized approval state and repairs rows created by the defect.
