# Staff Management Surface Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the administrator staff-management page so its feedback, account actions, and role-assignment workflow are clear without changing behavior.

**Architecture:** Keep `AdminStaffPage` responsible for data rendering and existing actions. Move page-specific presentation into a new scoped stylesheet and add stable semantic hooks to the HTML/rendered rows. Feedback alerts remain the existing `.alert.alert-success` and `.alert.alert-danger` nodes, but start hidden and are shown only by action results.

**Tech Stack:** Existing vanilla ES modules, Bootstrap 5.3 utility classes, scoped CSS, Node native tests, PHPUnit markup contracts.

## Global Constraints

- Preserve existing selectors, modal IDs, service calls, confirmation metadata, and role-management behavior.
- Preserve profile-change request rendering and review behavior.
- Use real product labels and account data; do not add filler copy or fabricated metrics.
- Run targeted checks first, then the complete frontend suite and relevant backend markup/contract tests.

---

### Task 1: Add regression coverage for the staff-management surface

**Files:**
- Modify: `frontend/tests/staff-utils.test.js`
- Modify: `backend/tests/Feature/StaffDashboardMarkupTest.php`

**Interfaces:**
- Consumes the existing `AdminStaffPage` rendering contract and canonical `admin-staff.html` fixture.
- Produces assertions for hidden feedback alerts, page stylesheet ownership, and stable action hooks.

- [ ] **Step 1: Write the failing frontend contract tests**

Add tests that read `features/staff/pages/admin-staff/admin-staff.html` and assert:

```js
assert.match(template, /class="alert alert-success d-none staff-management__feedback/);
assert.match(template, /class="alert alert-danger d-none staff-management__feedback/);
assert.match(template, /staff-management\.css/);
assert.match(template, /staff-management__section/);
```

Also assert `AdminStaffPage.prototype.staffRows` emits `staff-management__actions` and `staff-management__status` hooks for populated rows.

- [ ] **Step 2: Run the focused frontend test file**

Run: `node --test frontend/tests/staff-utils.test.js`

Expected: the new assertions fail because the current template has blank visible alerts and no scoped staff-management presentation hooks.

- [ ] **Step 3: Add the backend markup contract**

Extend `StaffDashboardMarkupTest::testCanonicalAdminStaffPageUsesFeatureEntry()` to require `staff-management.css`, `staff-management__section`, and the hidden feedback alert classes.

- [ ] **Step 4: Run the focused backend markup tests**

Run: `& 'C:\xampp\php\php.exe' backend/vendor/bin/phpunit backend/tests/Feature/StaffDashboardMarkupTest.php`

Expected: the new assertions fail for the same missing markup.

### Task 2: Implement the scoped staff-management redesign

**Files:**
- Modify: `frontend/features/staff/pages/admin-staff/admin-staff.html`
- Modify: `frontend/features/staff/pages/admin-staff/admin-staff.page.js`
- Create: `frontend/assets/css/staff-management.css`

**Interfaces:**
- Keeps `AdminStaffPage.list`, `AdminStaffPage.action`, `promoteModal`, `pwModal`, profile-change hooks, and existing data attributes unchanged.
- Produces `staff-management__section`, `staff-management__feedback`, `staff-management__status`, and `staff-management__actions` presentation hooks.

- [ ] **Step 1: Hide and label feedback alerts in the template**

Change the two blank alerts to:

```html
<div class="alert alert-success d-none staff-management__feedback" role="status" aria-live="polite"></div>
<div class="alert alert-danger d-none staff-management__feedback" role="alert" aria-live="assertive"></div>
```

Link `/scan2borrow/frontend/assets/css/staff-management.css` after the shared stylesheet.

- [ ] **Step 2: Add semantic section and action hooks**

Add `staff-management__section` to the staff-account and role-assignment cards, add a concise section metadata row, and preserve the current form/table IDs and modal targets. Keep the explanatory role-assignment copy, but make the workflow boundary explicit with a `staff-management__workflow-note` hook.

- [ ] **Step 3: Update rendered rows without changing actions**

In `staffRows`, preserve each existing `data-*` attribute and button action, while adding:

```js
<span class="staff-management__status staff-management__status--${this.escape(String(row.status || '').toLowerCase())}">${this.escape(row.status || '')}</span>
<div class="staff-management__actions">...</div>
```

In `borrowerRows`, preserve `data-promote-user`, `data-name`, and modal attributes, while adding `staff-management__promote-action` to the button.

- [ ] **Step 4: Make feedback mutually exclusive and dismissible by state**

Add a private helper to `AdminStaffPage`:

```js
showFeedback(kind, message) {
  const success = this.root.querySelector?.('.alert.alert-success');
  const error = this.root.querySelector?.('.alert.alert-danger');
  [success, error].forEach((node) => node?.classList.add('d-none'));
  const target = kind === 'success' ? success : error;
  if (!target) return;
  target.textContent = message;
  target.classList.remove('d-none');
}
```

Use it in `action()` for success and failure; do not alter the API call or reload behavior. Profile-request load errors continue to use the error alert through the same helper.

- [ ] **Step 5: Add the Swiss-style scoped stylesheet**

Create `staff-management.css` with white/neutral surfaces, navy text, blue accent, 1px rules, compact table spacing, readable status pills, and grouped action buttons. Scope every selector under `.staff-management-page` or `.staff-management` so shared staff pages are unaffected. Do not introduce new fonts, gradients, decorative icons, or permanent red/green blocks.

- [ ] **Step 6: Run the focused tests**

Run:

```powershell
node --test frontend/tests/staff-utils.test.js
& 'C:\xampp\php\php.exe' backend/vendor/bin/phpunit backend/tests/Feature/StaffDashboardMarkupTest.php
```

Expected: all focused tests pass.

### Task 3: Verify the complete surface

**Files:**
- None beyond Task 1 and Task 2.

- [ ] **Step 1: Run the full frontend suite**

Run: `npm.cmd test`

Expected: all frontend tests pass with zero failures.

- [ ] **Step 2: Run relevant backend contracts**

Run:

```powershell
& 'C:\xampp\php\php.exe' backend/vendor/bin/phpunit backend/tests/Feature/StaffDashboardMarkupTest.php backend/tests/Feature/StaffDashboardFrontendContractTest.php
```

Expected: all relevant backend tests pass.

- [ ] **Step 3: Inspect the served page**

Open `http://localhost/scan2borrow/admin/staff`, hard-refresh, and verify that no blank red/green bars appear above the staff accounts table. Trigger a successful and failed staff action to verify only the relevant compact feedback alert appears.

- [ ] **Step 4: Review the final diff**

Run: `git diff --check; git status --short`

Confirm only the approved staff-management files and the plan/spec documents are included; preserve unrelated user files.
