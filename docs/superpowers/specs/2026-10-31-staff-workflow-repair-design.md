# Staff Workflow Repair Design

## Scope

Repair the staff circulation workflow requested by the owner:

1. Make the desk checkout and borrowing-approval controls reliably submit through the circulation UI.
2. Make the borrower notification page's email and SMS buttons call the notification API and show clear results.
3. Send SMS notifications through the configured SMS adapter instead of returning an unconditional "not enabled" response.
4. Show an approved borrower profile photo in circulation and dashboard borrowing cards, matching the existing admin borrower detail behavior.

The existing profile approval path already persists the approved image to `users.photo`. The defect is in the downstream query/rendering surfaces, not the approval write itself.

## Decisions

- Preserve the existing circulation API and confirmation flow. Add regression coverage around the actual form/click paths and only harden the page wiring where the tests expose a gap.
- Keep email behavior unchanged. Inject the SMS sender into `BorrowerNotificationService` and use the existing `SmsSenderFactory` so provider configuration remains centralized.
- Return user-safe failure messages when a borrower has no phone number or the configured SMS provider fails.
- Select the borrower photo alongside pending borrowing rows. Keep the book-cover image as the existing card artwork and add a separate borrower avatar.
- Resolve photo URLs through the existing shared media helper in browser pages, with a local fallback for unit tests.
- Do not modify unrelated existing worktree changes in `frontend/features/staff/services/index.js`, `frontend/tests/scanner.test.js`, `EXAMPLE.jpg`, or `uploads/`.

## Verification

- Frontend: `npm.cmd test`.
- Targeted PHP tests for notification, staff repository, circulation repository, and circulation controller.
- Backend static analysis: `backend/vendor/bin/phpstan analyse --configuration=backend/phpstan.neon` (or the repository's Composer wrapper).
- Full backend quality is also rerun before handoff; any pre-existing failures are reported separately from this change.
