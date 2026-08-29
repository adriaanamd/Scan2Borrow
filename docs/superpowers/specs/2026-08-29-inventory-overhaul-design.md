# Inventory Catalog and Copy Workflow Design

## Goal

Make inventory quantity management reliable by treating a book title and its physical copies as separate concepts everywhere in the application. Staff must be able to create or increase a title's quantity without entering a duplicate barcode, while every physical copy remains uniquely identifiable for borrowing and returns.

## Problem

The current grouped inventory response represents one catalog title, but the edit form and validator still assume that every inventory row is one physical book with its own barcode. This causes grouped titles to submit an empty or reused barcode and receive a 422 duplicate/required-barcode error. It also makes quantity updates appear disconnected from the dashboard and borrower surfaces.

## Chosen approach

Use the existing normalized catalog/copy model:

- `book_titles` stores one row per catalog title, including metadata and the requested total quantity.
- `book_copies` stores one row per physical copy, including its unique barcode, accession number, location, status, and loan-related dates.
- A title-level inventory row never requires a barcode. Its identifier is `book_titles.id` (`title_id` in API payloads).
- Copy identifiers are generated automatically when quantity increases and can be edited through a dedicated copy operation.

The legacy one-row-per-book model remains a read/migration concern only. The application must not silently accept a quantity update while writing to a schema that cannot represent quantity; installations without the normalized schema must receive an actionable migration error.

## User experience

### Create a title

The inventory drawer accepts title metadata and an initial quantity. Barcode and accession fields are optional for bulk creation. On save, the server creates the title and exactly that many active copies in one transaction. The first copy uses supplied identifiers when present; missing or subsequent identifiers are generated uniquely.

The response includes the title ID, total quantity, and generated copy list so the UI can show which identifiers were created.

### Edit a title

The title edit form edits title metadata and desired total quantity. It does not display a title-level barcode field and does not send `barcode` or `accession_no` as title identity fields.

- Increasing quantity creates the difference as new available copies.
- Keeping quantity unchanged updates only title metadata.
- Decreasing quantity archives available copies until the requested total is reached.
- A decrease below the number of active borrowed/reserved copies is rejected with the minimum permitted quantity.
- Every change is atomic: title metadata, copy changes, and the response either all succeed or all roll back.

### Manage physical copies

Each title row exposes a “View copies” action. The copy panel lists barcode, accession, location, status, due date, and return date. Copy editing uses `copy_id` and validates barcode/accession uniqueness against other copies. Copy archive/delete actions retain the existing destructive confirmation modal and reject removal of actively borrowed copies.

## API boundaries

Keep the inventory API explicit rather than relying on ambiguous fields:

- `GET /api/books`: returns title rows with `title_id`, metadata, `quantity`, `available_quantity`, `reserved_quantity`, and `borrowed_quantity`.
- `POST /api/books` with `action=create_title`: creates a title and generated copies.
- `POST /api/books` with `action=update_title`: updates title metadata and synchronizes quantity by `title_id`.
- `GET /api/book-copies?title_id=<id>`: returns the copies for one title.
- `POST /api/book-copies` with `action=update`: updates one copy by `copy_id`.
- `POST /api/book-copies` with `action=archive|restore|delete`: changes one or more copy records subject to active-loan protection.

The existing `action=create|update` payloads may remain as compatibility aliases only if they are translated to the explicit title actions. The server must never interpret a grouped title's `title_id` as a physical `book_copies.id`.

All mutation responses use the existing JSON envelope and return field-specific messages. Duplicate barcode and accession failures identify the conflicting identifier. Quantity failures identify both the requested quantity and the active-copy minimum.

## Data and migration behavior

No new schema is required for the core workflow because the normalized tables already model titles, copies, and transactions. Existing installations must run `sql/upgrade_bulk_borrowing.sql` before using this workflow. The migration must remain safe for legacy collations, duplicate ISBN source rows, and reruns.

The application will detect an unprepared legacy schema and return an actionable setup error for title quantity operations rather than reporting a false successful update. Documentation will continue to list the normalized migration as mandatory for existing databases and fresh legacy imports.

## Backend components

- `BookController`: route and validate explicit title/copy actions, including correct 422 field messages.
- `BookMutationService`: separate title mutation and copy mutation orchestration.
- `PdoBookRepository`: search grouped titles, create/update titles with copy synchronization, and expose copy queries/mutations.
- `BookMutationRequest` and new copy DTO/validator types: represent title and copy payloads without overloading barcode fields.
- `BookArchiveService` and repository interfaces: preserve active-loan protections for copies and title-level archive behavior.

## Frontend components

- Inventory page: render title counts from server fields, use `title_id` for edit actions, remove the misleading required title barcode behavior, and display generated-copy feedback.
- Copy panel/component: load and edit physical copies independently, refresh the parent title row after changes, and use confirmation for destructive copy operations.
- Inventory service: provide typed action methods for title and copy endpoints.
- Shared error handling: show server messages instead of the generic “Request failed” toast so the cause of a rejected identifier or quantity is visible.

## Error handling and consistency rules

- A grouped title may have zero or more generated identifiers, but a physical copy may never have a blank or duplicate barcode.
- The title quantity must equal the count of non-archived copies after every successful title mutation.
- Dashboard, search, borrower portals, reports, and inventory must calculate copy counts from the same non-archived `book_copies` rows.
- Copy status changes caused by borrowing/returns remain owned by the borrowing workflow; inventory cannot manually mark an actively borrowed copy available.
- Database transactions protect bulk creation and quantity synchronization.

## Testing strategy

Test-first coverage will include:

1. Updating a grouped title without a barcode succeeds and changes quantity.
2. Increasing a title from one copy to fourteen creates thirteen unique copies and updates dashboard totals.
3. Decreasing quantity archives only removable available copies and rejects a value below active copies.
4. Creating a title with quantity greater than one accepts blank barcode/accession fields and returns generated identifiers.
5. Duplicate manual copy identifiers return targeted 422 responses without partial writes.
6. Copy list/edit/archive operations use `copy_id` and refresh title counts.
7. Inventory markup and JavaScript send title IDs for title edits and copy IDs for copy edits.
8. Legacy/unmigrated setup errors are explicit, and the migration contract remains collation-safe and rerunnable.
9. Full PHP and frontend test suites continue to pass.

## Non-goals

- Replacing the borrowing transaction model.
- Allowing duplicate physical barcodes.
- Adding CSV import or a separate warehouse-management workflow.
- Removing legacy tables in this feature; cleanup remains a separate migration decision.
