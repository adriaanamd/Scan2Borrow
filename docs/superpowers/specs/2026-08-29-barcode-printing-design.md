# Barcode printing design

## Goal

Give staff a safe, auditable way to export barcode labels for every physical copy of one book title that has not been exported before.

## Approved behavior

- Printing is implemented as a print-ready browser page whose action is **Export PDF**. The browser's print dialog is used with “Save as PDF”.
- The workflow is per catalog title: staff open a title's Copies panel and export all currently unprinted, non-archived copies in one batch.
- A physical copy is marked printed when the export batch is created. This marker is irreversible.
- If every active copy is already marked printed, the request succeeds as a normal skip and does not create a new batch.
- Previously created batches can be opened again for PDF re-export from their immutable snapshot; reopening a batch never changes printed state.
- A canceled browser print dialog does not unmark copies. The UI explains that export generation is the irreversible point.

## Data model

`book_copies.printed_at` is the per-copy irreversible marker. `barcode_print_batches` records who created an export and when. `barcode_print_batch_items` stores immutable label snapshots so a historical PDF can be re-exported even if catalog metadata changes later.

The schema is added through `sql/upgrade_barcode_printing.sql`. The README install order is updated so fresh and existing installations apply it after the bulk-borrowing migration.

## API

- `POST /api/barcode-print-batches` creates a batch for a title. It requires staff authentication and CSRF. The server selects and locks unprinted active copies, creates the batch and snapshots, marks those copies printed, and returns the batch token and labels. No copy IDs are trusted from the browser.
- `GET /api/barcode-print-batches?batch_token=...` returns one immutable batch for staff re-export.
- `GET /api/barcode-print-batches?title_id=...` returns a title's export history for the Copies panel.

The create operation is transactional and row-locks candidate copies so two simultaneous requests cannot export the same copy twice.

## Label output

The print page renders one label per physical copy with title, author, barcode, accession number, and location details. It uses the existing browser print conventions and shared application palette; it does not add a PDF library or mutate the existing sidenav.

## Security and validation

- Only admin and librarian sessions can create, view, or re-export batches.
- Create requests validate a positive title ID and CSRF token.
- Batch tokens are opaque, length-limited, and allowlisted before database lookup.
- Snapshot data is escaped before insertion into the print DOM.
- Existing borrowing and copy-management behavior remains unchanged.
