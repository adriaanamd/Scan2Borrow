# Student-First Approval Cards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the student or borrower name the primary visual identity in both staff approval-card workflows while keeping book titles as readable secondary context.

**Architecture:** Keep the existing staff dashboard controller and modal markup. Update only the `approvalCard()` and `returnApprovalCard()` renderers plus the dashboard-local card styles, using explicit borrower and book-title classes in each renderer. Add a source-level frontend contract test that locks the order and hierarchy without changing API behavior.

**Tech Stack:** Vanilla JavaScript ES modules, static HTML, inline dashboard CSS, Node’s built-in test runner.

## Global Constraints

- Preserve existing modal structure, counters, API calls, approval decisions, return evidence, metadata, and action controls.
- Keep the pending borrow card’s existing book cover and dark surface.
- Keep the return card’s existing evidence photo and light surface.
- Long names and titles must wrap naturally at narrow widths rather than overflow or be clipped.
- Do not change backend services, database schema, endpoint paths, modal IDs, approval counts, polling, confirmation behavior, or unrelated working-tree files.
- Run the focused frontend test first, then the complete frontend test suite, and inspect the final diff for whitespace errors.

---

### Task 1: Add the failing student-first card contract

**Files:**
- Modify: `frontend/tests/staff-pages.test.js` after the existing separate return approvals action test

**Interfaces:**
- Consumes: `frontend/features/staff/pages/dashboard/staff-dashboard.page.js` as source text.
- Produces: a regression contract requiring both renderers to emit borrower identity before book context with explicit hierarchy classes.

- [ ] **Step 1: Write the failing test**

Add this test after the `staff dashboard exposes return approvals as a separate action` test:

```js
test('approval cards prioritize the borrower name over the book title', () => {
  const source = fs.readFileSync(staffDashboardSource, 'utf8');
  const returnCardStart = source.indexOf('returnApprovalCard(row)');
  const approvalCardStart = source.indexOf('approvalCard(row)');

  assert.ok(returnCardStart >= 0);
  assert.ok(approvalCardStart >= 0);

  const returnCard = source.slice(returnCardStart, source.indexOf('\n  updateApprovalCounts()', returnCardStart));
  const approvalCard = source.slice(approvalCardStart, source.indexOf('\n  async refreshNotifications()', approvalCardStart));

  assert.match(approvalCard, /approval-card__borrower/);
  assert.match(approvalCard, /approval-card__book-title/);
  assert.ok(approvalCard.indexOf('approval-card__borrower') < approvalCard.indexOf('approval-card__book-title'));
  assert.match(returnCard, /return-approval-card__borrower/);
  assert.match(returnCard, /return-approval-card__book-title/);
  assert.ok(returnCard.indexOf('return-approval-card__borrower') < returnCard.indexOf('return-approval-card__book-title'));
});
```

- [ ] **Step 2: Run the focused test and confirm it fails for the intended reason**

Run from the repository root:

```powershell
npm test -- --test-name-pattern="approval cards prioritize the borrower name"
```

Expected result: the test fails because the current renderers do not emit the new borrower/book-title hierarchy classes.

- [ ] **Step 3: Commit the red test**

```powershell
git add frontend/tests/staff-pages.test.js
git commit -m "test: require student-first approval cards"
```

### Task 2: Reorder and enlarge the approval-card identities

**Files:**
- Modify: `frontend/features/staff/pages/dashboard/staff-dashboard.page.js:649-711`
- Modify: `frontend/features/staff/pages/dashboard/dashboard.html:631-759`

**Interfaces:**
- Consumes: Existing row fields, `media()`, `escape()`, date formatting, approval actions, return evidence, and existing modal targets.
- Produces: borrower-first markup in `approvalCard(row)` and `returnApprovalCard(row)`, plus responsive styles for both hierarchy levels.

- [ ] **Step 1: Update `returnApprovalCard(row)` identity markup**

Replace the text block inside the return card’s first flex child with:

```js
<div class="return-approval-card__identity">
  <h4 class="return-approval-card__borrower mb-1">${this.escape(row.borrower || "Borrower")}</h4>
  <p class="return-approval-card__book-title mb-1">${this.escape(row.title || "Book")}</p>
  <p class="small text-muted mb-2">Barcode: ${this.escape(row.book_barcode || "—")}</p>
  <p class="small mb-1"><strong>Due:</strong> ${this.escape(this.date(row.due_date) || "—")}</p>
  <p class="small mb-2"><strong>Requested:</strong> ${this.escape(row.requested_at || "—")}</p>
  <span class="badge bg-warning text-dark">${this.escape(row.return_status || "pending") === "pending" ? "Return Verification Pending" : this.escape(row.return_status)}</span>
</div>
```

Keep the existing evidence markup and return form unchanged.

- [ ] **Step 2: Update `approvalCard(row)` identity markup**

Replace the current title/author block with:

```js
<div class="approval-card__identity">
  <p class="approval-card__borrower">${this.escape(row.borrower || "Borrower")}</p>
  <p class="approval-card__book-title">${this.escape(row.title || "Book")}</p>
  <p class="function">by ${this.escape(row.author || "—")} | Barcode: ${this.escape(row.book_barcode || "—")}</p>
</div>
```

Keep the details block and Accept/Reject forms unchanged.

- [ ] **Step 3: Adjust pending-card styles**

In the dashboard-local `<style>` block, replace the fixed-height and old title rules with:

```css
.approval-card .info {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
}
.approval-card__identity {
  min-width: 0;
}
.approval-card__borrower {
  font-size: 1.45rem;
  line-height: 1.25;
  font-weight: 800;
  color: #fff;
  margin: 0 0 0.25rem;
  overflow-wrap: anywhere;
}
.approval-card__book-title {
  font-size: 0.95rem;
  line-height: 1.35;
  font-weight: 600;
  color: rgba(209, 213, 219, 1);
  margin: 0;
  overflow-wrap: anywhere;
}
.approval-card .function {
  font-size: 0.8rem;
  color: rgba(156, 163, 175, 1);
  margin: 0.2rem 0 0;
  overflow-wrap: anywhere;
}
```

Remove the old `.approval-card .name` rule and remove `height: 7rem` from `.approval-card .info` so long names are not clipped.

- [ ] **Step 4: Add return-card hierarchy styles**

Add these rules alongside the existing return-card styles:

```css
.return-approval-card__identity {
  min-width: 0;
  flex: 1 1 16rem;
}
.return-approval-card__borrower {
  font-size: 1.35rem;
  line-height: 1.25;
  font-weight: 800;
  color: #0f172a;
  overflow-wrap: anywhere;
}
.return-approval-card__book-title {
  font-size: 0.95rem;
  line-height: 1.35;
  font-weight: 600;
  color: #475569;
  overflow-wrap: anywhere;
}
```

The existing `.return-approval-card` flex wrapper already uses `flex-wrap: wrap`; keep that behavior so evidence moves below the identity block when needed.

- [ ] **Step 5: Run the focused test and confirm it passes**

Run:

```powershell
npm test -- --test-name-pattern="approval cards prioritize the borrower name"
```

Expected result: the new contract passes with zero failures.

- [ ] **Step 6: Commit the implementation**

```powershell
git add frontend/features/staff/pages/dashboard/staff-dashboard.page.js frontend/features/staff/pages/dashboard/dashboard.html
git commit -m "feat: prioritize borrower names in approval cards"
```

### Task 3: Run the complete frontend quality gate

**Files:**
- Verify: `frontend/features/staff/pages/dashboard/staff-dashboard.page.js`
- Verify: `frontend/features/staff/pages/dashboard/dashboard.html`
- Verify: `frontend/tests/staff-pages.test.js`

**Interfaces:**
- Consumes: The student-first approval-card implementation and focused contract.
- Produces: fresh frontend test output and a scoped diff check.

- [ ] **Step 1: Run the complete frontend test suite**

Run:

```powershell
npm test
```

Expected result: the full Node test suite exits with code 0 and reports no failures or skipped tests.

- [ ] **Step 2: Check whitespace and scoped status**

Run:

```powershell
git diff --check HEAD~2
git status --short --branch
```

Expected result: `git diff --check` emits no whitespace errors. The only task commits are the focused test and implementation; existing unrelated modifications to `frontend/features/staff/services/index.js`, `frontend/tests/scanner.test.js`, and `uploads/202310449-606bb73417da81df.jpg` remain untouched.
