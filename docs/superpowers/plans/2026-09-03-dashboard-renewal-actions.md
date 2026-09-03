# Dashboard Renewal Actions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (\`- [ ]\`) syntax for tracking.

**Goal:** Move student and teacher borrower renewal requests from the standalone dashboard Renewals section into each My Books row's Actions column with a shared renewal modal.

**Architecture:** Keep \`RenewalService\` and the staff approval page unchanged. Replace the dashboard-only \`RenewalPanelComponent\` with a shared \`RenewalModalComponent\`; each borrower dashboard will load renewal records, render receipt/renewal actions beside the matching loan, and open the modal for the selected loan.

**Tech Stack:** Vanilla ES modules, Bootstrap 5 modal API, existing student Organic and teacher Swiss CSS scopes, Node's built-in test runner, PHPUnit renewal contract tests.

## Global Constraints

- Remove the standalone dashboard Renewals section and its dashboard mount from both student and teacher pages.
- My Books Actions contains \`View receipt\` and \`Renew\` for each borrowable loan.
- Renewal opens a modal with the selected title, due date, optional reason, and \`Request +7 days\`.
- Keep the existing role-specific renewal endpoints and \`{ok, data, errors}\` response shape.
- A matching pending or approved renewal renders its status instead of a duplicate request control.
- Pending and overdue loans retain explanatory non-action states.
- Student My Books remains Organic and teacher My Books remains Swiss.
- Escape borrower values before HTML insertion.
- Disable the modal submit button during the request and reload dashboard data after success.
- Do not change renewal eligibility, the +7-day extension, the renewal schema, staff approval, or receipt generation.

---

### Task 1: Add failing frontend contracts for row actions and the modal

**Files:**
- Modify: \`frontend/tests/borrower-renewals.test.js\`
- Modify: \`frontend/tests/borrower-dashboard-redesign.test.js:150-170,238-250\`

**Interfaces:**
- Consumes: existing \`RenewalService\` and the desired \`RenewalModalComponent.open(loan)\` / \`handleSubmit(event)\` interface.
- Produces: failing assertions for removal of the old section, Actions markup, modal contents, request payload, and status states.

- [ ] **Step 1: Replace standalone-panel tests with modal/action tests**

Change the import in \`frontend/tests/borrower-renewals.test.js\` to \`RenewalModalComponent\`. Add these tests before the implementation exists:

~~~js
import { RenewalModalComponent } from '../app/shared/components/renewal-modal/renewal-modal.component.js';

test('renewal modal renders a selected loan and submits the existing payload', async () => {
  const root = { innerHTML: '', addEventListener() {} };
  const calls = [];
  let changed = 0;
  const submitButton = { disabled: false };
  const form = {
    elements: {
      loan_id: { value: '88' },
      reason: { value: 'Project deadline' },
    },
    querySelector: () => submitButton,
  };
  const modal = new RenewalModalComponent(root, {
    service: {
      request: async (loanId, reason) => calls.push([loanId, reason]),
    },
    onChanged: () => { changed += 1; },
    contentClass: 'student-dashboard__modal',
    headerClass: 'student-dashboard__modal-header',
  });

  modal.open({ id: 88, title: 'Clean Code', due_date: '2026-08-30' });
  assert.match(root.innerHTML, /Clean Code/);
  assert.match(root.innerHTML, /Due 2026-08-30/);
  assert.match(root.innerHTML, /name="reason"/);
  assert.match(root.innerHTML, /Request \+7 days/);

  await modal.handleSubmit({
    target: { closest: () => form },
    preventDefault() {},
  });

  assert.deepEqual(calls, [['88', 'Project deadline']]);
  assert.equal(changed, 1);
  assert.equal(submitButton.disabled, true);
});

test('borrower dashboards place renewal beside the receipt in My Books Actions', () => {
  for (const page of [
    'features/student/pages/dashboard/dashboard.html',
    'features/teacher/pages/dashboard/dashboard.html',
  ]) {
    const source = read(page);
    assert.doesNotMatch(source, /id="renewalPanel"/);
    assert.match(source, /<th>Actions<\/th>/);
    assert.match(source, /id="renewalModal"/);
  }
  for (const page of [
    'features/student/pages/dashboard/student-dashboard.page.js',
    'features/teacher/pages/dashboard/teacher-dashboard.page.js',
  ]) {
    const source = read(page);
    assert.match(source, /RenewalModalComponent/);
    assert.match(source, /data-renewal-open/);
    assert.match(source, /View receipt/);
    assert.match(source, /Renew/);
    assert.match(source, /Awaiting approval/);
    assert.match(source, /Resolve overdue balance/);
  }
});

test('renewal presentation no longer uses the standalone panel contract', () => {
  const styles = read('assets/css/reservations.css');
  assert.match(styles, /\.reservation-queue/);
  assert.doesNotMatch(styles, /\.renewal-panel/);
  assert.match(read('assets/css/borrower-dashboards.css'), /\.borrower-dashboard__loan-actions/);
});
~~~

- [ ] **Step 2: Update dashboard redesign expectations from Receipt to Actions**

In the existing student and teacher active-loan tests, change:

~~~js
['Book', 'Quantity', 'Borrowed', 'Due', 'Status', 'Receipt']
~~~

to:

~~~js
['Book', 'Quantity', 'Borrowed', 'Due', 'Status', 'Actions']
~~~

Keep the \`current-loans\` and existing content-preservation assertions.

- [ ] **Step 3: Run the focused tests and confirm the expected red state**

Run:

~~~powershell
node --test frontend/tests/borrower-renewals.test.js frontend/tests/borrower-dashboard-redesign.test.js
~~~

Expected: FAIL because the modal module does not exist, both dashboards still expose \`renewalPanel\`, the headings still say \`Receipt\`, and the old stylesheet still contains \`.renewal-panel\`. This confirms the tests exercise the requested change.

### Task 2: Replace the dashboard panel with a shared renewal modal

**Files:**
- Create: \`frontend/app/shared/components/renewal-modal/renewal-modal.component.js\`
- Delete: \`frontend/app/shared/components/renewal-panel/renewal-panel.component.js\` after dashboard imports are migrated
- Modify: \`frontend/assets/css/reservations.css:11-36\`

**Interfaces:**
- Consumes: \`{ service, onChanged, onError, contentClass, headerClass }\`; \`service.request(loanId, reason)\` returns a promise.
- Produces: \`open(loan)\`, \`handleSubmit(event)\`, \`close()\`, and \`destroy()\`.

- [ ] **Step 1: Create the modal component**

Create the shared component with this implementation shape:

~~~js
export class RenewalModalComponent {
  constructor(root, {
    service,
    onChanged = () => {},
    onError = () => {},
    contentClass = '',
    headerClass = '',
  } = {}) {
    this.root = root;
    this.service = service;
    this.onChanged = onChanged;
    this.onError = onError;
    this.contentClass = contentClass;
    this.headerClass = headerClass;
    this.boundSubmit = (event) => this.handleSubmit(event);
    this.root?.addEventListener?.('submit', this.boundSubmit);
  }

  open(loan) {
    const loanId = loan?.id || loan?.loan_id;
    if (!this.root || !loanId) return;
    const title = this.escape(loan.title || '');
    const dueDate = this.escape(loan.due_date || '—');
    const safeLoanId = this.escape(loanId);
    const titleId = 'renewal-modal-title-' + safeLoanId;
    const reasonId = 'renewal-reason-' + safeLoanId;

    this.root.innerHTML =
      '<div class="modal-dialog"><div class="modal-content ' +
      this.escape(this.contentClass) +
      ' renewal-modal"><form data-renewal-form>' +
      '<div class="modal-header text-white ' + this.escape(this.headerClass) + '">' +
      '<h5 class="modal-title" id="' + titleId + '">Renew book</h5>' +
      '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>' +
      '</div><div class="modal-body"><p class="renewal-modal__loan"><strong>' +
      title + '</strong><span>Due ' + dueDate + '</span></p>' +
      '<label class="form-label fw-semibold" for="' + reasonId + '">Reason (optional)</label>' +
      '<textarea class="form-control" id="' + reasonId + '" name="reason" maxlength="500" rows="4" placeholder="Tell us why you need more time."></textarea>' +
      '<div class="form-text">Requests add one standard seven-day period and require librarian approval.</div>' +
      '<input type="hidden" name="loan_id" value="' + safeLoanId + '">' +
      '</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>' +
      '<button type="submit" class="btn renewal-modal__submit" data-renewal-submit>Request +7 days</button>' +
      '</div></form></div></div>';

    this.instance = globalThis.bootstrap?.Modal?.getOrCreateInstance?.(this.root);
    this.instance?.show?.();
  }

  async handleSubmit(event) {
    const form = event.target?.closest?.('[data-renewal-form]');
    if (!form) return;
    event.preventDefault();
    const button = form.querySelector('[data-renewal-submit]');
    if (button) button.disabled = true;
    try {
      await this.service.request(
        form.elements.loan_id.value,
        form.elements.reason.value.trim(),
      );
      this.close();
      await this.onChanged();
    } catch (error) {
      if (button) button.disabled = false;
      this.onError(error?.message || 'Unable to submit the renewal request.');
    }
  }

  close() { this.instance?.hide?.(); }

  escape(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[character]));
  }

  destroy() {
    this.root?.removeEventListener?.('submit', this.boundSubmit);
  }
}
~~~

The component must render escaped title/due-date values, delegate one submit listener through \`data-renewal-form\`, and leave errors visible through the dashboard's \`onError\` callback.

- [ ] **Step 2: Remove panel-only CSS**

Delete the \`.renewal-panel\`, \`.renewal-panel__*\`, and panel mobile rules from \`frontend/assets/css/reservations.css\`. Keep reservation queue rules and the staff renewal page rules. Do not delete the old component file until Tasks 3 and 4 no longer import it.

- [ ] **Step 3: Run the component test**

Run:

~~~powershell
node --test frontend/tests/borrower-renewals.test.js
~~~

Expected: the modal contract passes after the new component exists; dashboard markup assertions remain red until both dashboard pages are migrated.

### Task 3: Put renewal actions into the student My Books table

**Files:**
- Modify: \`frontend/features/student/pages/dashboard/student-dashboard.page.js:1-31,69-103,210-224,299-336\`
- Modify: \`frontend/features/student/pages/dashboard/dashboard.html:203-228,334-420\`
- Modify: \`frontend/assets/css/borrower-dashboards.css\`

**Interfaces:**
- Consumes: \`RenewalService.list()\`, \`RenewalModalComponent.open(loan)\`, and the student dashboard payload.
- Produces: \`currentLoans\`, a renewal \`Map\` keyed by \`loan_id\`, delegated \`data-renewal-open\` handling, and student-scoped Actions controls.

- [ ] **Step 1: Replace the student panel wiring**

Import \`RenewalModalComponent\`, create one \`RenewalService\), and replace the panel instance with:

~~~js
this.renewalService = new RenewalService({
  api: new ApiClient({ csrf: this.csrf, fetchImpl: window.fetch.bind(window) }),
  role: 'student',
});
this.currentLoans = [];
this.renewals = new Map();
this.renewalModal = new RenewalModalComponent(this.$('renewalModal'), {
  service: this.renewalService,
  contentClass: 'student-dashboard__modal',
  headerClass: 'student-dashboard__modal-header',
  onChanged: () => this.load(),
  onError: (message) => this.showToast(message, false),
});
~~~

In \`render(data)\`, assign the current loans, reset the renewal map, render immediately, and load renewal records:

~~~js
this.currentLoans = data.current_loans || [];
this.renewals = new Map();
this.renderLoans(this.currentLoans);
this.loadRenewals();
~~~

Add these methods before \`renderLoans()\`:

~~~js
loadRenewals() {
  this.renewalService.list()
    .then((response) => {
      this.renewals = new Map(
        (response?.data?.renewals || []).map((renewal) => [
          String(renewal.loan_id),
          renewal,
        ]),
      );
      this.renderLoans(this.currentLoans);
    })
    .catch(() => {
      this.renewals = new Map();
      this.renderLoans(this.currentLoans);
    });
}

renewalAction(loan) {
  const loanId = loan.id || loan.loan_id;
  const renewal = this.renewals.get(String(loanId));
  const status = String(loan.status || '').toLowerCase();
  if (renewal) {
    return '<span class="borrower-dashboard__renewal-status">' +
      this.escapeHtml(renewal.status_label || renewal.status) + '</span>';
  }
  if (status === 'borrowed') {
    return '<button type="button" class="btn btn-sm borrower-dashboard__renew-action" ' +
      'data-renewal-open data-loan-id="' + this.escapeHtml(loanId) + '">Renew</button>';
  }
  const label = status === 'pending'
    ? 'Awaiting approval'
    : status === 'overdue'
      ? 'Resolve overdue balance'
      : 'Renewal unavailable';
  return '<span class="borrower-dashboard__renewal-status borrower-dashboard__renewal-status--muted">' +
    label + '</span>';
}
~~~

Update the student row's final cell so it contains both controls:

~~~js
const receipt = '<a href="/scan2borrow/receipt?code=' +
  encodeURIComponent(loan.transaction_code || '') +
  '" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">View receipt</a>';
row.innerHTML =
  '<td>' + this.escapeHtml(loan.title) + '<br><span class="text-muted small">' +
  this.escapeHtml(loan.author) + '</span></td><td>' +
  Number(loan.quantity || 1) + '</td><td>' +
  this.formatDate(loan.borrow_date) + '</td><td>' +
  this.formatDate(loan.due_date) + '</td><td>' +
  this.badge(loan.status) + '</td><td><div class="borrower-dashboard__loan-actions">' +
  receipt + this.renewalAction(loan) + '</div></td>';
~~~

Add a delegated listener to \`bindEvents()\`:

~~~js
this.$('current-loans')?.addEventListener('click', (event) => {
  const button = event.target.closest('[data-renewal-open]');
  if (!button) return;
  const loan = this.currentLoans.find(
    (item) => String(item.id || item.loan_id) === button.dataset.loanId,
  );
  if (loan) this.renewalModal.open(loan);
});
~~~

- [ ] **Step 2: Update student markup**

Remove the \`renewalPanel\` mount, change the table heading from \`Receipt\` to \`Actions\`, keep the empty-state \`colspan="6"\`, and add this modal mount before the borrow modal:

~~~html
<div class="modal fade student-dashboard__renewal-modal" id="renewalModal" tabindex="-1" aria-hidden="true"></div>
~~~

- [ ] **Step 3: Add student Organic action styling**

Add these rules to \`borrower-dashboards.css\`:

~~~css
.borrower-dashboard__loan-actions { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; min-width: 170px; }
.borrower-dashboard__loan-actions .btn { white-space: nowrap; }
.borrower-dashboard--student .borrower-dashboard__renew-action { background: var(--borrower-accent); border-color: var(--borrower-accent); border-radius: 12px; color: var(--borrower-deep); font-weight: 700; }
.borrower-dashboard--student .borrower-dashboard__renew-action:hover,
.borrower-dashboard--student .borrower-dashboard__renew-action:focus-visible { background: var(--borrower-deep); border-color: var(--borrower-deep); color: var(--card); }
.borrower-dashboard__renewal-status { border: 1px solid var(--borrower-accent); border-radius: 10px; color: var(--borrower-accent); display: inline-block; font-size: .72rem; font-weight: 700; padding: 6px 9px; }
.borrower-dashboard__renewal-status--muted { border-color: var(--border-strong); color: var(--muted); }
.borrower-dashboard__renewal-modal .renewal-modal__loan { display: grid; gap: 4px; margin-bottom: 1.25rem; }
.borrower-dashboard__renewal-modal .renewal-modal__loan span { color: var(--muted); font-size: .85rem; }
.borrower-dashboard--student .renewal-modal__submit { background: var(--borrower-accent); border-color: var(--borrower-accent); border-radius: 12px; color: var(--borrower-deep); font-weight: 700; }
~~~

- [ ] **Step 4: Run the student-focused contracts**

~~~powershell
node --test frontend/tests/borrower-renewals.test.js frontend/tests/borrower-dashboard-redesign.test.js
~~~

Expected: student modal, markup, action, and Organic CSS assertions pass; teacher assertions remain red until Task 4.

### Task 4: Put renewal actions into the teacher My Books table

**Files:**
- Modify: \`frontend/features/teacher/pages/dashboard/teacher-dashboard.page.js:1-31,57-96,135-163\`
- Modify: \`frontend/features/teacher/pages/dashboard/dashboard.html:362-389,466-547\`
- Modify: \`frontend/assets/css/borrower-dashboards.css\`

**Interfaces:**
- Consumes: the shared \`RenewalModalComponent\` contract with \`role: 'teacher'\`.
- Produces: teacher-scoped Actions markup, delegated renewal opening, and Swiss modal styling; staff approval remains unchanged.

- [ ] **Step 1: Replace teacher panel wiring and render Actions**

Add loadRenewals() and renewalAction(loan) directly to the teacher page with these exact contracts:

~~~js
loadRenewals() {
  this.renewalService.list()
    .then((response) => {
      this.renewals = new Map(
        (response?.data?.renewals || []).map((renewal) => [
          String(renewal.loan_id),
          renewal,
        ]),
      );
      this.renderLoans(this.currentLoans);
    })
    .catch(() => {
      this.renewals = new Map();
      this.renderLoans(this.currentLoans);
    });
}

renewalAction(loan) {
  const loanId = loan.id || loan.loan_id;
  const renewal = this.renewals.get(String(loanId));
  const status = String(loan.status || '').toLowerCase();
  if (renewal) {
    return '<span class="borrower-dashboard__renewal-status">' +
      this.escapeHtml(renewal.status_label || renewal.status) + '</span>';
  }
  if (status === 'borrowed') {
    return '<button type="button" class="btn btn-sm borrower-dashboard__renew-action" ' +
      'data-renewal-open data-loan-id="' + this.escapeHtml(loanId) + '">Renew</button>';
  }
  const label = status === 'pending'
    ? 'Awaiting approval'
    : status === 'overdue'
      ? 'Resolve overdue balance'
      : 'Renewal unavailable';
  return '<span class="borrower-dashboard__renewal-status borrower-dashboard__renewal-status--muted">' +
    label + '</span>';
}
~~~

~~~js
this.renewalService = new RenewalService({
  api: new ApiClient({ csrf: this.csrf, fetchImpl: window.fetch.bind(window) }),
  role: 'teacher',
});
this.currentLoans = [];
this.renewals = new Map();
this.renewalModal = new RenewalModalComponent(document.getElementById('renewalModal'), {
  service: this.renewalService,
  contentClass: 'teacher-dashboard__modal',
  headerClass: 'teacher-dashboard__modal-header',
  onChanged: () => this.load(),
  onError: (message) => this.toast(message),
});
~~~

In \`render(data)\`, set \`currentLoans\`, reset \`renewals\`, call \`renderLoans()\`, and call \`loadRenewals()\`. Replace the receipt-only cell with:

~~~js
const receipt = '<a href="/scan2borrow/receipt?code=' +
  encodeURIComponent(loan.transaction_code || '') +
  '" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">View receipt</a>';
row.innerHTML =
  '<td>' + this.escapeHtml(loan.title) + '<br><span class="text-muted small">' +
  this.escapeHtml(loan.author || '') + '</span></td><td>' +
  Number(loan.quantity || 1) + '</td><td>' +
  this.escapeHtml(loan.borrow_date || '') + '</td><td>' +
  this.escapeHtml(loan.due_date || '') + '</td><td>' +
  this.badge(loan.status || '') + '</td><td><div class="borrower-dashboard__loan-actions">' +
  receipt + this.renewalAction(loan) + '</div></td>';
~~~

Add the same delegated \`current-loans\` click listener from Task 3, calling \`this.renewalModal.open(loan)\` for the matching \`data-loan-id\`.

- [ ] **Step 2: Update teacher markup**

Remove the \`renewalPanel\` mount, change \`Receipt\` to \`Actions\`, keep the empty-state \`colspan="6"\`, and add:

~~~html
<div class="modal fade teacher-dashboard__renewal-modal" id="renewalModal" tabindex="-1" aria-hidden="true"></div>
~~~

- [ ] **Step 3: Add Swiss teacher action styling**

Add:

~~~css
.borrower-dashboard--teacher .borrower-dashboard__renew-action { background: var(--borrower-accent); border-color: var(--borrower-accent); border-radius: 3px; color: #FFFFFF; font-weight: 700; }
.borrower-dashboard--teacher .borrower-dashboard__renew-action:hover,
.borrower-dashboard--teacher .borrower-dashboard__renew-action:focus-visible { background: #001F73; border-color: #001F73; color: #FFFFFF; }
.borrower-dashboard--teacher .borrower-dashboard__renewal-status { border-radius: 3px; }
.borrower-dashboard--teacher .borrower-dashboard__renewal-status--muted { border-color: #B9C9D5; color: #63798B; }
.borrower-dashboard--teacher .renewal-modal__submit { background: var(--borrower-accent); border-color: var(--borrower-accent); border-radius: 3px; color: #FFFFFF; font-weight: 700; }
~~~

- [ ] **Step 4: Run focused borrower tests**

~~~powershell
node --test frontend/tests/borrower-renewals.test.js frontend/tests/borrower-dashboard-redesign.test.js
~~~

Expected: all focused tests pass and no dashboard source or active CSS reference remains to \`renewalPanel\` or \`.renewal-panel\`.

### Task 5: Finish responsive behavior, tests, and commit

**Files:**
- Modify: \`frontend/tests/borrower-renewals.test.js\`
- Modify: \`frontend/tests/borrower-dashboard-redesign.test.js\`
- Modify: \`frontend/assets/css/borrower-dashboards.css\`
- Modify: \`frontend/assets/css/reservations.css\`
- Delete: \`frontend/app/shared/components/renewal-panel/renewal-panel.component.js\`

**Interfaces:**
- Consumes: completed student/teacher row actions and shared modal.
- Produces: a clean regression suite and a committed frontend-only implementation.

- [ ] **Step 1: Add responsive action layout**

Append:

~~~css
@media (max-width: 768px) {
  .borrower-dashboard__loan-actions { align-items: stretch; flex-direction: column; min-width: 132px; }
  .borrower-dashboard__loan-actions .btn,
  .borrower-dashboard__loan-actions .borrower-dashboard__renewal-status { text-align: center; width: 100%; }
}
~~~

- [ ] **Step 2: Strengthen the state and cleanup assertions**

Ensure the renewal tests assert all of the following for both dashboard page sources: \`status === 'pending'\`, \`status === 'overdue'\`, \`renewal.status_label || renewal.status\`, and \`data-renewal-open\`. Assert that both HTML files contain \`<th>Actions</th>\`, \`id="renewalModal"\`, and no \`id="renewalPanel"\`. Assert that \`reservations.css\` retains \`.reservation-queue\` but no \`.renewal-panel\`.

- [ ] **Step 3: Run the complete frontend suite**

~~~powershell
npm test
~~~

Expected: all frontend tests pass, including the updated renewal tests and dashboard contracts.

- [ ] **Step 4: Commit the frontend change**

~~~powershell
git add frontend/app/shared/components/renewal-modal/renewal-modal.component.js frontend/app/shared/components/renewal-panel/renewal-panel.component.js frontend/features/student/pages/dashboard/student-dashboard.page.js frontend/features/student/pages/dashboard/dashboard.html frontend/features/teacher/pages/dashboard/teacher-dashboard.page.js frontend/features/teacher/pages/dashboard/dashboard.html frontend/assets/css/borrower-dashboards.css frontend/assets/css/reservations.css frontend/tests/borrower-renewals.test.js frontend/tests/borrower-dashboard-redesign.test.js
git commit -m "feat: move borrower renewals into book actions"
~~~

### Task 6: Verify the unchanged backend contract and frontend syntax

**Files:**
- No source changes expected.

**Interfaces:**
- Consumes: the committed frontend implementation and existing renewal API contract.
- Produces: final verification evidence.

- [ ] **Step 1: Run focused frontend tests once more**

~~~powershell
node --test frontend/tests/borrower-renewals.test.js frontend/tests/borrower-dashboard-redesign.test.js
~~~

Expected: all focused tests pass.

- [ ] **Step 2: Run backend renewal tests**

~~~powershell
Push-Location backend
& 'C:\xampp\php\php.exe' vendor/bin/phpunit tests/Unit/Renewal tests/Feature/RenewalControllerTest.php tests/Feature/RenewalSchemaContractTest.php tests/Feature/RenewalNotificationSchemaTest.php
$backendExit = $LASTEXITCODE
Pop-Location
if ($backendExit -ne 0) { exit $backendExit }
~~~

Expected: backend renewal tests pass; no backend files are changed.

- [ ] **Step 3: Run syntax and diff checks**

~~~powershell
Get-ChildItem -Path frontend -Recurse -Filter *.js | ForEach-Object { node --check $_.FullName }
git diff --check
git status --short
~~~

Expected: no JavaScript syntax errors or whitespace errors; only pre-existing unrelated untracked files remain outside the committed implementation.
