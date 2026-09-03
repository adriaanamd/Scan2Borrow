# Borrower Catalog Waitlist Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a confirmed `Join waitlist` action for unavailable books in the student Search Books and teacher Borrow Books catalogs, using the existing borrower hold API and dashboard queue.

**Architecture:** Keep the waitlist interaction in the shared `BorrowerSearchPage` controller because recommendation cards and paged catalog cards share `bookCard(book)`. The controller will load active holds, render role-aware unavailable actions, delegate clicks from both dynamic card collections, confirm through the existing global modal service, and submit through the existing `ReservationService`. Student and teacher pages will provide their role, expose a toast mount, and preserve their existing visual surfaces.

**Tech Stack:** Browser ES modules, existing `ApiClient`, `ReservationService`, `ToastService`, `Scan2BorrowConfirmation`, Bootstrap 5 markup/CSS, and Node’s native `node:test` suite.

## Global Constraints

- No new reservation statuses, database tables, or backend endpoints.
- Use the existing role-specific hold endpoints: `/scan2borrow/api/student/holds` and `/scan2borrow/api/teacher/holds`.
- Confirmation must occur before the POST request; canceling must make no request.
- Escape book-derived values before HTML insertion.
- Student keeps the Organic surface; teacher keeps the Swiss surface.
- Preserve the existing `Add to Borrow Cart`, `You have this`, dashboard `Your holds`, claim, and cancel behavior.
- Use the existing confirmation modal and toast conventions; do not add native `window.confirm` calls.

---

## File Map

- `frontend/tests/borrower-catalog.test.js` — focused shared catalog and waitlist behavior contracts.
- `frontend/tests/student-library-surfaces.test.js` — student catalog markup boundary assertions.
- `frontend/tests/teacher-borrow-history-surfaces.test.js` — teacher catalog markup and role assertions.
- `frontend/app/shared/pages/borrower-search.page.js` — shared active-hold state, unavailable action rendering, confirmation, POST, and feedback.
- `frontend/features/student/pages/search/student-search.page.js` — student role wiring.
- `frontend/features/teacher/pages/borrow/teacher-borrow.page.js` — teacher role wiring and custom action delegation.
- `frontend/features/student/pages/search/search.html` — student toast host.
- `frontend/features/teacher/pages/borrow/borrow.html` — teacher toast host.
- `frontend/assets/css/student-search.css` — student-scoped waitlist action treatment if needed.
- `frontend/assets/css/teacher-search.css` — teacher-scoped waitlist action treatment if needed.

No backend files are expected to change because the reservation route table, controller, service, and borrower role endpoints already support listing and joining holds.

---

### Task 1: Add failing waitlist contracts

**Files:**
- Modify: `frontend/tests/borrower-catalog.test.js`
- Modify: `frontend/tests/student-library-surfaces.test.js`
- Modify: `frontend/tests/teacher-borrow-history-surfaces.test.js`

**Interfaces:**
- Consumes: existing `BorrowerSearchPage`, student/teacher catalog templates, and role page controllers.
- Produces: failing contracts for active hold normalization, unavailable action markup, confirmed join behavior, role wiring, and toast mounts.

- [ ] **Step 1: Extend the focused catalog test file with waitlist behavior tests**

Append these tests to `frontend/tests/borrower-catalog.test.js`:

```js
test('active waitlist state keeps only actionable hold statuses', () => {
  const holds = [
    { title_id: 11, status: 'queued' },
    { title_id: 12, status: 'offered' },
    { title_id: 13, status: 'claimed' },
    { title_id: 14, status: 'fulfilled' },
    { title_id: 15, status: 'cancelled' },
    { title_id: 'invalid', status: 'queued' },
  ];

  const ids = BorrowerSearchPage.prototype.activeWaitlistTitleIds.call({}, holds);

  assert.deepEqual([...ids], [11, 12, 13]);
});

test('unavailable catalog actions offer a safe waitlist button', () => {
  const context = {
    classPrefix: 'student',
    waitlistedTitleIds: new Set(),
    escapeHtml: BorrowerSearchPage.prototype.escapeHtml,
  };
  const action = BorrowerSearchPage.prototype.waitlistAction.call(context, {
    id: 21,
    title: '<Clean Code>',
  });

  assert.match(action, /Join waitlist/);
  assert.match(action, /data-waitlist-title-id="21"/);
  assert.match(action, /data-waitlist-title="&lt;Clean Code&gt;"/);
  assert.doesNotMatch(action, />Unavailable</);
});

test('waitlisted catalog actions are disabled and cannot be joined again', () => {
  const context = {
    classPrefix: 'teacher',
    waitlistedTitleIds: new Set([21]),
    escapeHtml: BorrowerSearchPage.prototype.escapeHtml,
  };
  const action = BorrowerSearchPage.prototype.waitlistAction.call(context, {
    id: 21,
    title: 'Clean Code',
  });

  assert.match(action, /disabled/);
  assert.match(action, /On waitlist/);
  assert.doesNotMatch(action, /Join waitlist/);
});

test('waitlist confirmation cancels without joining', async () => {
  const calls = [];
  const button = {
    dataset: { waitlistTitleId: '31', waitlistTitle: 'Clean Code' },
    disabled: false,
    textContent: 'Join waitlist',
  };
  const context = {
    waitlistedTitleIds: new Set(),
    confirmation: {
      confirm: async () => false,
    },
    reservationService: {
      join: async () => calls.push('join'),
    },
    notify: (...args) => calls.push(args),
  };

  const result = await BorrowerSearchPage.prototype.confirmWaitlist.call(context, button);

  assert.equal(result, false);
  assert.deepEqual(calls, []);
  assert.equal(button.disabled, false);
  assert.equal(button.textContent, 'Join waitlist');
});

test('waitlist confirmation joins after acceptance and marks the button', async () => {
  const calls = [];
  const button = {
    dataset: { waitlistTitleId: '32', waitlistTitle: 'Refactoring' },
    disabled: false,
    textContent: 'Join waitlist',
  };
  const context = {
    waitlistedTitleIds: new Set(),
    confirmation: {
      confirm: async (options) => {
        await options.onConfirm();
        return true;
      },
    },
    reservationService: {
      join: async (titleId) => {
        calls.push(['join', titleId]);
        return { data: { message: 'You joined the queue for "Refactoring".' } };
      },
    },
    notify: (...args) => calls.push(args),
  };

  const result = await BorrowerSearchPage.prototype.confirmWaitlist.call(context, button);

  assert.equal(result, true);
  assert.deepEqual(calls, [
    ['join', 32],
    ['You joined the queue for "Refactoring".', 'success'],
  ]);
  assert.equal(button.disabled, true);
  assert.equal(button.textContent, 'On waitlist');
  assert.equal(context.waitlistedTitleIds.has(32), true);
});

test('catalog waitlist wiring preserves role endpoints and toast mounts', () => {
  const student = read('features/student/pages/search/student-search.page.js');
  const teacher = read('features/teacher/pages/borrow/teacher-borrow.page.js');
  const reservationService = read('app/core/services/reservation.service.js');
  const studentTemplate = read('features/student/pages/search/search.html');
  const teacherTemplate = read('features/teacher/pages/borrow/borrow.html');

  assert.match(student, /role: ['"]student['"]/);
  assert.match(teacher, /role: ['"]teacher['"]/);
  assert.match(reservationService, /\/scan2borrow\/api\/\$\{this\.role\}\/holds/);
  assert.match(studentTemplate, /id="toast-host"/);
  assert.match(teacherTemplate, /id="toast-host"/);
});
```

- [ ] **Step 2: Extend the student and teacher surface tests**

In the existing student catalog boundary test, add:

```js
assert.match(source, /id="toast-host"/);
```

In the existing teacher catalog boundary test, add the same assertion against the teacher template.

- [ ] **Step 3: Run the red checkpoint**

Run:

```powershell
node --test frontend/tests/borrower-catalog.test.js frontend/tests/borrower-reservations.test.js frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
```

Expected result: FAIL because the shared active-hold/action/confirmation methods, role wiring, and catalog toast mounts do not exist. Failures must identify missing waitlist behavior rather than test import or syntax errors.

- [ ] **Step 4: Commit the failing contracts**

```powershell
git add frontend/tests/borrower-catalog.test.js frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
git commit -m "test: define borrower catalog waitlist behavior"
```

---

### Task 2: Add shared reservation state and confirmation behavior

**Files:**
- Modify: `frontend/app/shared/pages/borrower-search.page.js`

**Interfaces:**
- Consumes: constructor role configuration, current CSRF token, existing `ReservationService`, `ApiClient`, `ToastService`, and global `Scan2BorrowConfirmation`.
- Produces: `activeWaitlistTitleIds(holds)`, `waitlistTitleId(book)`, `waitlistAction(book)`, `loadWaitlist()`, `confirmWaitlist(button)`, `markWaitlisted(button)`, `notify(message, type)`, and `handleWaitlistClick(event)`.

- [ ] **Step 1: Add waitlist dependencies and state to the shared controller**

Add these imports at the top of `frontend/app/shared/pages/borrower-search.page.js`:

```js
import { ApiClient } from "../../core/api/api-client.js";
import { ReservationService } from "../../core/services/reservation.service.js";
import { ToastService } from "../../core/services/toast.service.js";
```

Extend the constructor signature to accept `role`, `reservationService`, `toastService`, and `confirmation`, then add this state after the existing CSRF assignment:

```js
this.role = role === "teacher" ? "teacher" : "student";
this.reservationService = reservationService || new ReservationService({
  api: new ApiClient({ csrf: this.csrf, fetchImpl: window.fetch.bind(window) }),
  role: this.role,
});
this.toastService = toastService || new ToastService({ document });
this.confirmation = confirmation || window.Scan2BorrowConfirmation;
this.waitlistedTitleIds = new Set();
```

The existing constructor already obtains `this.csrf` before this block, and both catalog templates load `confirmation.js` before their page modules.

- [ ] **Step 2: Add active-hold normalization and loading**

Add these methods before `load()`:

```js
activeWaitlistTitleIds(holds) {
  const activeStatuses = new Set(["queued", "offered", "claimed"]);
  return new Set(
    (Array.isArray(holds) ? holds : [])
      .filter((hold) => activeStatuses.has(hold?.status))
      .map((hold) => Number(hold?.title_id || 0))
      .filter((titleId) => Number.isInteger(titleId) && titleId > 0),
  );
}

loadWaitlist() {
  return this.reservationService.list()
    .then((response) => {
      this.waitlistedTitleIds = this.activeWaitlistTitleIds(response?.data?.holds || []);
    })
    .catch(() => {
      this.waitlistedTitleIds = new Set();
    });
}
```

The catch intentionally lets the catalog continue rendering. The backend remains the final duplicate-join guard.

- [ ] **Step 3: Make catalog startup wait for hold state before rendering cards**

Replace the body of `load()` with this implementation while preserving the existing recommendation/catalog branching:

```js
load() {
  return this.loadWaitlist().then(() => {
    const filtered = this.hasCatalogQuery();
    this.setAllBooksVisible(filtered);
    this.recommendationPanel.hidden = filtered;
    if (filtered) {
      this.loadCatalog(Number(this.params.get("page") || 1));
      return;
    }
    this.renderRecommendationsLoading();
    this.loadRecommendations();
  });
}
```

- [ ] **Step 4: Add waitlist action rendering**

Add these methods immediately before the existing `bookAction(book)` method:

```js
waitlistTitleId(book) {
  const titleId = Number(book?.title_id ?? book?.id ?? 0);
  return Number.isInteger(titleId) && titleId > 0 ? titleId : 0;
}

waitlistAction(book) {
  const titleId = this.waitlistTitleId(book);
  if (this.waitlistedTitleIds.has(titleId)) {
    return '<button type="button" class="btn btn-outline-secondary w-100" disabled>On waitlist</button>';
  }
  if (!titleId) {
    return '<button type="button" class="btn btn-outline-secondary w-100" disabled>Waitlist unavailable</button>';
  }
  return `<button type="button" class="btn btn-outline-primary w-100" data-waitlist-title-id="${this.escapeHtml(titleId)}" data-waitlist-title="${this.escapeHtml(book.title || "")}">Join waitlist</button>`;
}

markWaitlisted(button) {
  button.disabled = true;
  button.textContent = "On waitlist";
  button.classList?.remove("btn-outline-primary");
  button.classList?.add("btn-outline-secondary");
}
```

The existing `bookAction(book)` will call `this.waitlistAction(book)` for unavailable titles after checking `already_borrowed`, so borrowed cards continue to show `You have this`.

- [ ] **Step 5: Add confirmation, join, error, and toast behavior**

Add these methods before `bindEvents()`:

```js
async confirmWaitlist(button) {
  const titleId = Number(button.dataset.waitlistTitleId || 0);
  if (!titleId || this.waitlistedTitleIds.has(titleId)) return false;

  try {
    return await this.confirmation.confirm({
      title: "Join waitlist",
      message: `Join the waitlist for "${button.dataset.waitlistTitle || "this book"}"?`,
      confirmLabel: "Join waitlist",
      confirmClass: this.role === "teacher" ? "btn-accent" : "btn-primary",
      trigger: button,
      onConfirm: async () => {
        button.disabled = true;
        button.textContent = "Joining…";
        try {
          const response = await this.reservationService.join(titleId);
          this.waitlistedTitleIds.add(titleId);
          this.markWaitlisted(button);
          this.notify(response?.data?.message || "You joined the waitlist.", "success");
        } catch (error) {
          button.disabled = false;
          button.textContent = "Join waitlist";
          this.notify(error?.message || "Unable to join the waitlist.", "danger");
          throw error;
        }
      },
    });
  } catch {
    return false;
  }
}

notify(message, type = "info") {
  const toast = this.toastService.show(message, type);
  toast?.classList?.add("show");
  if (toast && typeof window.setTimeout === "function") {
    window.setTimeout(() => toast.remove(), 3500);
  }
}

handleWaitlistClick(event) {
  const button = event.target.closest?.("[data-waitlist-title-id]");
  if (!button) return;
  this.confirmWaitlist(button).catch(() => {});
}
```

The confirmation service handles Bootstrap-modal presentation and focus restoration. The `catch` in the delegated handler prevents an async rejection from becoming a browser console error; API failures have already been shown through `notify()`.

- [ ] **Step 6: Run the shared waitlist behavior tests**

Run:

```powershell
node --test frontend/tests/borrower-catalog.test.js
```

Expected result: the pure active-hold, action markup, and confirmation tests pass after the card hook and role wiring are completed in Task 3. If only tests that require role/template changes fail, continue to Task 3; no failure may be caused by a syntax error.

---

### Task 3: Wire role cards, delegated events, and catalog toast mounts

**Files:**
- Modify: `frontend/app/shared/pages/borrower-search.page.js`
- Modify: `frontend/features/student/pages/search/student-search.page.js`
- Modify: `frontend/features/teacher/pages/borrow/teacher-borrow.page.js`
- Modify: `frontend/features/student/pages/search/search.html`
- Modify: `frontend/features/teacher/pages/borrow/borrow.html`

**Interfaces:**
- Consumes: shared `waitlistAction(book)`, `confirmWaitlist(button)`, and role state from Task 2.
- Produces: one unavailable-card waitlist action in both catalogs, delegated event handling for dynamically rendered recommendations and catalog pages, and visible toast feedback mounts.

- [ ] **Step 1: Route unavailable shared actions to the waitlist renderer**

In `BorrowerSearchPage.bookAction(book)`, preserve the borrowed branch and replace the unavailable branch with:

```js
bookAction(book) {
  const availableQuantity = Number(book.available_quantity ?? (book.status === "Available" ? 1 : 0));
  const borrowed = Boolean(book.already_borrowed);
  return borrowed
    ? '<span class="badge bg-info w-100 py-2">&#128214; You have this</span>'
    : availableQuantity > 0
      ? `<button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#borrowModal" data-title-id="${this.escapeHtml(book.title_id ?? book.id)}" data-title="${this.escapeHtml(book.title || "")}" data-author="${this.escapeHtml(book.author || "Unknown Author")}" data-available-quantity="${this.escapeHtml(book.available_quantity ?? 1)}" data-book-barcode="${this.escapeHtml(book.barcode || "")}" title="Add this title">Add to Borrow Cart</button>`
      : this.waitlistAction(book);
}
```

- [ ] **Step 2: Add delegated waitlist listeners to both dynamic card hosts**

In `bindEvents()`, after the existing catalog pagination listener, add:

```js
this.recommendationResults.addEventListener("click", (event) => this.handleWaitlistClick(event));
this.results.addEventListener("click", (event) => this.handleWaitlistClick(event));
```

These listeners must be attached to the stable hosts, not individual cards, because `renderRecommendations()` and `renderCatalog()` replace their contents.

- [ ] **Step 3: Provide student and teacher roles**

Add `role: "student",` to the student constructor configuration in `frontend/features/student/pages/search/student-search.page.js`.

Add `role: "teacher",` to the teacher constructor configuration in `frontend/features/teacher/pages/borrow/teacher-borrow.page.js`.

- [ ] **Step 4: Update the teacher unavailable action without duplicating card actions**

In `TeacherBorrowPage.bookAction(book)`, preserve the borrowed branch and custom teacher available button, but replace the unavailable return with `return this.waitlistAction(book);`:

```js
bookAction(book) {
  const availableQuantity = Number(book.available_quantity ?? (book.status === "Available" ? 1 : 0));
  if (Boolean(book.already_borrowed)) {
    return '<span class="badge bg-info w-100 py-2">&#128214; You have this</span>';
  }
  if (availableQuantity <= 0) {
    return this.waitlistAction(book);
  }
  return `<button type="button" class="btn btn-accent teacher-search-card__action w-100" data-bs-toggle="modal" data-bs-target="#borrowModal" data-title-id="${this.escapeHtml(book.title_id ?? book.id)}" data-title="${this.escapeHtml(book.title || "")}" data-author="${this.escapeHtml(book.author || "Unknown Author")}" data-available-quantity="${this.escapeHtml(book.available_quantity ?? 1)}" data-book-barcode="${this.escapeHtml(book.barcode || "")}" title="Add this title">Add to Borrow Cart</button>`;
}
```

- [ ] **Step 5: Add toast hosts to both catalog templates**

Place this block inside each catalog page’s `.content` root, after the catalog section and before the borrow modal:

```html
<div
  id="toast-host"
  class="toast-container position-fixed bottom-0 end-0 p-3"
  style="z-index: 1090"
></div>
```

Keep the existing borrow modal IDs and structure unchanged.

- [ ] **Step 6: Run the focused role contracts**

Run:

```powershell
node --test frontend/tests/borrower-catalog.test.js frontend/tests/borrower-reservations.test.js frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
```

Expected result: all focused waitlist, reservation, student, and teacher tests pass. The teacher catalog must continue to expose `teacher-search-card__action` for available cards, while unavailable cards use the shared waitlist action exactly once.

- [ ] **Step 7: Commit the implementation slice**

```powershell
git add frontend/app/shared/pages/borrower-search.page.js frontend/features/student/pages/search/student-search.page.js frontend/features/teacher/pages/borrow/teacher-borrow.page.js frontend/features/student/pages/search/search.html frontend/features/teacher/pages/borrow/borrow.html
git commit -m "feat: add catalog waitlist actions"
```

---

### Task 4: Preserve role-specific waitlist presentation

**Files:**
- Modify: `frontend/assets/css/student-search.css` only if the student action needs scoped overrides.
- Modify: `frontend/assets/css/teacher-search.css` only if the teacher action needs scoped overrides.

**Interfaces:**
- Consumes: `btn-outline-primary`, `btn-outline-secondary`, existing student Organic tokens, and existing teacher Swiss tokens.
- Produces: responsive, readable waitlist actions without changing the established catalog anchor.

- [ ] **Step 1: Inspect existing role action rules**

Confirm that the shared `.btn-outline-primary` and `.btn-outline-secondary` rules in `frontend/assets/css/style.css` provide visible contrast on both card backs. Confirm the student and teacher page selectors keep card actions inside their role surfaces.

- [ ] **Step 2: Add only required scoped rules**

If the inspection shows no clipping or contrast issue, leave both search stylesheets unchanged. If an override is required, add only these role-scoped rules:

```css
.student-search-page .student-search-card [data-waitlist-title-id],
.student-search-page .student-recommended-card [data-waitlist-title-id],
.teacher-search-page .teacher-search-card [data-waitlist-title-id],
.teacher-search-page .teacher-recommended-card [data-waitlist-title-id] {
  min-height: 38px;
}
```

Do not add a new font, palette, texture, or navigation selector.

- [ ] **Step 3: Run the style and catalog contracts**

Run:

```powershell
node --test frontend/tests/borrower-catalog.test.js frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
git diff --check
```

Expected result: focused tests pass and `git diff --check` reports no whitespace errors.

- [ ] **Step 4: Commit styling only if changed**

If CSS changed:

```powershell
git add frontend/assets/css/student-search.css frontend/assets/css/teacher-search.css
git commit -m "style: keep catalog waitlist actions role scoped"
```

If CSS did not change, do not create an empty commit.

---

### Task 5: Verify, review, and finish the branch

**Files:**
- No new source files; verify all implementation and test files from Tasks 1–4.

**Interfaces:**
- Consumes: completed shared waitlist controller, role wiring, templates, existing reservation backend, and focused contracts.
- Produces: a regression-safe waitlist action available from both borrower catalog pages.

- [ ] **Step 1: Run the focused waitlist and reservation suite**

```powershell
node --test frontend/tests/borrower-catalog.test.js frontend/tests/borrower-reservations.test.js frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
```

Expected: all focused tests pass with zero failures.

- [ ] **Step 2: Run the complete frontend suite**

```powershell
npm test
```

Expected: all frontend tests pass with zero failures, including existing confirmation, toast, borrower catalog, modal, dashboard reservation, and role-boundary tests.

- [ ] **Step 3: Run syntax and whitespace verification**

```powershell
$syntaxFailed = $false
Get-ChildItem -Path frontend -Recurse -Filter *.js | ForEach-Object {
  node --check $_.FullName
  if ($LASTEXITCODE -ne 0) { $syntaxFailed = $true }
}
if ($syntaxFailed) { exit 1 }
git diff --check
```

Expected: no JavaScript syntax errors and no whitespace errors.

- [ ] **Step 4: Review the final behavior boundaries**

Confirm by inspection that:

- Both Student Search and Teacher Borrow show `Join waitlist` only for unavailable, unreserved titles.
- Clicking `Join waitlist` opens the existing confirmation modal.
- Canceling makes no POST request.
- Confirming posts the title ID to the correct role-specific `/holds` endpoint with CSRF handled by `ApiClient`.
- The successful card becomes `On waitlist` and cannot be clicked again.
- Duplicate active holds loaded on startup render as `On waitlist` after refresh.
- API failures restore the button and show a danger toast.
- Available cards still open their existing borrow modal and teacher cards have one available action.
- Dashboard `Your holds` remains intact for queue position, claim, and cancellation.
- No backend source files or unrelated root files changed.

- [ ] **Step 5: Commit any final implementation changes**

```powershell
git status --short
git diff --check
git log --oneline -5
```

If verification required a code adjustment, rerun the focused and complete suites before committing the adjustment:

```powershell
git add frontend/app/shared/pages/borrower-search.page.js frontend/features/student/pages/search/student-search.page.js frontend/features/teacher/pages/borrow/teacher-borrow.page.js frontend/features/student/pages/search/search.html frontend/features/teacher/pages/borrow/borrow.html frontend/assets/css/student-search.css frontend/assets/css/teacher-search.css frontend/tests/borrower-catalog.test.js frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
git commit -m "feat: complete borrower catalog waitlist flow"
```

Then use `superpowers:finishing-a-development-branch` to present the integration choices. Do not merge, push, or remove the worktree until the user chooses an option.
