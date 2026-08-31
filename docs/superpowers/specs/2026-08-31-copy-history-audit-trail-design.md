# Copy History and Business Audit Trail Design

**Date:** 2026-08-31
**Status:** Approved for implementation
**Scope:** Staff panel only

## Context

Scan2Borrow already treats each physical copy as a `book_copies` record with its own barcode, accession number, location, status, loan linkage, and barcode-print history. The missing capability is an immutable business history that lets staff scan one barcode and understand the copy's complete lifetime, including who performed each action and why.

The application is a custom PHP 8.3-style application using PDO repositories, a small HTTP router, server-rendered HTML entry pages, and vanilla JavaScript modules. The implementation will follow those existing boundaries rather than introducing a framework or application-wide logging system.

## Goals

- Let authorized staff retrieve a copy's entire lifetime by barcode.
- Record business actions with actor, timestamp, before/after values, and reason where relevant.
- Make `Lost` and `Damaged` first-class physical-copy statuses.
- Require a business reason for status changes involving `Lost` or `Damaged`.
- Preserve an append-only audit record that is independent of application/runtime logs.
- Provide both a dedicated barcode lookup page and an entry point from the existing physical-copy panel.
- Include historical events that can be reconstructed from existing data, without fabricating staff identities.

## Non-goals

- Exposing copy history to students, teachers, guests, or public pages.
- Replacing existing loan, return, inventory, or barcode-print workflows.
- Building a general-purpose audit framework for every table in the application.
- Editing or deleting audit events from the UI.
- Inferring an actor where the legacy record has no actor field.

## Approved approach

Use one append-only `audit_events` table as the canonical business stream for copy events. Domain services write explicit events at the point where a business action succeeds. The history query joins copy, title, and staff information and returns a chronological event list for the staff UI.

This is preferred over per-feature history tables because the barcode timeline is a first-class use case, and over database triggers because triggers cannot reliably receive the authenticated staff identity or the required business reason.

## Domain model

### Copy statuses

The normalized `book_copies.status` column will support:

- `Available`
- `Borrowed`
- `Reserved`
- `Lost`
- `Damaged`

Existing behavior for the first three statuses remains unchanged. Status mutation validation will accept only these five values.

### Audit event types

The initial event vocabulary is deliberately small and tied to business actions:

- `acquired` — a physical copy was created.
- `status_changed` — the copy status changed, including transitions to or from `Lost` and `Damaged`.
- `loaned` — a borrowing transaction attached the copy to a borrower.
- `returned` — a loan item was completed and the copy was returned.
- `barcode_printed` — a barcode export batch included the copy.
- `archived` — the copy was soft-archived.
- `restored` — an archived copy was restored.
- `deleted` — an archived copy was permanently deleted; the event must be written before the row is removed.

`status_changed` carries the reason for every transition involving `Lost` or `Damaged`. The UI and validator require a non-empty reason for those transitions. Other status changes may accept an optional reason so staff can document unusual handling without being forced to enter one for ordinary circulation.

### Event record

The event record contains:

- its own integer identifier;
- `copy_id`, retained as a foreign key while the physical copy exists;
- nullable `actor_user_id` for the staff member who performed the action;
- a stable event type;
- nullable `from_status` and `to_status` values;
- nullable `reason`;
- nullable `transaction_id`, `borrowing_item_id`, and `print_batch_id` references when applicable;
- a JSON metadata snapshot for stable display context such as barcode, title, borrower label, accession, and legacy provenance;
- `occurred_at` with database timestamp precision.

The metadata snapshot is not a replacement for relational links. It preserves what staff saw for deleted or changed related records and gives the timeline a useful display label without allowing a later title edit to rewrite history.

Events are inserted only. There is no update or delete endpoint for them.

## Historical backfill

The migration will backfill what the current database can establish:

- `acquired` from `book_copies.created_at` and the copy's original identifying fields;
- `loaned` and `returned` from `borrowing_items`, `borrowing_transactions`, and legacy `borrowing` rows where present;
- `barcode_printed` from existing barcode print batch membership;
- an initial `status_changed` baseline only when the existing record has enough information to distinguish a meaningful prior state; otherwise the current status is represented by the copy snapshot, not a fabricated transition.

Legacy actor columns such as `processed_by` and `approved_by` will be used where they exist. If no actor can be recovered, `actor_user_id` remains null and the UI displays `Historical record` / `Actor not recorded`. This is explicit provenance, not a fake staff account.

The backfill must be idempotent. A unique legacy-source key or equivalent metadata guard will prevent duplicate events if the migration is run more than once.

## Write paths

### Copy acquisition

When a normalized copy is inserted, the same transaction inserts an `acquired` event. Title creation and quantity expansion must identify each generated copy and record its own event.

### Copy updates and status changes

The copy mutation service/repository reads the current copy, validates the requested status and reason, updates the copy, and inserts one `status_changed` event when the status differs. If identifying/location fields change without a status change, the mutation is still recorded as a business update event only if the existing UI action represents a meaningful copy change; otherwise the audit stream remains focused on the approved event vocabulary.

Status changes involving `Lost` or `Damaged` fail with HTTP 422 when the trimmed reason is empty. The reason is stored in the event and is never emitted into application logs.

### Loans and returns

The normalized borrowing repository/service writes `loaned` after a loan transaction and item have been created successfully. Return completion writes `returned` in the same transaction as the loan item and copy status update. Existing borrower-driven flows use the current authenticated user as borrower but only a staff actor is recorded when a staff account is actually performing the business action; borrower actions may have a null staff actor with metadata identifying the initiating flow.

### Barcode printing

Barcode batch creation records `barcode_printed` events for the individual copies included in the new batch, using the authenticated staff account and the print batch reference. Re-exporting an existing batch is a print action against the batch but does not create duplicate lifetime events for copies unless new labels are actually generated.

### Archive, restore, and delete

Copy archive and restore actions create their corresponding events. Permanent deletion inserts `deleted` before deleting the copy; the event must not rely on a foreign key that would prevent retaining the deleted copy's audit row. The implementation will use nullable foreign-key behavior or a stable metadata snapshot as required by the database engine.

## Service and repository boundaries

- `AuditEvent` domain record/value object: validates event type and exposes a typed display payload.
- `AuditEventRepositoryInterface`: append events and query a copy's timeline.
- `PdoAuditEventRepository`: PDO implementation with parameterized SQL and driver-compatible JSON handling.
- `CopyAuditService` or equivalent application service: resolves a barcode, loads copy context, and returns the complete timeline DTO for staff controllers.
- Existing mutation/borrowing/printing services receive the audit repository through constructor injection and write events in the same database transaction as the business mutation.
- A staff-only controller and route expose barcode lookup. The controller validates a non-empty barcode, maps not-found to 404, and never returns data for non-staff sessions.

The controller will not compose audit text from untrusted HTML. The API returns typed fields; the frontend escapes all values before rendering.

## HTTP surface

Add a staff-only endpoint:

`GET /api/staff/copy-history?barcode={barcode}`

Success response:

```json
{
  "ok": true,
  "data": {
    "copy": {
      "copy_id": 42,
      "barcode": "BK-0042",
      "accession_no": "ACC-0042",
      "title": "Example title",
      "author": "Example author",
      "status": "Available",
      "location": "Floor 2 / Fiction / Shelf A / Row 3"
    },
    "events": []
  }
}
```

The concrete event fields include `type`, `label`, `occurred_at`, `actor`, `from_status`, `to_status`, `reason`, and related reference labels. The API will return an empty event list only for a valid copy whose history has no rows after migration, though normal migrated copies should have an acquisition event.

Error behavior:

- `401` for unauthenticated/non-staff requests;
- `404` when no live or archived copy matches the barcode, subject to the chosen staff policy for archived records;
- `422` for a missing or invalid barcode parameter;
- `500` only for an unexpected infrastructure failure.

## Staff UI

### Visual direction

Use the Swiss anchor: neutral white/`#F7F7F8` surfaces, one Helvetica Neue/Akzidenz-style sans stack, Yves Klein blue `#002FA7` as the single accent, left-aligned type, and visible 1px grid rules. This gives the audit surface a precise library-record character without copying the existing dashboard's card-heavy visual language.

The differentiator is a vertical audit spine: every event is pinned to a continuous blue rule, with status transitions displayed as a compact before → after pair and the reason directly beneath it. The spine remains legible on mobile by collapsing the metadata into stacked blocks.

### Dedicated page

Add `/staff/copy-history` and a staff navigation item named `Copy History`. The page contains:

- a barcode input with a real scanner action using the existing scanner component pattern;
- a manual search/submit action;
- a copy identity header with title, barcode, accession, current status, and location;
- a chronological timeline newest first;
- explicit empty, loading, not-found, and error states;
- no fabricated sample events or staff names.

### Inventory entry point

Add `View history` to each active/archived physical-copy row in the existing copy panel. It opens the dedicated page with the barcode in the query string or navigates directly to the page and focuses the lookup. The panel remains responsible for copy editing and barcode export; the dedicated page owns the lifetime timeline.

### Interaction rules

- Keyboard submit works for manual barcode entry.
- A successful scan replaces the previous timeline and updates the browser URL without leaking unrelated query state.
- All server-provided strings are escaped before insertion.
- Status badges have text labels and are not color-only.
- The reason is shown whenever present; actor and timestamp are always visible for each event.
- Staff with either `admin` or `librarian` role can view the history. Existing staff page authorization is reused.

## Error handling and consistency

- Business event insertion occurs in the same transaction as its triggering mutation wherever the existing repository already owns the transaction.
- If an event cannot be persisted, the business mutation rolls back rather than succeeding without an audit record.
- Audit reads tolerate a missing related borrower/title label by falling back to the metadata snapshot.
- Audit history queries are bounded by barcode lookup and indexed by copy/timestamp; pagination is not needed for the first staff surface, but the repository contract should be shaped so pagination can be added without changing the controller contract.
- Reasons are trimmed, length-limited, and stored as plain text. Output encoding is performed at the presentation boundary.

## Testing strategy

Backend unit tests will cover:

- event type/status validation;
- required reasons for transitions to/from `Lost` and `Damaged`;
- actor and metadata mapping;
- barcode lookup success/not-found/invalid input;
- staff authorization;
- idempotent historical backfill guards;
- event creation for acquisition, loan, return, print, archive, restore, and delete;
- transaction rollback when audit insertion fails.

Backend feature tests will cover the new route, JSON contract, role access, and migration/schema contract. Existing borrowing, return, copy mutation, and barcode-print tests will be extended so the new audit dependency is verified without weakening current behavior.

Frontend tests will cover:

- page registration and staff route markup;
- scanner/manual lookup behavior;
- timeline rendering and escaping;
- status transition reason presentation;
- empty/not-found/error states;
- inventory copy-panel history navigation.

Verification will run the project's PHPUnit suite, frontend test suite, PHPStan level 9 if configured in the current environment, and focused schema/contract tests. Any unavailable external database dependency will be reported with the exact command output.

## Acceptance criteria

- A staff member scans a barcode from the dedicated page and sees the copy's complete migrated/new lifetime in chronological order.
- Each event identifies the action, timestamp, and actor when known.
- Status changes show both states and the recorded reason when supplied/required.
- `Lost` and `Damaged` can be selected in staff copy editing, and transitions involving them cannot be saved without a reason.
- New acquisition, loan, return, print, archive, restore, and delete actions produce audit events.
- No audit event can be edited or deleted through the application.
- Non-staff users cannot access the page or API.
- Existing inventory and circulation behavior remains intact.
- The implementation does not use application logs as the business audit trail.
