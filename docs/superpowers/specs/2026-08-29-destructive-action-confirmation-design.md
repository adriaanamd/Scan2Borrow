# Destructive Action Confirmation Design

## Status

Approved in conversation on 2026-08-29. This design covers reusable confirmation warnings for destructive and high-impact frontend actions.

## Goal

Add a reusable confirmation-modal layer to Scan2Borrow so destructive actions require an intentional confirmation before they submit or navigate, while ordinary workflows remain uninterrupted.

## Scope

The confirmation layer applies to:

- Logout.
- Book archive and permanent deletion, including bulk actions.
- Account disabling and staff demotion.
- Borrow-request approval and rejection.
- Guest-request rejection, including rejection without a reason.

The confirmation layer does not apply to navigation, search, filtering, opening or viewing informational modals, add/edit/save forms, restoring archived books, or ordinary borrow/return submission.

No backend, database, route, API, or authorization changes are required.

## Current context

The application currently serves static HTML pages with deferred legacy scripts under `frontend/assets/js`. Bootstrap 5.3.3 is already loaded by the pages. The repository also contains a newer app-layer `ModalService`, but the active pages still use legacy controllers such as `inventory.js`, `staff.js`, and `app-navbar.js`.

Several actions currently use `window.confirm()` or submit immediately. Inventory rows are rendered dynamically, and the navbar logout link is injected dynamically, so confirmation handling must support delegated events and refreshed DOM.

## Proposed architecture

Add a shared legacy-core module:

```text
frontend/assets/js/core/confirmation.js
```

The module will expose a small global service for legacy scripts and an equivalent Promise-based API suitable for later adoption by the app-layer services. Its primary operation accepts:

```js
confirm({
  title,
  message,
  confirmLabel,
  confirmClass,
  onConfirm,
})
```

The service owns one Bootstrap modal instance per document. It creates the modal lazily on first use, updates its contextual content for each request, and removes or replaces transient listeners when the request finishes. The caller receives a Promise resolving to whether the action was confirmed, or can provide the continuation callback through the service API.

The implementation must not modify or take ownership of existing borrow, return, camera, upload, photo, approval-list, or informational modals.

## Interaction flow

```text
destructive trigger
    -> prevent default / stop immediate action
    -> open shared confirmation modal
        -> cancel, close, Escape, or backdrop
            -> resolve false; restore focus; take no action
        -> confirm
            -> disable controls and show Processing…
            -> execute the stored link, form submit, or API continuation once
            -> resolve true and clean up
```

The service must guard against duplicate confirmations and duplicate submissions while a request is active. It must restore the original trigger state and focus the trigger again when possible. If Bootstrap or the shared service cannot initialize, callers use the existing native confirmation fallback rather than proceeding silently.

## Integration points

### Navbar logout

The shared navbar module will mark or identify logout links consistently. A delegated click handler will cancel the original navigation, request confirmation, and navigate to the existing logout URL only after confirmation.

### Inventory

`inventory.js` will route single and bulk archive/delete actions through the service. Existing action names, request payloads, API endpoints, success messages, and refresh behavior remain unchanged. Restore stays immediate because it is not destructive.

### Staff account actions

`staff.js` will confirm every account status toggle and staff demotion before invoking the existing staff-action API. The current control is labeled only `Toggle Status`, so both enabling and disabling use the same confirmation guard. Promotion remains immediate because it is already presented through its own existing form modal and is not destructive.

### Staff approval actions

Approval and rejection forms will be intercepted before POST submission. After confirmation, the original form submission proceeds with its current method, fields, CSRF behavior, redirect, and server-side validation. Rejection without a reason remains a separate confirmation step, and the shared warning replaces its native prompt.

### Guest-request review

The reject action in the review form will use the shared warning. If no reason is entered, the reason warning is shown as part of the same guarded flow without allowing a rejected request to submit accidentally.

## Modal UX contract

The modal will follow the existing Bootstrap styling and accessibility conventions:

- Warning icon and `Confirm action` heading.
- Action-specific title and concise explanation.
- Neutral Cancel button.
- Yellow confirm styling for archive or disable.
- Red confirm styling for delete, demote, reject, or logout.
- Green confirm styling for approve.
- A disabled confirm control with `Processing…` while the continuation runs.
- Correct `role`, `aria-labelledby`, and `aria-describedby` relationships.
- Bootstrap-managed focus behavior, including safe Escape and backdrop cancellation.

Messages should include the affected book, account, or request name when that information is already available. They must not invent identifiers or other data.

## Error handling

- Cancelled actions must never call an API, submit a form, or navigate.
- Confirmed actions preserve existing error handling and user-visible messages.
- A continuation failure must restore the modal controls and allow the existing page controller to display its normal error state.
- The service must clean up stale listeners and pending state after cancel, confirm, or error.
- Native confirmation is an availability fallback only; it must not become the primary path.

## Testing and verification

Add focused frontend tests for:

- Lazy modal creation and reuse.
- Contextual title/message/button rendering.
- Confirm and cancel resolution.
- Escape/backdrop/close cancellation.
- Duplicate-click and duplicate-submission prevention.
- Focus restoration where supported by the test DOM.
- Fallback behavior when Bootstrap is unavailable.
- Logout interception.
- Dynamic inventory single and bulk archive/delete actions.
- Staff disabling and demotion.
- Approval/rejection and reasonless-rejection flows.
- Absence of targeted native confirmation calls after migration.

Run the focused frontend tests, the full frontend test suite, relevant PHP feature/parity tests, and `git diff --check` before reporting completion.

## Compatibility and rollback

The feature is frontend-only and can be rolled back by removing the shared script inclusion and integration calls. Existing backend contracts and page routes remain unchanged. Existing non-destructive modals remain independent and can continue to use Bootstrap directly.

## Success criteria

- All in-scope destructive and high-impact actions show one consistent reusable warning modal.
- Cancel never performs the requested action.
- Confirm performs the same existing action exactly once.
- Dynamic content and the injected logout link remain covered after rerenders.
- Ordinary non-destructive workflows do not show warnings.
- Existing page markup contracts, API payloads, messages, routes, and tests remain compatible.
- Focused and regression tests pass.
