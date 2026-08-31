# Profile Change Approval Design

**Date:** 2026-08-31  
**Status:** Approved

## Goal

Let authenticated students and teachers request updates to their own profile information from Settings while keeping account identity and security controls under administrator control.

## Scope

Requestable fields are:

- first name, middle name, and last name
- email address and contact number
- course and year level
- department and position
- profile photo

The following remain administrator-only and are never accepted from a borrower request: barcode/ID, role, account status, password, and other security or system fields.

The feature is limited to the staff panel plus the student and teacher Settings pages. Guest profile editing and staff-account editing are out of scope.

## Recommended approach

Use one atomic profile-change request per submission. The request stores the submitted values and the original profile snapshot, then waits for an administrator decision. Approval updates every requested field in one transaction; rejection leaves the user unchanged. A borrower may not create another request while one is pending.

This approach was chosen over field-by-field approvals because it prevents partially applied identity changes and gives administrators one clear before/after decision. Direct edits with an audit log would not satisfy the approval requirement.

## Data model

Add `profile_change_requests` in the base schema and a standalone upgrade migration. Each row contains:

- `id`, `user_id`, and `status` (`pending`, `approved`, or `rejected`)
- `original_values` JSON containing the values shown when the request was submitted
- `requested_values` JSON containing only changed requestable fields
- `original_photo` and `requested_photo` storage references
- optional administrator `review_note`
- `requested_at`, `reviewed_at`, and `reviewed_by`

The record is the business audit trail for the request and decision. It is never deleted by the application. A transaction and a conditional `status = 'pending'` update prevent duplicate decisions and stale approvals. Foreign keys link the requester and reviewer to `users`, with reviewer deletion retaining the request history through `SET NULL`.

Photos use the existing local photo-storage contract. The submitted image is staged under a request-specific filename; only approval makes it the user's active photo. Rejected requests do not change the active photo.

## Backend flow

Borrower endpoints are role-specific to match the existing route conventions:

- `GET /api/student/settings` and `GET /api/teacher/settings` return the current editable profile, protected fields for display, and the latest request status.
- `POST /api/student/settings` and `POST /api/teacher/settings` validate the CSRF token, normalize the allow-listed fields, validate photo type/size through the existing storage boundary, and create a pending request.

Admin endpoints are protected separately:

- `GET /api/admin/profile-change-requests` lists pending requests and includes the requester's display data plus original/requested values.
- `POST /api/admin/profile-change-request-action` accepts `approve` or `reject`, a request ID, and an optional review note.

Only the `admin` role may access the review endpoints. The request creator may only operate on their own profile. Validation rejects unknown fields, empty required names, oversized values, invalid email addresses, and submissions with no actual change. The API returns safe, user-facing error envelopes consistent with existing controllers.

On submission, active administrators receive an existing notification with the request ID. On approval or rejection, the requester receives an existing notification containing the decision and reviewer note when present.

## Frontend flow

Student and teacher Settings pages become editable forms while retaining a read-only ID/barcode block. The form includes:

- editable profile fields listed in Scope
- a photo preview and file picker
- a clear explanation that changes are pending administrator review
- a request button disabled while a request is pending
- a request status card showing submitted time, requested values, and the latest decision

The student and teacher controllers share behavior through role-specific services but keep their feature-owned templates. Submissions use the existing API/CSRF conventions, safe text rendering, and the existing toast/confirmation utilities.

The admin staff page gains a Profile Change Requests section. Each row shows requester, role, submitted time, changed fields, and request status. Opening a request exposes a field-by-field before/after comparison and photo preview. Approve and reject actions use the shared confirmation service; rejection includes an optional note. The section is not rendered for librarians because the page itself is admin-protected.

The settings surface uses the approved Swiss direction inside the existing application shell: white content canvas, left-aligned typography, thin grid rules, one blue action accent, and a visible before/after comparison as the signature interaction. No fabricated profile values are shown when data is absent.

## Errors and concurrency

- Missing or expired sessions return `401`.
- Invalid CSRF tokens return `419`.
- Invalid fields, invalid images, duplicate pending requests, and empty changes return `422`.
- Missing requests return `404`.
- A second administrator decision against an already-reviewed request returns a conflict-style `422` without changing the user.
- Approval rechecks that the requester is still an active student or teacher and applies only the stored allow-listed values.
- Notification failures do not roll back a successfully persisted request decision; the request record remains authoritative.

## Testing

Backend tests will cover field allow-listing and validation, borrower isolation, pending-request rules, admin-only access, atomic approval/rejection, photo staging, and notification payloads. Schema contract tests will verify both the fresh schema and upgrade migration. Frontend tests will cover settings service payloads, form/pending-state behavior, safe before/after rendering, photo selection, admin request actions, and role boundaries.

Verification will run the repository's `npm test` suite and PHP lint across source and test files. If PHPStan/PHPUnit binaries are unavailable in the local checkout, that limitation will be reported with the available verification results.
