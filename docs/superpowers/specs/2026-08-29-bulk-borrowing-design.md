# Bulk Borrowing Design

## Goal

Allow students and teachers to borrow multiple copies and multiple titles in one atomic borrowing transaction, while representing library titles separately from their physical copies.

## Approved scope

- Students and teachers use the same bulk-borrow capability.
- Borrowers can build a cart from catalog search results and by scanning copy barcodes.
- A cart may contain different titles and multiple copies of the same title.
- The server accepts one submission and returns one transaction code.
- The borrower limit counts physical copies, not distinct titles.
- If any requested title or copy is unavailable, the complete request is rejected with no partial records.
- Existing student loan periods and teacher preferred due-date rules remain unchanged.
- Approval-enabled requests reserve copies immediately; staff approves or rejects the complete transaction.
- Non-borrowing guest request flows remain outside this feature unless they are later explicitly included.

## Data model

The current physical-copy `books` model will be normalized into four concepts:

### `book_titles`

One row per catalog title. It owns title metadata such as ISBN, title, author, publisher, description, category, cover, and the total `quantity` of physical copies. Available, reserved, and borrowed quantities are calculated from copy statuses rather than stored as independently mutable counters.

### `book_copies`

One row per physical copy. It owns the unique barcode, accession number, title relationship, shelf/location fields, status (`Available`, `Reserved`, or `Borrowed`), due/return dates, and archive state. Generated identifiers are supported for newly created quantities, and staff may replace them with scanned physical barcodes.

### `borrowing_transactions`

One row per checkout session. It owns the unique transaction code, borrower, common borrow/due dates, approval state, staff decision metadata, and overall transaction timestamps/status.

### `borrowing_items`

One row per physical copy in a transaction. It owns the selected copy, item-level return date, status, and fine. This allows one transaction to contain multiple titles, repeated copies of one title, and individual or whole-transaction returns.

Existing title-level relations such as keywords and book views will be remapped to `book_titles`; copy-level relations such as return notifications will be remapped to `book_copies` or `borrowing_items` according to their meaning.

## Migration and fresh installs

Create `sql/upgrade_bulk_borrowing.sql` as a forward migration for existing installations. It will:

1. Create the normalized tables and indexes.
2. Group legacy book rows into titles using ISBN when available, otherwise normalized title/author/publisher identity.
3. Create one copy row for every existing physical book, preserving barcode, accession number, location, status, dates, archive state, and identifiers.
4. Convert legacy borrowing rows into transaction headers and item rows while preserving transaction codes, approval states, dates, return data, and fines.
5. Remap dependent title/copy relationships and validate that every legacy record has a destination.
6. Leave the database in a state that can be safely used by the new application without duplicate quantity counts.

Update `sql/database.sql` with the final schema for fresh installs. Update `README.md` with the base-schema and migration order, explicitly listing `upgrade_bulk_borrowing.sql` for existing databases and documenting the catalog/copy quantity model. Schema contract tests will ensure both the migration and fresh-install schema remain present.

## Borrower experience

The student search, student dashboard, and teacher dashboard share the bulk cart behavior:

- Search results show each title once with total and available copy counts.
- Add-to-cart adds one copy; quantity controls adjust the requested count.
- Scanning a copy barcode adds that copy's title and increments its requested count. Repeated scans increase the same cart row.
- The cart shows title, author, requested quantity, available quantity, and total copies.
- The student flow uses its existing default due date; teachers retain the preferred return date input.
- Submit sends title quantities and optional exact copy barcodes in one request.
- A successful response shows one transaction code, total copy count, and a receipt link.
- Existing dashboard, history, receipt, and return surfaces render all items associated with the transaction.

## API and atomic workflow

The borrower endpoints retain their role-specific routes while accepting a bulk payload:

```json
{
  "action": "borrow",
  "items": [
    {"title_id": 12, "quantity": 2},
    {"title_id": 18, "quantity": 1, "barcodes": ["COPY-18-02"]}
  ],
  "due_date": "2026-09-20"
}
```

The service validates non-empty items, positive quantities, duplicate title entries, exact barcode ownership, due dates, and the borrower's remaining copy limit. It then starts a database transaction, locks the affected title/copy rows, allocates exact scanned copies first and available copies for the remaining quantities, and rechecks every rule under the lock.

For approval-enabled requests, the service inserts one pending transaction and its items, marks all selected copies `Reserved`, and creates the staff approval workload. For immediately approved requests, it inserts the same transaction/items and marks copies `Borrowed`. Any exception or availability failure rolls back the entire transaction.

Staff approval and rejection operate on the transaction header. Approval changes all reserved copies and items together; rejection releases every reserved copy. Approval must fail atomically if the reservation is no longer valid. Existing single-item requests remain accepted as a one-item bulk request during the transition.

Returns accept either a copy barcode for one item or a transaction code for all active items in that transaction. A transaction is complete only when all its items are returned.

## Staff inventory

The inventory console will display title-level totals and availability while retaining copy-level management. Staff can create a title with an initial quantity, which creates generated copy identifiers, and can add, edit, assign, archive, restore, or delete individual copies subject to active-loan protections. Destructive copy actions continue to use the reusable confirmation modal.

## Error handling and security

- Invalid quantities, duplicate items, unknown titles, mismatched barcodes, unavailable copies, and limit violations return clear validation errors.
- Availability errors identify the affected title and requested versus available count.
- No endpoint trusts client-side availability or borrower limits.
- All inventory allocation and status changes use parameterized queries and database transactions.
- Borrower authorization and CSRF checks remain on existing routes.
- User-controlled title and author values continue to be escaped by existing renderers.

## Testing and verification

### Backend

- Unit-test bulk request validation and due-date rules.
- Test copy allocation, duplicate-title normalization, borrower-limit counting, and all-or-nothing rollback.
- Test reservation, approval, rejection, individual return, and transaction return behavior.
- Test migration/schema contracts and dependent relation remapping.
- Preserve existing single-book behavior through a one-item compatibility request.

### Frontend

- Test cart add/remove/increment/decrement behavior.
- Test duplicate barcode scans and quantity/availability rendering.
- Test student and teacher submit payloads, due-date handling, success receipt data, and error display.
- Test that dashboard/search/teacher entry points use the shared cart behavior.

### Final checks

- Run the complete JavaScript test suite.
- Run the available PHP test suite and migration/schema contract tests.
- Run `git diff --check` and verify a clean working tree after the feature commits.

