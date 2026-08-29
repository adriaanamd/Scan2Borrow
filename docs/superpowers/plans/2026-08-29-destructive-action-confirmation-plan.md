# Destructive Action Confirmation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add one reusable Bootstrap confirmation layer that guards every approved destructive or high-impact frontend action without changing existing API or navigation behavior.

**Architecture:** Create a shared frontend-core service at `frontend/assets/js/core/confirmation.js`. It lazily creates and reuses one Bootstrap modal, exposes `Scan2BorrowConfirmation.confirm(options): Promise<boolean>`, and installs delegated click/submit guards for marked links and forms. Page controllers call the same service before API continuations, so dynamically rendered inventory, staff, and approval controls use the same warning behavior.

**Tech Stack:** Vanilla JavaScript, Bootstrap 5.3.3 modal API, static HTML templates, Node.js built-in test runner, existing frontend controllers and PHP parity tests.

## Global Constraints

- Remain framework-free: vanilla HTML, CSS, and JavaScript only.
- Preserve existing clean URLs, redirects, session behavior, CSRF behavior, API payloads, response shapes, and user-facing messages.
- Preserve existing UI/UX: layout, typography, colors, spacing, responsive behavior, Bootstrap integration, copy, icons, forms, modals, drawers, alerts, toasts, loading states, empty states, printing, camera, barcode, and upload flows.
- Preserve existing DOM IDs, form names, query parameters, `data-*` attributes, and accessibility affordances wherever current behavior or tests depend on them.
- No backend, database, route, API, or authorization changes.
- Confirm logout, archive, permanent delete, account status toggle, demotion, approval, rejection, and rejection without a reason.
- Do not confirm navigation, search/filtering, viewing/opening modals, add/edit/save, restore, or ordinary borrow/return submission.
- Use existing Bootstrap 5.3.3; do not add a dependency or replace existing modals.

---

### Task 1: Build the shared confirmation service

**Files:**
- Create: `frontend/assets/js/core/confirmation.js`
- Create: `frontend/tests/confirmation.test.js`

**Interfaces:**
- Produces `window.Scan2BorrowConfirmation.confirm(options): Promise<boolean>`.
- `options` contains `title`, `message`, `confirmLabel`, `confirmClass`, optional `trigger`, and `onConfirm`.
- Produces delegated handling for `.nav-logout` links and elements/forms with `data-confirm-action`.

- [ ] **Step 1: Write failing tests**

Use Node’s built-in test runner and a minimal fake document/window. Cover public behavior:

```js
test('cancel resolves false and never calls the continuation', async () => {
  const { service, modal } = createConfirmationFixture();
  let called = false;
  const result = service.confirm({
    title: 'Delete book',
    message: 'This cannot be undone.',
    onConfirm: () => { called = true; },
  });
  modal.cancel();
  assert.equal(await result, false);
  assert.equal(called, false);
});

test('confirm renders context and executes once', async () => {
  const { service, modal } = createConfirmationFixture();
  let calls = 0;
  const result = service.confirm({
    title: 'Archive Clean Code',
    message: 'The book leaves the active catalog.',
    confirmLabel: 'Archive',
    confirmClass: 'btn-warning',
    onConfirm: () => { calls += 1; },
  });
  assert.equal(modal.title.textContent, 'Archive Clean Code');
  assert.equal(modal.message.textContent, 'The book leaves the active catalog.');
  modal.confirm.click();
  modal.confirm.click();
  assert.equal(await result, true);
  assert.equal(calls, 1);
});

test('Bootstrap absence falls back to native confirmation', async () => {
  const { service, window } = createConfirmationFixture({
    bootstrap: null,
    nativeResult: false,
  });
  let called = false;
  assert.equal(await service.confirm({
    message: 'Leave the session?',
    onConfirm: () => { called = true; },
  }), false);
  assert.deepEqual(window.confirmCalls, ['Leave the session?']);
  assert.equal(called, false);
});
```

`createConfirmationFixture()` must expose the generated modal’s title, message, confirm control, and cancel operation without requiring a browser or third-party test package.

- [ ] **Step 2: Run tests and verify the expected failure**

```powershell
node --test frontend/tests/confirmation.test.js
```

Expected: FAIL because the service does not exist.

- [ ] **Step 3: Implement the service**

Implement a `ConfirmationService` with `confirm(options)`, `ensureModal()`, `install()`, `guardLink(event)`, `guardForm(event)`, and `finish(result)`. Publish one instance as `globalThis.Scan2BorrowConfirmation` and call `install()` once.

The service must:

- Lazily create one Bootstrap modal with `role="dialog"`, `aria-modal="true"`, `aria-labelledby`, and `aria-describedby`.
- Set contextual text with `textContent`, update confirm label/class, and reuse the same modal.
- Resolve false on Cancel, close, Escape, or backdrop without calling `onConfirm`.
- Set one-shot pending state before calling `onConfirm`; disable controls and display `Processing…`.
- Await an async continuation, restore controls on rejection, and clean transient listeners/state.
- Restore focus to the original trigger when possible.
- Call `window.confirm(message)` if Bootstrap is unavailable and invoke `onConfirm` only for true.
- Handle `data-confirm-reason-selector` by showing `Reject without a reason?` before the normal rejection warning when the selected field is empty.
- Preserve form submitters and use a private one-shot bypass flag when resubmitting a confirmed form.

The delegated selectors must support this contract:

```html
<form data-confirm-action="reject"
      data-confirm-title="Reject request"
      data-confirm-message="Reject this borrow request?"
      data-confirm-label="Reject"
      data-confirm-class="btn-danger">
```

- [ ] **Step 4: Run focused tests and commit**

```powershell
node --test frontend/tests/confirmation.test.js
git add frontend/assets/js/core/confirmation.js frontend/tests/confirmation.test.js
git commit -m "feat: add reusable destructive action confirmation"
```

Expected: all service tests pass before the commit.

### Task 2: Load the service on served session pages

**Files:**
- Modify: every template matched by `rg -l 'core/app-navbar.js' frontend/features`.

This includes the current feature templates under `frontend/features/{guest,staff,student,teacher}`, including the staff dashboard, inventory, admin staff, and guest-request pages. The duplicate `frontend/pages` tree has been retired.

- [ ] **Step 1: Add the script before the navbar script**

Insert this exact tag immediately before the existing `app-navbar.js` tag in every matched template:

```html
<script src="/scan2borrow/frontend/assets/js/core/confirmation.js" defer></script>
```

Do not add it to login, registration, OTP, receipt-only, or other pages without an authenticated/guest navbar.

- [ ] **Step 2: Add and run a template regression assertion**

Extend `frontend/tests/confirmation.test.js` to read every template containing `core/app-navbar.js` and assert that the confirmation script occurs before it. Assert that auth-only pages remain without the script.

```powershell
node --test frontend/tests/confirmation.test.js
```

Expected: PASS.

- [ ] **Step 3: Commit the loading boundary**

```powershell
git add frontend/features frontend/tests/confirmation.test.js
git commit -m "feat: load confirmation guard on session pages"
```

### Task 3: Guard inventory archive and deletion

**Files:**
- Modify: `frontend/features/staff/pages/inventory/inventory.page.js`
- Modify: `frontend/tests/staff-pages.test.js`

**Interfaces:**
- Consumes `window.Scan2BorrowConfirmation.confirm(options)`.
- Preserves `doAction`, `apiPost`, toast, refresh, bulk selection, and action payload behavior.

- [ ] **Step 1: Add failing source-contract tests**

For the feature inventory controller, assert `Scan2BorrowConfirmation.confirm` is present and `window.confirm` is absent:

```js
const source = fs.readFileSync(featureInventoryPath, 'utf8');
assert.match(source, /Scan2BorrowConfirmation\.confirm/);
assert.doesNotMatch(source, /window\.confirm/);
```

- [ ] **Step 2: Run the focused test and verify failure**

```powershell
node --test frontend/tests/staff-pages.test.js
```

Expected: FAIL on the new source assertions.

- [ ] **Step 3: Replace native confirmation with the shared continuation**

Use this shape in the feature controller:

```js
doAction(action, ids, confirmation = null) {
  const execute = () => this.apiPost(action, { ids })
    .then((response) => {
      this.toast(response.message, response.ok);
      if (response.ok) this.load();
    })
    .catch(() => this.toast('Request failed.', false));

  if (!confirmation) return execute();
  return window.Scan2BorrowConfirmation.confirm({
    ...confirmation,
    onConfirm: execute,
  });
}
```

Pass warning, label, and button class for single/bulk archive and permanent delete. Keep restore immediate. Preserve all existing IDs, API payloads, success messages, and refresh behavior.

- [ ] **Step 4: Run tests and commit**

```powershell
node --test frontend/tests/staff-pages.test.js
git add frontend/features/staff/pages/inventory/inventory.page.js frontend/tests/staff-pages.test.js
git commit -m "feat: confirm inventory archive and deletion"
```

### Task 4: Guard logout and staff account changes

**Files:**
- Modify: `frontend/assets/js/core/app-navbar.js`
- Modify: `frontend/app/shared/components/app-navbar/app-navbar.component.js`
- Modify: `frontend/features/staff/pages/dashboard/staff-dashboard.page.js`
- Modify: `frontend/features/staff/pages/admin-staff/admin-staff.html`
- Modify: `frontend/tests/navbar-cache.test.js`
- Modify: `frontend/tests/staff-pages.test.js`

- [ ] **Step 1: Add failing assertions**

Assert both navbar implementations emit logout metadata, the feature staff controller calls the service before `toggle_status`/`demote`, and the canonical admin template contains no inline demotion `confirm()`.

- [ ] **Step 2: Run focused tests and verify failure**

```powershell
node --test frontend/tests/navbar-cache.test.js frontend/tests/staff-pages.test.js
```

- [ ] **Step 3: Integrate the guards**

Add these attributes to each generated logout link:

```html
data-confirm-action="logout"
data-confirm-title="Log out?"
data-confirm-message="Are you sure you want to log out?"
data-confirm-label="Log out"
data-confirm-class="btn-danger"
```

Wrap status-toggle and demotion continuations with the service. Confirm every status toggle because the existing control exposes only `Toggle Status`; confirm demotion with a red button. Remove inline demotion confirmation from the canonical admin template. Leave promotion and password-reset modal opening unchanged.

- [ ] **Step 4: Run focused tests and commit**

```powershell
node --test frontend/tests/navbar-cache.test.js frontend/tests/staff-pages.test.js
git add frontend/assets/js/core/app-navbar.js frontend/app/shared/components/app-navbar/app-navbar.component.js frontend/features/staff/pages/dashboard/staff-dashboard.page.js frontend/features/staff/pages/admin-staff/admin-staff.html frontend/tests/navbar-cache.test.js frontend/tests/staff-pages.test.js
git commit -m "feat: confirm logout and staff account changes"
```

### Task 5: Guard approval, rejection, and reasonless guest rejection

**Files:**
- Modify: `frontend/features/staff/pages/dashboard/dashboard.html`
- Modify: `frontend/features/staff/pages/guest-requests/guest-requests.html`
- Modify: `frontend/features/staff/pages/dashboard/staff-dashboard.page.js`
- Modify: `frontend/tests/staff-pages.test.js`

- [ ] **Step 1: Add failing markup/controller assertions**

Assert both canonical templates contain action metadata and no targeted inline `confirm()`; assert the feature dashboard’s `submitBorrowing` calls the shared service.

- [ ] **Step 2: Run the focused test and verify failure**

```powershell
node --test frontend/tests/staff-pages.test.js
```

- [ ] **Step 3: Add metadata and guard the feature controller**

Replace inline handlers with metadata like:

```html
<form method="POST"
      data-confirm-action="approve"
      data-confirm-title="Approve borrow request"
      data-confirm-message="Approve this borrow request?"
      data-confirm-label="Approve"
      data-confirm-class="btn-success">
```

Use red equivalent metadata for reject forms. Guest rejection also gets `data-confirm-reason-selector="#review-notes"`; the service first asks about a blank reason, then the normal rejection warning.

In `StaffDashboardPage.submitBorrowing(event)`, call `event.preventDefault()`, await the shared service with green approve or red reject copy, return on cancellation, and keep the existing API call, CSRF behavior, toast, dashboard refresh, and error handling unchanged. The API must run once only after confirmation.

- [ ] **Step 4: Run focused tests and commit**

```powershell
node --test frontend/tests/staff-pages.test.js
git add frontend/features/staff/pages/dashboard/dashboard.html frontend/features/staff/pages/guest-requests/guest-requests.html frontend/features/staff/pages/dashboard/staff-dashboard.page.js frontend/tests/staff-pages.test.js
git commit -m "feat: confirm staff approval and rejection actions"
```

### Task 6: Full verification and compatibility audit

**Files:**
- Modify: `frontend/tests/confirmation.test.js` only if a regression assertion needs correction.
- Modify: `frontend/tests/architecture.test.js` only if the shared-core contract needs explicit coverage.

- [ ] **Step 1: Scan targeted native confirmations**

```powershell
rg -n -i 'window\.confirm|onsubmit="return confirm|onclick=".*confirm' frontend/assets frontend/features
```

Expected: no targeted native confirmation remains in logout, inventory, staff-account, approval, rejection, or guest-request flows.

- [ ] **Step 2: Run the complete frontend suite**

```powershell
npm test
```

Expected: all frontend tests pass.

- [ ] **Step 3: Run relevant PHP parity tests**

```powershell
backend\vendor\bin\phpunit backend/tests/Feature/FrontendVisualSystemTest.php backend/tests/Feature/InventoryMarkupParityTest.php backend/tests/Feature/StaffDashboardMarkupTest.php backend/tests/Feature/StaffDashboardFrontendContractTest.php backend/tests/Feature/GuestMarkupParityTest.php
```

Expected: all selected parity and markup tests pass.

- [ ] **Step 4: Check diff and status**

```powershell
git diff --check
git status --short
git log -8 --oneline
```

Expected: no whitespace errors and only intentional changes.

- [ ] **Step 5: Commit only needed test corrections**

If verification requires a test-only correction, use:

```powershell
git add frontend/tests
git commit -m "test: verify destructive action confirmations"
```

Do not create an empty commit when no correction is needed.
