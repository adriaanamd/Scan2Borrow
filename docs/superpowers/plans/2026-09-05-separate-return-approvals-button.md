# Separate Return Approvals Button Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give staff a dedicated `Return Approvals` dashboard button, badge, and modal for reviewing librarian-approved return requests.

**Architecture:** Keep the existing return-approval API, rendering methods, CSRF handling, confirmation flow, and polling. Move the return review markup out of the borrow-approval modal, add a sibling dashboard trigger/modal, and make each badge count only its own request type.

**Tech Stack:** Static HTML, ES modules, Node’s built-in test runner, existing Bootstrap modal styles, PHP backend APIs unchanged.

## Global Constraints

- Preserve the existing borrow approval workflow and generic `Pending Approvals` button.
- Reuse `GET /scan2borrow/api/staff/return-approvals` and `POST /scan2borrow/api/staff/return-action`.
- Keep return rejection notes, confirmation dialogs, CSRF, and staff authorization unchanged.
- Do not change SQL, backend behavior, unrelated dashboard features, or existing test coverage.
- Run targeted checks first, then the complete frontend test command and relevant backend return tests.

---

### Task 1: Add the failing discoverability contract test

**Files:**
- Modify: `frontend/tests/staff-pages.test.js` after the existing librarian-approved return review test

**Interfaces:**
- Consumes: `frontend/features/staff/pages/dashboard/dashboard.html`
- Produces: A regression test requiring a separate return trigger/modal and ensuring return markup is not inside `#approvalModal`.

- [ ] **Step 1: Write the failing test**

Add this test after `staff dashboard exposes librarian-approved return review fields and endpoint`:

```js
test('staff dashboard exposes return approvals as a separate action', () => {
  const template = fs.readFileSync(dashboardTemplate, 'utf8');
  assert.match(template, /id="return-approvals-trigger"/);
  assert.match(template, /data-bs-target="#returnApprovalModal"/);
  assert.match(template, />Return Approvals</);
  assert.match(template, /id="returnApprovalModal"/);
  assert.match(template, /id="return-approvals-count"/);

  const borrowModalStart = template.indexOf('id="approvalModal"');
  const returnModalStart = template.indexOf('id="returnApprovalModal"');
  assert.ok(borrowModalStart >= 0);
  assert.ok(returnModalStart > borrowModalStart);
  const borrowModal = template.slice(borrowModalStart, returnModalStart);
  assert.doesNotMatch(borrowModal, /return-approval-section|returnApprovalList/);
});
```

- [ ] **Step 2: Run the focused test to verify it fails for the intended reason**

Run from the repository root:

```powershell
npm test -- --test-name-pattern="separate action"
```

Expected: FAIL because the current template has no `return-approvals-trigger` or `returnApprovalModal`, and the return section is still inside `#approvalModal`.

- [ ] **Step 3: Commit the red test**

```powershell
git add frontend/tests/staff-pages.test.js
git commit -m "test: require separate return approvals action"
```

### Task 2: Add the separate dashboard action and modal

**Files:**
- Modify: `frontend/features/staff/pages/dashboard/dashboard.html:39-58` for the sibling trigger
- Modify: `frontend/features/staff/pages/dashboard/dashboard.html:599-860` to remove the return section from the borrow modal and add the dedicated return modal
- Modify: `frontend/features/staff/pages/dashboard/staff-dashboard.page.js:615-689` so the two badges remain independent

**Interfaces:**
- Consumes: Existing `StaffDashboardPage.renderApprovals`, `renderReturnApprovals`, `submitReturn`, and existing staff return endpoints.
- Produces: `#return-approvals-trigger` targeting `#returnApprovalModal`; `#return-approvals-count` contains only return-request count; `#pending-approvals-count` contains only borrow-request count.

- [ ] **Step 1: Add the sibling `Return Approvals` trigger**

Immediately after the existing `#pending-approvals-trigger` button in the page header, add:

```html
<button
  id="return-approvals-trigger"
  type="button"
  class="btn btn-outline-danger"
  data-bs-toggle="modal"
  data-bs-target="#returnApprovalModal"
>
  Return Approvals
  <span id="return-approvals-count" class="badge bg-danger ms-2">0</span>
</button>
```

- [ ] **Step 2: Move the return review section into its own modal**

Remove the existing `.return-approval-section` through its closing `</section>` from `#approvalModal`. Insert a new sibling modal after `#approvalModal` and before the closing content wrapper:

```html
<div class="modal fade" id="returnApprovalModal" tabindex="-1" aria-labelledby="returnApprovalModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="returnApprovalModalLabel">Return Approvals</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="max-height: 70vh; overflow-y: auto">
        <section class="return-approval-section" aria-labelledby="return-approvals-title">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h5 id="return-approvals-title" class="mb-1">Returns awaiting verification</h5>
              <p class="small text-muted mb-0">Confirm the physical book is at the desk before approving.</p>
            </div>
            <span id="return-approvals-modal-count" class="badge bg-warning text-dark">0</span>
          </div>
          <div id="returnApprovalList"></div>
        </section>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
```

Keep the existing return card styles available to this modal and keep `returnApprovalList` as the render target used by `renderReturnApprovals`.

- [ ] **Step 3: Make the badges independent**

Replace `updateApprovalCounts()` with:

```js
updateApprovalCounts() {
  const borrowCount = document.getElementById("pending-approvals-count");
  if (borrowCount) borrowCount.textContent = String(this.borrowApprovalCount);
  const returnCount = document.getElementById("return-approvals-count");
  if (returnCount) returnCount.textContent = String(this.returnApprovalCount);
  const modalReturnCount = document.getElementById("return-approvals-modal-count");
  if (modalReturnCount) modalReturnCount.textContent = String(this.returnApprovalCount);
}
```

Remove the extra `#approvalModal .badge` update loop from `renderApprovals`; the dedicated button and modal counters will now be updated only by `updateApprovalCounts()`.

- [ ] **Step 4: Run the focused frontend tests to verify the implementation passes**

Run:

```powershell
npm test -- --test-name-pattern="staff dashboard"
```

Expected: all matching staff-dashboard tests pass, including the new separate-action test.

- [ ] **Step 5: Commit the implementation**

```powershell
git add frontend/features/staff/pages/dashboard/dashboard.html frontend/features/staff/pages/dashboard/staff-dashboard.page.js frontend/tests/staff-pages.test.js
git commit -m "feat: separate return approvals dashboard action"
```

### Task 3: Run the repository quality checks and inspect the final diff

**Files:**
- Verify: `frontend/features/staff/pages/dashboard/dashboard.html`
- Verify: `frontend/features/staff/pages/dashboard/staff-dashboard.page.js`
- Verify: `frontend/tests/staff-pages.test.js`

**Interfaces:**
- Consumes: The committed dashboard implementation and existing backend return-approval tests.
- Produces: Fresh local verification output and a clean, scoped git diff.

- [ ] **Step 1: Run the complete frontend test suite**

Run:

```powershell
npm test
```

Expected: all frontend tests pass with no skipped tests.

- [ ] **Step 2: Run the relevant backend return-approval tests**

Run from `backend`:

```powershell
& 'C:\xampp\php\php.exe' vendor/bin/phpunit tests/Unit/Infrastructure/PdoReturnApprovalRepositoryTest.php tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php tests/Unit/Borrowing/ReturnServiceTest.php tests/Unit/Reservation/ReturnAvailabilityIntegrationTest.php tests/Feature/SchemaContractTest.php
```

Expected: 27 tests pass, 260 assertions pass.

- [ ] **Step 3: Check the diff and repository status**

Run:

```powershell
git diff HEAD~1 --check
git status --short --branch
```

Expected: no whitespace errors; only the intended dashboard/test changes are present; the branch is clean after the implementation commit.

- [ ] **Step 4: Commit any required final cleanup**

If the checks require a small implementation-only cleanup, run the focused frontend test again and commit it with:

```powershell
git add frontend/features/staff/pages/dashboard/dashboard.html frontend/features/staff/pages/dashboard/staff-dashboard.page.js frontend/tests/staff-pages.test.js
git commit -m "fix: polish separate return approvals action"
```
