# Borrower Approval Notification Panel

## Goal

Notify students and teachers in the browser when staff approves their online borrowing request, including bulk requests, through a persistent notification panel.

## Product behavior

- The feature applies to student and teacher online borrowing approvals.
- Staff approval creates one notification per approved borrowing transaction.
- A bulk approval creates one message listing all distinct approved book titles.
- The notification identifies that the request was approved and names the borrowed book or books.
- Rejected requests are out of scope for this change.
- The existing recommendation shelf and Recent Activity panel remain unchanged.

Example message:

> Your borrowing request was approved. You borrowed: "Clean Code", "Refactoring".

## Chosen architecture

Reuse the existing `notifications` table and `CirculationNotificationRepositoryInterface` used by hold and renewal notifications. Extend the approval persistence path so the approval and its borrower notification are written in the same database transaction. Add borrower-scoped read endpoints and make the existing shared borrower navigation render a notification bell/panel for both student and teacher roles.

This gives the borrower a durable unread notification even if they were offline when staff approved the request. The recommendation shelf remains a separate catalog surface and is not changed by this feature.

## Data and schema

The `notifications.type` enum will support a new `borrow_approved` value.

- Fresh installs: update `sql/install.sql`.
- Existing installs: add a rerunnable migration at `sql/upgrade_borrower_approval_notifications.sql`.
- Notification rows use the approved borrowing transaction ID as `related_id`.
- `is_read` remains the source of truth for the unread badge.

No new table is required.

## Approval flow

When `PdoStaffRepository` approves an online borrowing request:

1. Load the borrower ID and the titles represented by the transaction before changing its status.
2. Update the transaction, borrowing items, and physical copy statuses using the existing normalized or legacy path.
3. Mark the staff-side approval notification as read using the existing behavior.
4. Call `CirculationNotificationRepositoryInterface::notifyBorrower()` once with:
   - the transaction borrower ID;
   - type `borrow_approved`;
   - title `Borrowing request approved`;
   - a message listing the approved title(s);
   - the transaction ID as `related_id`.
5. Commit the transaction.

The existing pending-state guard keeps approval idempotent. If the borrower notification insert fails, the transaction rolls back so an approval is never persisted without its required notification.

The notification repository is injected into `PdoStaffRepository` as an optional dependency for compatibility with existing unit fixtures; production wiring supplies `PdoCirculationNotificationRepository` using the same PDO connection.

## Borrower API

Add role-aware routes for both students and teachers:

- `GET /api/student/notifications`
- `GET /api/teacher/notifications`
- `POST /api/student/notifications`
- `POST /api/teacher/notifications`

The GET response returns the latest 20 user-scoped rows and an unread count:

```json
{
  "ok": true,
  "data": {
    "notifications": [
      {
        "id": 12,
        "type": "borrow_approved",
        "title": "Borrowing request approved",
        "message": "Your borrowing request was approved. You borrowed: \"Clean Code\".",
        "related_id": 44,
        "is_read": 0,
        "created_at": "2026-11-10 12:00:00"
      }
    ],
    "unread_count": 1
  }
}
```

The POST action supports:

- `{action: "read", notification_id: <positive integer>}` to mark one row read;
- `{action: "read_all"}` to mark all rows read for the current user.

Every query and update includes the authenticated user ID. Invalid IDs and unauthorized roles use the existing controller response conventions.

## Browser panel

The already-loaded shared borrower navigation script will render a borrower-only notification bell in the top bar. The panel will:

- show an unread badge;
- fetch current data when opened;
- display the latest messages with title, message, and time;
- mark individual messages read when selected;
- provide a “Mark all as read” control;
- show a clear empty state when no notifications exist;
- keep staff and guest navigation unchanged.

The panel will use escaped text and existing shared styling conventions. It will work on both student and teacher pages without changing the recommendation shelf or Recent Activity markup.

## Components and files

### Backend

- `PdoStaffRepository`: create approval notifications during normalized and legacy approval transactions.
- `ApplicationFactory`: inject the shared circulation notification repository into staff persistence.
- `BorrowerController`, `BorrowerRouteTable`, and `BorrowerPortalRepositoryInterface`: expose borrower notification reads and actions.
- `PdoBorrowerPortalRepository`: query and update user-scoped notification rows.
- `PdoCirculationNotificationRepository`: reuse existing borrower notification insertion.
- `sql/install.sql`: add the new notification enum value.
- `sql/upgrade_borrower_approval_notifications.sql`: add the enum value for existing databases.

### Frontend

- `frontend/assets/js/core/app-navbar.js`: render and operate the borrower notification panel through the shared navigation layer.
- `frontend/assets/css/style.css` or the existing borrower shared stylesheet: add only the panel/badge styles required for the new markup.

## Testing strategy

Test-first coverage will include:

1. Normalized student approval writes one `borrow_approved` notification with the borrower ID and title.
2. Normalized bulk approval writes one notification whose message includes all approved titles.
3. Legacy teacher approval preserves compatibility and writes the same notification type.
4. A repeated approval does not create a duplicate notification.
5. Borrower notification GET returns only the current user’s rows and the correct unread count.
6. Read-one and read-all actions cannot affect another user’s notifications.
7. Borrower navigation renders the bell, badge, panel, empty state, and read controls while staff/guest navigation remains unchanged.
8. The schema and upgrade migration both contain `borrow_approved` and remain rerunnable where the project migration style requires it.

Verification will run targeted PHPUnit and frontend tests first, followed by the full relevant local CI checks. Existing unrelated baseline failures will be reported separately and not weakened.

## Non-goals

- OS-level browser push permission or service-worker notifications.
- Email or SMS delivery for approval messages.
- Reworking recommendation ranking or Recent Activity.
- Adding rejection notifications.
- Changing staff notification behavior beyond marking the handled approval request read.

