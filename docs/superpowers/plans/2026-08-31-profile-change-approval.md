# Profile Change Approval Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task with review checkpoints. Each task ends in one commit; preserve the 20-commit sequence.

**Goal:** Allow students and teachers to submit administrator-approved profile and photo changes from Settings, with a durable review history.

**Architecture:** Add a small profile-change domain boundary, a PDO repository that snapshots and decides requests transactionally, and a service that validates borrower input, stages photos, and sends targeted notifications. Add borrower and admin API routes, then wire role-owned Settings pages and an admin review section into the existing frontend.

**Tech Stack:** PHP 8.2/strict typed classes, PDO/MySQL with SQLite-compatible tests, PHPUnit-style repository tests, vanilla ES modules, Bootstrap markup, native Node test runner.

## Global Constraints

- Requestable fields: `firstname`, `middlename`, `lastname`, `email`, `contact_no`, `course`, `year_level`, `department`, `position`, and `photo`.
- Admin-only fields: `barcode`, `role`, `status`, `password`, and all other security/system fields.
- Only `student` and `teacher` sessions may create requests; only `admin` sessions may review them.
- One pending request per borrower; approval applies all stored changes atomically.
- Never interpolate user input into SQL; use prepared statements and allow-listed columns.
- Every PHP file declares strict types and has complete parameter/return types.
- Preserve the two existing root untracked files and do not modify unrelated worktrees.
- Each task below ends with exactly one commit. Use the listed commit message.

---

### Task 1: Add the profile field policy

**Files:**
- Create: `backend/src/Domain/Profile/ProfileFieldPolicy.php`
- Test: `backend/tests/Unit/Profile/ProfileFieldPolicyTest.php`

**Interfaces:**
- Produces `ProfileFieldPolicy::requestable(): array<string, string>` mapping external keys to SQL columns.
- Produces `ProfileFieldPolicy::adminOnly(): array<string>` and `ProfileFieldPolicy::isRequestable(string): bool`.

- [ ] Write tests asserting the ten requestable keys and that barcode, role, status, password, and unknown keys are not requestable.
- [ ] Run `C:\xampp\php\php.exe backend/vendor/bin/phpunit backend/tests/Unit/Profile/ProfileFieldPolicyTest.php` and record the expected missing-class failure if the vendor binary is unavailable.
- [ ] Implement a `final` utility with private constructor and immutable constant maps; return copies so callers cannot mutate policy state.
- [ ] Run `C:\xampp\php\php.exe -l` on both PHP files.
- [ ] Commit: `feat: define profile change field policy`.

### Task 2: Add request status and record value objects

**Files:**
- Create: `backend/src/Domain/Profile/ProfileChangeRequestStatus.php`
- Create: `backend/src/Domain/Profile/ProfileChangeRequest.php`
- Test: `backend/tests/Unit/Profile/ProfileChangeRequestTest.php`

**Interfaces:**
- `ProfileChangeRequestStatus::PENDING`, `APPROVED`, `REJECTED` with `label(): string`.
- `ProfileChangeRequest` exposes readonly `id`, `userId`, `status`, `originalValues`, `requestedValues`, `originalPhoto`, `requestedPhoto`, `requestedAt`, `reviewedAt`, `reviewedBy`, and `reviewNote`.

- [ ] Test enum conversion/labels and construction with nullable photo/reviewer fields.
- [ ] Implement typed readonly objects; normalize array PHPDoc to `array<string, string>`.
- [ ] Run targeted PHP lint/tests.
- [ ] Commit: `feat: add profile change request domain records`.

### Task 3: Add profile-change input validation

**Files:**
- Create: `backend/src/Application/Validators/ProfileChangeRequestValidator.php`
- Test: `backend/tests/Unit/Profile/ProfileChangeRequestValidatorTest.php`

**Interfaces:**
- `validate(array<string, mixed> $input, array<string, string> $current): array<string, string>` returns changed allow-listed values or throws `InvalidArgumentException` with a user-safe message.
- `validateReview(string $decision, string $note): string` accepts only `approve`/`reject` and truncates review notes to 500 characters.

- [ ] Test trimming, empty optional values, required names, email validation, per-column lengths, unknown/admin-only rejection, no-change rejection, and invalid review decisions.
- [ ] Implement all limits from `users`: names 80, course 100, year 20, email 120, contact 30, department/position 120; permit empty optional values as empty strings.
- [ ] Keep `photo_data` out of text comparison and validate only its presence/type boundary in the service.
- [ ] Run targeted tests/lint.
- [ ] Commit: `feat: validate profile change requests`.

### Task 4: Define persistence contracts

**Files:**
- Create: `backend/src/Infrastructure/Persistence/ProfileChangeRequestRepositoryInterface.php`
- Create: `backend/src/Infrastructure/Persistence/ProfileChangeNotificationInterface.php`
- Test: `backend/tests/Unit/Profile/ProfilePersistenceContractTest.php`

**Interfaces:**
- Repository methods:
  - `profile(int $userId): ?array<string, mixed>`
  - `pendingForUser(int $userId): ?array<string, mixed>`
  - `create(int $userId, array $originalValues, array $requestedValues, ?string $originalPhoto, ?string $requestedPhoto): int`
  - `pendingRequests(): list<array<string, mixed>>`
  - `decide(int $requestId, int $reviewerId, string $decision, string $reviewNote): ?array<string, mixed>`
- Notification methods: `notifyAdministrators(int $requestId, string $message): void` and `notifyBorrower(int $userId, int $requestId, string $title, string $message): void`.

- [ ] Add a reflection contract test for method names, parameter types, and return types.
- [ ] Implement interfaces only; no database behavior yet.
- [ ] Lint and commit: `feat: define profile approval persistence contracts`.

### Task 5: Add schema and upgrade migration

**Files:**
- Modify: `sql/database.sql` (drop/create ordering and table definition)
- Create: `sql/upgrade_profile_change_requests.sql`
- Modify: `backend/tests/Feature/SchemaContractTest.php`

**Interfaces:**
- Table `profile_change_requests` columns: `id`, `user_id`, `status`, `original_values`, `requested_values`, `original_photo`, `requested_photo`, `review_note`, `requested_at`, `reviewed_at`, `reviewed_by`.

- [ ] Add the table before dependent cleanup in the fresh schema with JSON fields for values, `VARCHAR(255)` photo storage references, status enum, requester/reviewer foreign keys, and indexes on `(status, requested_at)` and `(user_id, status)`.
- [ ] Add an idempotent upgrade migration using `CREATE TABLE IF NOT EXISTS`, matching the base schema and existing MySQL naming conventions.
- [ ] Extend schema tests to require the table in `database.sql`, the upgrade file, foreign keys, and all status/value columns.
- [ ] Run schema contract tests/lint.
- [ ] Commit: `feat: add profile change request schema`.

### Task 6: Implement current profile and pending-request reads

**Files:**
- Create: `backend/src/Infrastructure/Persistence/PdoProfileChangeRequestRepository.php`
- Test: `backend/tests/Unit/Infrastructure/PdoProfileChangeRequestRepositoryTest.php`

**Interfaces:**
- Implement `profile()` with `id`, `barcode`, all requestable columns, `photo`, and `role`.
- Implement `pendingForUser()` with decoded JSON values and request metadata.

- [ ] Build a SQLite fixture containing users and profile requests, then test profile isolation and JSON decoding.
- [ ] Implement prepared queries and return normalized string values; return `null` for unknown users and no pending request.
- [ ] Add a private `decodeMap(mixed): array<string, string>` helper that ignores malformed/non-string JSON entries.
- [ ] Run the repository test and PHP lint.
- [ ] Commit: `feat: read borrower profile approval state`.

### Task 7: Implement request creation persistence

**Files:**
- Modify: `backend/src/Infrastructure/Persistence/PdoProfileChangeRequestRepository.php`
- Modify: `backend/tests/Unit/Infrastructure/PdoProfileChangeRequestRepositoryTest.php`

**Interfaces:**
- `create()` inserts one pending row and returns its integer ID.

- [ ] Test that original/requested maps and both photo references round-trip, and that a pending request is rejected by the service-facing repository guard.
- [ ] Implement an explicit pending existence query inside a transaction before insert; throw `RuntimeException('A profile change request is already pending.')` when found.
- [ ] Bind JSON using `json_encode(..., JSON_THROW_ON_ERROR)` and photo references as nullable strings.
- [ ] Run tests/lint and commit: `feat: persist pending profile changes`.

### Task 8: Implement transactional admin decisions

**Files:**
- Modify: `backend/src/Infrastructure/Persistence/PdoProfileChangeRequestRepository.php`
- Modify: `backend/tests/Unit/Infrastructure/PdoProfileChangeRequestRepositoryTest.php`

**Interfaces:**
- `decide()` returns the decided request payload or `null` when the request is missing/already decided.

- [ ] Test approval updates only allow-listed user columns and photo, marks the request approved, and records reviewer/time; test rejection leaves users unchanged; test a second decision returns `null`.
- [ ] Implement a transaction: select pending request, decode stored maps, update each known column with a fixed SQL map, update request with `WHERE status = 'pending'`, commit, and return requester/requested data.
- [ ] Recheck the requester role is `student` or `teacher` and status is `active`; otherwise roll back and throw a safe runtime exception.
- [ ] Run tests/lint and commit: `feat: apply profile approvals atomically`.

### Task 9: Add admin-only notification persistence

**Files:**
- Create: `backend/src/Infrastructure/Persistence/PdoProfileChangeNotificationRepository.php`
- Test: `backend/tests/Unit/Infrastructure/PdoProfileChangeNotificationRepositoryTest.php`

**Interfaces:**
- Implement `ProfileChangeNotificationInterface` using the existing `notifications` table.

- [ ] Test admin-only recipient selection for submissions, borrower recipient selection for decisions, type/title/message/related ID, and no SQL interpolation.
- [ ] Implement prepared `INSERT ... SELECT` for `role = 'admin' AND status = 'active'`; use `type = 'profile_change_request'` and request ID as `related_id`.
- [ ] Run tests/lint and commit: `feat: notify profile change reviewers`.

### Task 10: Add profile-change application service

**Files:**
- Create: `backend/src/Application/Services/ProfileChangeRequestService.php`
- Test: `backend/tests/Unit/Profile/ProfileChangeRequestServiceTest.php`

**Interfaces:**
- `show(int $userId): array<string, mixed>` returns `profile`, `requestable_fields`, and `pending_request`.
- `submit(int $userId, array<string, mixed> $input): int` creates a request.
- `decide(int $requestId, int $reviewerId, string $decision, string $note): array<string, mixed>` returns the decision result.

- [ ] Test service diffing, photo staging via `PhotoStorageInterface`, notification calls, duplicate pending handling, and non-fatal notification exceptions after persistence.
- [ ] Implement current profile snapshot, validator diff, optional `photo_data` staging with filename seed `profile-request-{userId}`, repository calls, and borrower/admin notification messages.
- [ ] Keep the active photo unchanged until approval; store staged path only in the request row.
- [ ] Run tests/lint and commit: `feat: orchestrate profile change approvals`.

### Task 11: Add borrower profile API controller and routes

**Files:**
- Create: `backend/src/Http/Controllers/ProfileChangeRequestController.php`
- Modify: `backend/src/Http/Routing/BorrowerRouteTable.php`
- Test: `backend/tests/Feature/ProfileChangeRequestControllerTest.php`

**Interfaces:**
- Controller methods: `show(ServerRequest): JsonResponse`, `submit(ServerRequest): JsonResponse`.
- Routes: `GET/POST /api/student/settings` and `GET/POST /api/teacher/settings`.

- [ ] Test student/teacher access, cross-role rejection, CSRF failure, successful submission, and validation error envelopes.
- [ ] Implement role detection from `SessionService`, use request path to preserve role-specific endpoints, and return `401`, `419`, `422`, or `200` in existing envelope style.
- [ ] Run feature tests/lint and commit: `feat: expose borrower profile approval API`.

### Task 12: Add admin review API to the staff controller

**Files:**
- Modify: `backend/src/Http/Controllers/StaffController.php`
- Modify: `backend/src/Http/Routing/StaffRouteTable.php`
- Test: `backend/tests/Unit/StaffProfileChangeControllerTest.php`

**Interfaces:**
- `profileChangeRequests(ServerRequest): JsonResponse` returns pending rows.
- `profileChangeRequestAction(ServerRequest): JsonResponse` performs approve/reject.
- Routes: `GET /api/admin/profile-change-requests`, `POST /api/admin/profile-change-request-action`.

- [ ] Test unauthenticated/librarian rejection, admin listing, CSRF enforcement, valid decisions, missing request, and review-note forwarding.
- [ ] Inject the profile service into `StaffController` as a nullable dependency only where needed to preserve existing unit-test construction, then enforce admin role before use.
- [ ] Run tests/lint and commit: `feat: add admin profile approval endpoints`.

### Task 13: Wire production dependencies and API documentation

**Files:**
- Modify: `backend/src/Bootstrap/ApplicationFactory.php`
- Modify: `backend/src/Http/Documentation/ApiEndpointCatalog.php`
- Modify: `backend/tests/Unit/Documentation/ApiEndpointCatalogTest.php`
- Modify: `backend/tests/Feature/ApiDocumentationControllerTest.php`

**Interfaces:**
- Construct one PDO profile repository, notification repository, photo storage, service, and inject them into borrower/staff controllers.

- [ ] Add six catalog entries for the role-specific borrower GET/POST pairs and admin GET/POST pair; update expected endpoint count from 54 to 60.
- [ ] Wire `LocalPhotoStorage` with the existing uploads path and `/scan2borrow/uploads` prefix.
- [ ] Run documentation tests and PHP lint; commit: `feat: wire profile approval services`.

### Task 14: Add borrower profile model and service contracts

**Files:**
- Create: `frontend/features/student/models/profile-change.model.js`
- Create: `frontend/features/student/services/profile-change.service.js`
- Create: `frontend/features/teacher/models/profile-change.model.js`
- Create: `frontend/features/teacher/services/profile-change.service.js`
- Modify: `frontend/features/student/services/settings.service.js`
- Modify: `frontend/features/teacher/services/settings.service.js`
- Test: `frontend/tests/profile-change-services.test.js`

**Interfaces:**
- Services expose `load()` and `submit(formData)` against role-specific `/api/{role}/settings` paths.
- Models normalize absent values to empty strings and preserve `pending_request` without inventing data.

- [ ] Test exact GET/POST paths, FormData preservation, and role-specific normalization.
- [ ] Implement small role wrappers over the existing `ApiClient`; do not duplicate fetch/CSRF logic.
- [ ] Run `npm test -- frontend/tests/profile-change-services.test.js` and commit: `feat: add borrower profile approval clients`.

### Task 15: Convert student Settings to a request form

**Files:**
- Modify: `frontend/features/student/pages/settings/settings.html`
- Modify: `frontend/features/student/pages/settings/student-settings.page.js`
- Test: `frontend/tests/student-settings-approval.test.js`

**Interfaces:**
- `StudentSettingsPage` exposes `load`, `render`, `submit`, `renderRequestStatus`, and `escapeHtml`.

- [ ] Test editable IDs, read-only barcode, pending submit disablement, successful reload/toast, and photo preview/file selection.
- [ ] Replace the read-only notice with requestable fields, file input, current-photo preview, status panel, and submit button; leave barcode read-only.
- [ ] Implement API loading/submission through the new service, `FormData`, safe text rendering, and existing confirmation/toast conventions.
- [ ] Run targeted Node tests and commit: `feat: enable student profile change requests`.

### Task 16: Convert teacher Settings to a request form

**Files:**
- Modify: `frontend/features/teacher/pages/settings/settings.html`
- Modify: `frontend/features/teacher/pages/settings/teacher-settings.page.js`
- Test: `frontend/tests/teacher-settings-approval.test.js`

**Interfaces:**
- `TeacherSettingsPage` mirrors the student page API but uses teacher field labels and `/api/teacher/settings`.

- [ ] Test teacher-only endpoint, role label, editable fields, read-only Staff ID, pending disablement, and photo selection.
- [ ] Implement a teacher-owned template/controller; do not reuse student DOM IDs or endpoint strings.
- [ ] Run targeted Node tests and commit: `feat: enable teacher profile change requests`.

### Task 17: Add shared settings approval styling

**Files:**
- Create: `frontend/features/student/pages/settings/settings.css`
- Create: `frontend/features/teacher/pages/settings/settings.css`
- Modify: both settings HTML files to load their scoped stylesheet
- Test: `backend/tests/Feature/FrontendSettingsApprovalContractTest.php`

**Interfaces:**
- Styles expose `.profile-request-shell`, `.profile-request-status`, `.profile-request-preview`, `.profile-request-diff`, and responsive mobile rules.

- [ ] Test styles stay scoped to settings pages, include white surface/blue accent/hairline comparison hooks, and define focus/reduced-motion behavior.
- [ ] Implement the approved Swiss direction: white canvas, left-aligned field grid, thin rules, blue action accent, clear before/after panels, and no fabricated values.
- [ ] Run Node/PHP contract tests and commit: `feat: style borrower profile approval surfaces`.

### Task 18: Add admin request service and review UI

**Files:**
- Create: `frontend/features/staff/services/profile-change-request.service.js`
- Modify: `frontend/features/staff/services/index.js`
- Modify: `frontend/features/staff/pages/admin-staff/admin-staff.page.js`
- Modify: `frontend/features/staff/pages/admin-staff/admin-staff.html`
- Test: `frontend/tests/admin-profile-change-requests.test.js`

**Interfaces:**
- Service methods: `list()` and `action(action, requestId, reviewNote)`.
- `AdminStaffPage` exposes `profileChangeRows`, `profileChangeDetail`, `bindProfileChangeActions`, and `decideProfileChange`.

- [ ] Test exact admin API payloads, safe before/after rendering, photo preview, approve/reject confirmation metadata, and note submission.
- [ ] Add a third admin-only table/card section with pending rows, detail modal, field comparison, and review-note input; preserve staff-account table selectors.
- [ ] Use `Scan2BorrowConfirmation.confirm` and reload the admin data after a successful decision.
- [ ] Run targeted Node tests and commit: `feat: add admin profile approval queue`.

### Task 19: Integrate notifications and frontend regression contracts

**Files:**
- Modify: `frontend/features/staff/pages/admin-staff/admin-staff.html`
- Modify: `frontend/features/staff/pages/admin-staff/admin-staff.page.js`
- Modify: `frontend/tests/staff-pages.test.js`
- Modify: `backend/tests/Feature/CleanRouteMatrixTest.php`

**Interfaces:**
- Admin UI displays empty/loading/error states and notification-linked request IDs without changing existing staff page behavior.

- [ ] Add regression assertions for admin-only profile queue, settings links, role boundaries, and no native `confirm()` calls.
- [ ] Ensure the queue is empty-safe, action errors are rendered in the existing danger alert, and all dynamic content passes `escape`.
- [ ] Run the full `npm test` suite and commit: `test: cover profile approval UI integration`.

### Task 20: Final verification and handoff documentation

**Files:**
- Modify: `README.md` with the new migration command and approval workflow note
- Modify: `backend/tests/Feature/PageRouteTableTest.php` only if route assertions need the existing settings coverage extended
- Modify: `backend/tests/Feature/SchemaContractTest.php` only if final migration ordering assertions need tightening

**Interfaces:**
- No new runtime interfaces; this task closes verification gaps and documents deployment order.

- [ ] Run `npm test` and require all tests to pass.
- [ ] Run `C:\xampp\php\php.exe -l` for every PHP file under `backend/src` and `backend/tests`; run PHPStan/PHPUnit if binaries exist and record unavailable-tool limitations otherwise.
- [ ] Run `git diff --check`, inspect `git status`, and verify the two preserved root untracked files were not changed.
- [ ] Update README migration order with `sql/upgrade_profile_change_requests.sql`, then commit: `docs: document profile approval rollout`.

## Execution Notes

Implement with test-first cycles. After each commit, run the narrowest relevant test command before moving to the next task. The final branch must contain the design commit, this plan commit, and exactly the 20 implementation commits listed above; do not squash them.
