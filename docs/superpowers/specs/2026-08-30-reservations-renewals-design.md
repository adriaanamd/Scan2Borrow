# Reservations and Renewals Design

**Date:** 2026-08-30  
**Status:** Approved for implementation

## Goal

Add title-level reservations with a fair hold queue, 24-hour claim windows, automatic queue advancement and notifications, plus borrower-created renewal requests that require librarian approval.

## Context

Scan2Borrow is a framework-free PHP 8.2 modular monolith using PDO repositories, typed application services, route tables, PHPUnit, PHPStan, and a module-oriented JavaScript frontend. The normalized circulation schema uses `book_titles`, `book_copies`, `borrowing_transactions`, and `borrowing_items`, while repository capability checks retain compatibility with the legacy `books` and `borrowing` tables.

The existing system has a `Reserved` copy status, borrower dashboards/history, librarian approval flows, in-app staff notifications, and an SMTP email adapter. It does not yet have title-level queue records, hold expiry, renewal request state, or a staff review surface for either feature.

## Product decisions

- Reservations are title-level, not copy-level.
- Queue order is first-come-first-served by an immutable sequence/timestamp combination.
- A borrower may have one active reservation per title.
- A borrower who already has an active loan for the title cannot reserve it.
- When a copy becomes available, the oldest eligible queued borrower receives an offer.
- An offer expires after exactly 24 hours and the next eligible borrower is notified.
- Inactive accounts and borrowers with unresolved standing restrictions are not eligible for offers.
- Borrowers create renewal requests themselves.
- Librarians approve or reject renewal requests.
- One loan may receive one approved renewal.
- An approved renewal extends the due date by one standard loan period.
- Renewal eligibility requires an active account, no overdue active loan, no outstanding fine, no active hold on the title, and no previous approved renewal for that loan.
- Existing in-app notifications are the default automatic channel; email is sent when a borrower has an address and SMTP is configured.
- Every mutating endpoint uses the existing CSRF and role authorization mechanisms.

## Reservation model

Reservation records belong to a `book_titles` row in the normalized path and retain a legacy-compatible title/book mapping where the old schema is active. The record includes:

- borrower/user id;
- title id;
- monotonically increasing queue sequence;
- status: `queued`, `offered`, `claimed`, `fulfilled`, `expired`, or `cancelled`;
- timestamps for creation, offer, claim, fulfilment, expiry, and cancellation;
- the offered copy id when an offer is created;
- `hold_expires_at` for the 24-hour claim window;
- audit metadata for staff actions.

The unique active reservation rule is enforced in the service and protected by a database-compatible uniqueness strategy. Queue reads order by sequence ascending. Offer creation runs in a transaction, locks the title/queue candidates where supported, selects the oldest eligible borrower, marks the copy reserved, creates the offer, and writes the notification atomically.

### Reservation state transitions

```text
queued
  ├─ availability event → offered
  ├─ borrower cancellation → cancelled
  └─ standing becomes invalid → cancelled/ skipped by advancement

offered
  ├─ borrower claim → claimed
  ├─ 24-hour expiry → expired → next queued borrower offered
  └─ staff cancellation → cancelled → next queued borrower offered

claimed
  ├─ normal librarian borrowing completion → fulfilled
  └─ staff cancellation → cancelled
```

Returns and other availability events call a shared queue advancement service. A repeat call is idempotent: an already offered title, unavailable copy, or empty queue does not create duplicate offers.

## Renewal model

Renewal requests belong to one active normalized borrowing item or one legacy `borrowing` record. The record includes:

- borrower/user id;
- loan/item id and title id;
- status: `pending`, `approved`, `rejected`, or `cancelled`;
- requested, reviewed, approved/rejected, and cancelled timestamps;
- librarian reviewer id;
- due date before and after the decision;
- rejection reason when supplied;
- a renewal count protected by the service and database transaction.

The request service rechecks all rules at request time and the approval service rechecks them again. Approval updates the associated due date and records the one-time extension in the same transaction as the request approval. Rejected and cancelled requests do not affect the loan renewal count.

## API surface

Borrower routes are available for students and teachers with equivalent behavior:

- `GET /api/student/holds` and `/api/teacher/holds` — current borrower reservations;
- `POST /api/student/holds` and `/api/teacher/holds` — join a title queue;
- `POST /api/student/holds/action` and `/api/teacher/holds/action` — cancel or claim;
- `GET /api/student/renewals` and `/api/teacher/renewals` — current borrower requests;
- `POST /api/student/renewals` and `/api/teacher/renewals` — request renewal;
- `POST /api/student/renewals/action` and `/api/teacher/renewals/action` — cancel a pending request.

Staff routes:

- `GET /api/staff/holds` — queue and offer review data;
- `POST /api/staff/holds/action` — cancel, advance, or fulfil a hold;
- `GET /api/staff/renewals` — pending and historical renewal requests;
- `POST /api/staff/renewals/action` — approve or reject a request.

Maintenance command:

- `php backend/bin/process-circulation.php expire-holds` — expires all offered holds whose `hold_expires_at` is at or before the current clock time and advances affected queues. The command is safe to rerun.

All payloads are validated through typed DTO construction or dedicated validators. Responses retain the existing `{ok, data, errors}` JSON shape.

## Notifications

Automatic borrower notifications are stored in the existing notification system for dashboard visibility. Queue advancement creates a hold-available message containing the title, offer expiry, and claim action. Expiry creates no notification for the expired borrower but notifies the next eligible borrower. Renewal approval/rejection creates a borrower notification with the resulting due date or rejection reason. Email delivery reuses the existing SMTP sender and is best-effort after the in-app notification is committed; a missing address or unconfigured SMTP does not block circulation state changes.

## Frontend direction

The new surfaces use a Swiss library-desk visual direction:

- neutral surface `#F7F7F8`;
- one accent, Swiss Red `#E4002B`;
- Helvetica Neue/system sans for display and body;
- visible 1px hairline rules and left-aligned asymmetric layout;
- dates, queue position, and remaining hold time as prominent numerals.

The differentiator is a vertical queue rail showing the real queue position and the 24-hour offer countdown. It is shown only when the API supplies those values. Empty states use factual copy and do not fabricate sample reservations, people, or dates.

Borrower dashboard/history surfaces expose reservations and renewal actions. Staff dashboard surfaces expose separate hold and renewal review states with filters and clear approve/reject/cancel actions. All rendered borrower-owned values are escaped before insertion.

## Error handling and concurrency

- Duplicate active reservations return a validation error and the existing reservation remains unchanged.
- Invalid claim/cancel/renewal actions return an authorization or conflict response rather than mutating a different borrower’s record.
- A claim after expiry returns a conflict and does not reserve a copy.
- Approval of a renewal that became ineligible returns a conflict and records no due-date change.
- Return/offer/expiry workflows use transactions and row locks where supported by the active PDO driver.
- Service operations are written to be idempotent for repeated maintenance runs and browser retries.

## Testing and verification

Backend unit and PDO tests cover FIFO ordering, duplicate prevention, 24-hour expiry, eligibility, automatic next-person notification, claim/cancel/fulfil transitions, renewal limits, standing restrictions, approval/rejection, and legacy fallback. Feature tests cover role authorization, CSRF, route registration, payload validation, and response shape. Frontend tests cover service URLs, data escaping, real-state rendering, empty states, and student/teacher parity.

Before completion, run:

```text
cd backend && composer test
cd backend && composer analyse
npm test
```

The implementation will contain at least 12 meaningful commits for reservations and at least 12 meaningful commits for renewals, including schema, domain/service, repository, controller/routes, frontend, tests, and documentation work.

