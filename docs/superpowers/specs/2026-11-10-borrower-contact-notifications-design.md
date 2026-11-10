# Borrower Contact Notifications Design

**Date:** 2026-11-10
**Status:** Implemented; final verification and remote CI pending

## Goal

Notify students, teachers, and guests by SMS and/or email when a borrowing request is submitted and when it is approved. The notification must identify the requested or borrowed book(s), reference, and due date when available.

## Requirements

- Cover registered student and teacher borrower requests.
- Cover guest borrow requests.
- Cover approval of registered-user requests and guest requests.
- Cover immediate staff-desk borrowing as an approved event for students and teachers.
- Send to every available contact: email when an address exists and SMS when a phone number exists.
- Group multiple copies/titles into one message per event and channel.
- Preserve existing browser/in-app notifications.
- Do not roll back a successful borrowing or approval because an external provider fails.
- Prevent duplicate delivery for the same borrower, borrowing reference, event, and channel.

## Non-goals

- Adding a new SMS or email provider.
- Building a background worker, cron job, or queue processor.
- Adding a notification-management UI.
- Changing existing browser/in-app notification behavior.
- Sending return, renewal, overdue, or rejection contact messages as part of this feature.

## Design

### Unified delivery record

Add a `contact_notification_deliveries` table to `sql/install.sql` and the forward-compatible upgrade script. The table is intentionally not foreign-keyed to a single borrowing table because the application currently supports both normalized and legacy borrowing storage, plus a separate guest borrowing table.

Each row represents one event/channel pair:

- `recipient_type`: `user` or `visitor`
- `recipient_id`: registered user ID or visitor ID
- `event_type`: `borrow_requested` or `borrow_approved`
- `borrowing_reference`: transaction code or storage-specific borrowing ID, stored as text
- `channel`: `email` or `sms`
- `destination`: email address or phone number used for the attempt
- `subject`: email subject, nullable for SMS
- `message`: rendered notification body
- `status`: `pending`, `sent`, `failed`, or `skipped`
- `attempts`: number of provider attempts
- `last_error`: provider/configuration error, nullable
- `sent_at`: successful delivery time, nullable
- `created_at` and `updated_at`

Add a unique key across recipient type, recipient ID, event type, borrowing reference, and channel. This makes the operation idempotent even if an approval or request handler is retried.

Rows with no usable contact are marked `skipped`. Rows for an unavailable provider or failed provider call are marked `failed`, preserving the error for audit or a future retry mechanism. No worker is required for the initial implementation.

### Delivery service

Introduce an application-level borrower contact notification service that:

1. Accepts a normalized event payload containing recipient type/ID, event type, borrowing reference, title list, and optional due date.
2. Resolves the registered user's or visitor's name, email, and phone from the existing persistence layer.
3. Builds the email subject/body and concise SMS body.
4. Creates idempotent delivery rows for available channels.
5. Attempts delivery after the borrowing transaction has committed.
6. Updates each row to `sent`, `failed`, or `skipped` without throwing provider failures back into the borrowing flow.

The service will reuse `EmailSenderInterface` and `SmsSenderInterface`. Email requires `isConfigured()` to be true; SMS requires a configured sender instance. Existing sender behavior and provider configuration remain unchanged.

### Message behavior

Request event:

- Email subject: `Scan2Borrow - Borrow Request Received`
- Identifies all requested title(s), quantities where relevant, and the request/transaction reference.
- States that the request is pending staff approval.

Approval event:

- Email subject: `Scan2Borrow - Borrowing Approved`
- Identifies all approved title(s), quantities where relevant, the reference, and due date when available.
- States that the borrowing request was approved.

SMS text is the concise equivalent of the email content. Titles are escaped in HTML email output and normalized before rendering. No password, government-ID value, ID photo, or other sensitive registration data is included.

### Integration points

#### Registered student and teacher portal

After `BorrowingService` successfully commits a single or bulk request, dispatch `borrow_requested`. The bulk repository result must expose the selected title names (while preserving its existing counts and transaction code) so the message can be accurate.

If a deployment uses immediate approval instead of pending approval, dispatch only `borrow_approved` for that transaction rather than sending a request message that implies a pending state.

#### Registered student and teacher approval

After the staff repository commits an approval, dispatch `borrow_approved` using the captured approval context. The existing in-app `borrow_approved` notification remains in place. A stale/repeated approval that updates no row must not dispatch a second contact event.

#### Staff-desk borrowing

After `StaffCirculationRepositoryInterface::createDirectBorrow()` commits, dispatch `borrow_approved` for the registered student or teacher. The existing direct-borrow result already contains the transaction reference, due date, and title items needed to build the message.

#### Guest borrowing

After `GuestBorrowingService::submitRequest()` creates the pending visitor borrowing, dispatch `borrow_requested`. The guest borrowing result/repository contract must expose the title and due date needed for the payload.

After `GuestApprovalService::approve()` commits the release, dispatch `borrow_approved`. The current visitor in-app approval notification remains in place.

### Transaction boundary

External delivery must happen after the domain/persistence transaction commits. A provider timeout or rejected send therefore cannot turn a completed borrowing into a failed borrowing. Delivery failures are captured in the delivery table and remain visible for audit or future retry work.

## Testing and verification

Add focused tests for:

- both-channel delivery when both contacts are present;
- email-only and SMS-only recipients;
- missing contact details and unconfigured providers;
- provider exceptions and failed email results;
- idempotent duplicate events;
- grouped bulk titles and quantities;
- registered-user request and approval flows;
- guest request and approval flows;
- staff-desk approved borrowing;
- install/upgrade schema contracts.

Run targeted backend tests first, then the complete affected backend quality lane and frontend tests if shared wiring or contracts require them. Existing known failures remain baseline and must be reported separately; no assertions or quality gates may be weakened.

## Open implementation notes

- Preserve unrelated dirty-worktree files.
- Make changes inline on `master`, as requested.
- Commit only files belonging to this feature.
- Push the completed commits to the configured `origin` after fresh verification.
