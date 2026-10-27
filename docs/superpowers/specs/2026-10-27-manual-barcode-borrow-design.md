# Manual Barcode Entry for Student and Teacher Borrowing

## Purpose

Students and teachers must be able to borrow a book by typing or pasting its physical-copy barcode as well as by scanning it with a camera. The text-entry path must remain available when the camera is unavailable, inconvenient, or not desired.

## Context

The backend already exposes role-specific barcode lookup routes and accepts the resulting copy barcode in the existing bulk-borrow payload. The student borrow modal already keeps its text input separate from the scanner trigger. The teacher modal currently places `data-scan-target` on the text input itself, which lets the global scanner click handler intercept clicks intended for typing.

## Chosen approach

Keep the existing lookup, cart, and borrow-request architecture and make the input actions explicit in both role surfaces:

- The barcode input is a normal text input with no scanner target.
- `Add copy` performs the existing lookup-and-add path.
- Enter in the input performs the same add action.
- A separate `Scan with camera` button carries `data-scan-target="bulk-scan-barcode"`.
- A successful scan fills the input and returns focus to it; the user then confirms with `Add copy`.

This is intentionally a markup-level fix. A shared component or mode toggle would add refactoring and state without improving the requested behavior.

## Architecture and data flow

The student dashboard continues to use `StudentDashboardPage.lookupAndAdd()` and the student lookup route. The teacher catalog continues to use `BorrowerSearchPage.lookupAndAdd()` and the teacher lookup route. Both flows continue to add the resolved copy to `BulkBorrowCart`, render the cart, and submit the existing `items[...][title_id]`, `quantity`, and `barcodes[]` fields with CSRF protection.

The global scanner helper remains unchanged. It is activated only through the dedicated camera button and does not need to know whether the borrower is a student or teacher.

## UI and accessibility

Preserve the current role-specific modal styling, spacing, and button classes. The visible differentiator is the clear split between manual entry and camera scanning, not a new visual theme. Use explicit button labels and accessible `aria-label` text for the camera controls. Preserve existing autofocus/focus-return behavior, Enter handling, autocomplete settings, and current error surfaces.

## Error handling

- Empty manual input continues to be ignored by the existing lookup handler.
- Unknown, unavailable, duplicate, or invalid barcodes continue to use the existing lookup error message and role-specific error host.
- Camera permission or library failures continue to use the global scanner toast.
- Borrow submission, CSRF validation, approval status, and server-side capacity rules are unchanged.

## Verification

Add frontend contract assertions for both student and teacher borrow modals confirming:

1. The text input does not carry `data-scan-target`.
2. A separate camera button targets the text input.
3. The manual `Add copy` button and Enter handler remain present.
4. The existing role-specific lookup and borrow API paths remain unchanged.

Run the focused frontend tests first, then `npm test`, then the repository CI-equivalent backend quality command from `backend`:

```text
composer run quality
```

The GitHub Actions workflow remains the completion gate; no workflow or dependency changes are part of this feature.

## Scope exclusions

- No new API endpoint.
- No database or migration changes.
- No changes to scanner library loading or camera permissions.
- No changes to guest, staff, return, reservation, or approval flows.
