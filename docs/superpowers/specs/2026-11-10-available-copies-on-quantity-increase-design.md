# Available Copies on Quantity Increase

## Goal

Allow staff to increase a catalog title's quantity while one or more existing physical copies are borrowed, without creating new copies that inherit the borrowed status.

## Problem

The normalized title update flow currently uses the submitted title-level `status` when inserting additional `book_copies` rows. A title whose aggregate status is `Borrowed` can therefore create every newly added copy as `Borrowed`, even though those copies have not been loaned. This corrupts inventory availability and can make new inventory appear to be on active loans.

## Chosen behavior

When a title quantity increases:

- The operation remains allowed even when existing copies are borrowed.
- Every newly created physical copy is `Available`.
- Newly created copies have no `due_date` or `return_date`.
- Existing copies keep their current status and loan dates unchanged.
- The title-level submitted `status` does not control the status of new copies during an update.

Example: if a title has two copies and one is borrowed, increasing the quantity from two to three leaves the borrowed copy borrowed and creates one additional available copy that can be borrowed later.

## Scope

### Backend

Update the normalized title synchronization path in `backend/src/Infrastructure/Persistence/PdoBookRepository.php`. The insert branch used only when the requested quantity exceeds the current active copy count will use fixed values for a new inventory copy:

- `status`: `Available`
- `due_date`: `NULL`
- `return_date`: `NULL`

Metadata updates, keyword synchronization, transaction handling, quantity decreases, and active-copy protections remain unchanged.

### Frontend and API

No frontend or payload changes are required. Existing title edit requests remain compatible. The backend enforces the invariant so direct API calls cannot recreate the bug by submitting `status=Borrowed` during a quantity increase.

### Out of scope

- Changing how a new title is initially created.
- Changing borrowing, approval, renewal, or return workflows.
- Adding a schema migration.
- Redesigning the title edit form or removing its legacy status field.

## Data flow

1. Staff submits a title update with a requested quantity.
2. The repository loads the title's active physical copies inside the existing transaction.
3. Existing copies are left unchanged unless quantity reduction requires archiving removable available copies.
4. If the requested quantity is larger, the repository inserts only the difference as available, unloaned copies.
5. The title metadata and quantity are committed atomically.

## Error handling and consistency

The existing transaction and rollback behavior remains in place. The title quantity continues to represent the number of non-archived physical copies after a successful update. No new error response is needed because increasing quantity while copies are borrowed is valid.

## Testing strategy

Add a regression test to `backend/tests/Unit/Infrastructure/PdoBookRepositoryTest.php` that:

1. Creates a normalized title with one borrowed copy and one available copy.
2. Gives the borrowed copy an existing due date and leaves its return date unset.
3. Increases the title quantity by one while submitting a borrowed title status.
4. Verifies the title quantity and active copy count increase by one.
5. Verifies the original borrowed copy retains its status and due date.
6. Verifies the new copy is `Available` with null due and return dates.

Verification will run the targeted PHPUnit test first and then the full backend quality gate used by GitHub Actions: `composer run quality` from `backend/`.

