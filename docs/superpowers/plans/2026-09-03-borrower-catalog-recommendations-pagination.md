# Borrower Catalog Recommendations and Pagination Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Add a five-book Recommended section and a paginated All books view to both borrower catalog pages.

**Architecture:** Extend the shared `BorrowerSearchPage` controller with recommendation loading, All books visibility state, ten-item pagination, range text, and page navigation. Keep the existing student and teacher subclasses responsible for their role-specific book-card and borrow-modal presentation, and keep the existing role-specific book endpoints unchanged.

**Tech Stack:** Vanilla ES modules, existing browser `fetch` APIs, Bootstrap 5 modal behavior, existing student Organic and teacher Swiss CSS surfaces, Node’s built-in test runner.

## Global Constraints

- Student `Search Books` and teacher `Borrow Books` show exactly five available recommendations before the All books control.
- `Show all books` reveals the complete catalog in pages of ten and displays ranges such as `1-10 of 42`.
- Searching or filtering opens All books at page one and preserves the existing query parameters.
- Student keeps the Organic visual surface; teacher keeps the Swiss visual surface.
- Use the existing `/scan2borrow/api/student/books` and `/scan2borrow/api/teacher/books` endpoints; no backend source changes are expected.
- Keep the existing Add to Borrow Cart, barcode lookup, and role-specific borrow modal behavior.
- Escape book values before HTML insertion and use standard labels: `Recommended`, `Show all books`, `Hide all books`, `All books`, `Previous`, and `Next`.
- Do not claim recommendations are personalized; recommendations are five available titles selected by the current catalog ordering.

---

### Task 1: Add failing catalog contracts

**Files:**
- Create: `frontend/tests/borrower-catalog.test.js`
- Modify: `frontend/tests/student-library-surfaces.test.js`
- Modify: `frontend/tests/teacher-borrow-history-surfaces.test.js`

**Interfaces:**
- Consumes: `BorrowerSearchPage` and both existing catalog templates/controllers.
- Produces: failing contracts for the five-item recommendation query, ten-item catalog query, range/pager state, new template mounts, and shared controller boundaries.

- [ ] **Step 1: Create the shared catalog behavior tests**

Create `frontend/tests/borrower-catalog.test.js` with:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { BorrowerSearchPage } from '../app/shared/pages/borrower-search.page.js';

const root = path.resolve('frontend');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('borrower catalog templates expose recommendations and an all-books pager', () => {
  for (const file of [
    'features/student/pages/search/search.html',
    'features/teacher/pages/borrow/borrow.html',
  ]) {
    const source = read(file);
    assert.match(source, /id="recommendation-panel"/);
    assert.match(source, /id="book-recommendations"/);
    assert.match(source, /id="show-all-books"/);
    assert.match(source, /Show all books/);
    assert.match(source, /id="all-books-panel"/);
    assert.match(source, /id="catalog-range"/);
    assert.match(source, /id="book-pagination"/);
  }
});

test('shared catalog queries use five recommendations and ten catalog books', () => {
  const context = {
    params: new URLSearchParams('search=code&category_name=Programming'),
    pageSize: 10,
    recommendationSize: 5,
  };
  const recommendations = BorrowerSearchPage.prototype.recommendationQuery.call(context);
  const catalog = BorrowerSearchPage.prototype.catalogQuery.call(context, 3);

  assert.equal(recommendations.get('status'), 'Available');
  assert.equal(recommendations.get('page'), '1');
  assert.equal(recommendations.get('per_page'), '5');
  assert.equal(recommendations.get('sort'), 'created_at');
  assert.equal(recommendations.get('dir'), 'desc');
  assert.equal(catalog.get('search'), 'code');
  assert.equal(catalog.get('category_name'), 'Programming');
  assert.equal(catalog.get('page'), '3');
  assert.equal(catalog.get('per_page'), '10');
});

test('catalog range and pager state cover full, partial, and empty pages', () => {
  const context = { pageSize: 10 };
  const range = BorrowerSearchPage.prototype.rangeLabel.bind(context);
  const pager = BorrowerSearchPage.prototype.paginationState.bind(context);

  assert.equal(range(42, 1), '1-10 of 42');
  assert.equal(range(42, 5), '41-42 of 42');
  assert.equal(range(0, 1), '0-0 of 0');
  assert.deepEqual(pager(42, 1), { page: 1, pages: 5, previous: false, next: true });
  assert.deepEqual(pager(42, 5), { page: 5, pages: 5, previous: true, next: false });
});

test('catalog query detection distinguishes the landing view from filtered intent', () => {
  assert.equal(BorrowerSearchPage.prototype.hasCatalogQuery.call({ params: new URLSearchParams() }), false);
  assert.equal(BorrowerSearchPage.prototype.hasCatalogQuery.call({ params: new URLSearchParams('search=history') }), true);
  assert.equal(BorrowerSearchPage.prototype.hasCatalogQuery.call({ params: new URLSearchParams('status=Available') }), true);
});
```

- [ ] **Step 2: Extend existing surface contracts**

In `frontend/tests/student-library-surfaces.test.js`, extend the student catalog boundary test with:

```js
assert.match(template, /id="recommendation-panel"/);
assert.match(template, /id="show-all-books"/);
assert.match(template, /id="all-books-panel"/);
assert.match(template, /id="book-pagination"/);
```

In `frontend/tests/teacher-borrow-history-surfaces.test.js`, add the same four assertions to the teacher catalog test using the teacher borrow template.

- [ ] **Step 3: Run the red checkpoint**

Run:

```powershell
node --test frontend/tests/borrower-catalog.test.js frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
```

Expected: FAIL because the shared query/range methods and the recommendation/pagination mounts do not exist yet. Existing unrelated catalog tests must continue to pass.

---

### Task 2: Implement shared recommendation and pagination behavior

**Files:**
- Modify: `frontend/app/shared/pages/borrower-search.page.js`

**Interfaces:**
- Consumes: role-specific `this.api`, `this.params`, existing `fetch` response envelopes, and existing `bookCard(book)` rendering.
- Produces: `recommendationQuery()`, `catalogQuery(page)`, `hasCatalogQuery()`, `rangeLabel(total, page)`, `paginationState(total, page)`, `loadRecommendations()`, `loadCatalog(page)`, `renderRecommendations(books)`, `renderCatalog(data)`, `setAllBooksVisible(visible)`, and delegated page/toggle handlers.

- [ ] **Step 1: Add catalog state and DOM references**

After the existing `this.results` assignment in the constructor, add:

```js
this.recommendationPanel = document.getElementById('recommendation-panel');
this.recommendationResults = document.getElementById('book-recommendations');
this.showAllBooksButton = document.getElementById('show-all-books');
this.allBooksPanel = document.getElementById('all-books-panel');
this.catalogRange = document.getElementById('catalog-range');
this.pagination = document.getElementById('book-pagination');
this.pageSize = 10;
this.recommendationSize = 5;
this.catalogPage = 1;
this.catalogTotal = 0;
this.catalogRequestId = 0;
```

- [ ] **Step 2: Add pure query and pagination helpers**

Add these methods before `load()`:

```js
hasCatalogQuery() {
  return ['search', 'category_name', 'status', 'floor', 'sort']
    .some((name) => (this.params.get(name) || '') !== '');
}

recommendationQuery() {
  return new URLSearchParams({
    status: 'Available',
    page: '1',
    per_page: String(this.recommendationSize || 5),
    sort: 'created_at',
    dir: 'desc',
  });
}

catalogQuery(page = 1) {
  const query = new URLSearchParams(this.params);
  query.set('page', String(Math.max(1, Number(page) || 1)));
  query.set('per_page', String(this.pageSize || 10));
  return query;
}

rangeLabel(total, page) {
  const count = Math.max(0, Number(total) || 0);
  if (count === 0) return '0-0 of 0';
  const currentPage = Math.max(1, Number(page) || 1);
  const start = ((currentPage - 1) * (this.pageSize || 10)) + 1;
  const end = Math.min(currentPage * (this.pageSize || 10), count);
  return `${start}-${end} of ${count}`;
}

paginationState(total, page) {
  const count = Math.max(0, Number(total) || 0);
  const pages = Math.max(1, Math.ceil(count / (this.pageSize || 10)));
  const currentPage = Math.min(Math.max(1, Number(page) || 1), pages);
  return {
    page: currentPage,
    pages,
    previous: currentPage > 1,
    next: currentPage < pages,
  };
}
```

- [ ] **Step 3: Split initial recommendation loading from catalog loading**

Replace the current `load()` method with:

```js
load() {
  const filtered = this.hasCatalogQuery();
  this.setAllBooksVisible(filtered);
  this.recommendationPanel.hidden = filtered;
  if (filtered) {
    this.loadCatalog(Number(this.params.get('page') || 1));
    return;
  }
  this.renderRecommendationsLoading();
  this.loadRecommendations();
}

loadRecommendations() {
  const query = this.recommendationQuery().toString();
  fetch(`${this.api}?${query}`, { headers: { 'X-Requested-With': 'fetch' } })
    .then((response) => response.json().then((payload) => ({ ok: response.ok && payload.ok, payload })))
    .then(({ ok, payload }) => {
      if (!ok) throw new Error(payload.message || 'Unable to load recommendations.');
      this.renderRecommendations(payload.data?.books || []);
    })
    .catch(() => this.renderRecommendationsError());
}

loadCatalog(page = 1) {
  const requestId = ++this.catalogRequestId;
  this.catalogPage = Math.max(1, Number(page) || 1);
  this.setAllBooksVisible(true);
  this.renderCatalogLoading();
  const query = this.catalogQuery(this.catalogPage).toString();
  fetch(`${this.api}?${query}`, { headers: { 'X-Requested-With': 'fetch' } })
    .then((response) => response.json().then((payload) => ({ ok: response.ok && payload.ok, payload })))
    .then(({ ok, payload }) => {
      if (!ok) throw new Error(payload.message || 'Unable to load books.');
      if (requestId !== this.catalogRequestId) return;
      const data = payload.data || {};
      const total = Number(data.total || 0);
      const lastPage = Math.max(1, Math.ceil(total / this.pageSize));
      if (total > 0 && this.catalogPage > lastPage) {
        this.loadCatalog(lastPage);
        return;
      }
      this.render(data);
    })
    .catch((error) => {
      if (requestId === this.catalogRequestId) this.renderCatalogError(error.message);
    });
}
```

- [ ] **Step 4: Render recommendations, the catalog range, and pagination**

Keep the existing filter-option and active-filter logic in `render(data)`, then replace its result-count/render block with:

```js
this.catalogTotal = Number(data.total || 0);
this.renderCatalog(data);
```

Add the following methods:

```js
renderRecommendationsLoading() {
  this.recommendationResults.innerHTML = `<div class="${this.classPrefix}-library-state"><strong>Loading recommendations...</strong></div>`;
}

renderRecommendationsError() {
  this.recommendationResults.innerHTML = `<div class="${this.classPrefix}-library-state ${this.classPrefix}-library-state--error"><strong>Recommendations are unavailable right now.</strong></div>`;
}

renderRecommendations(books) {
  this.recommendationResults.replaceChildren();
  if (!books.length) {
    this.recommendationResults.innerHTML = `<div class="${this.classPrefix}-library-state"><strong>No recommended books are available right now.</strong></div>`;
    return;
  }
  const grid = document.createElement('div');
  grid.className = `row g-4 ${this.classPrefix}-recommended-grid`;
  books.slice(0, this.recommendationSize).forEach((book) => {
    const card = this.bookCard(book);
    card.classList.add(`${this.classPrefix}-recommended-card`);
    grid.appendChild(card);
  });
  this.recommendationResults.appendChild(grid);
}

renderCatalogLoading() {
  this.catalogRange.textContent = 'Loading...';
  this.pagination.replaceChildren();
  this.results.innerHTML = `<div class="${this.classPrefix}-library-state"><strong>Loading books...</strong></div>`;
}

renderCatalogError(message) {
  this.catalogRange.textContent = '0-0 of 0';
  this.pagination.replaceChildren();
  this.results.innerHTML = `<div class="${this.classPrefix}-library-state ${this.classPrefix}-library-state--error"><strong>We couldn't load the catalog</strong><p class="text-muted small mb-0">${this.escapeHtml(message)}</p></div>`;
}

renderCatalog(data) {
  const books = data.books || [];
  const total = Number(data.total || books.length);
  const state = this.paginationState(total, this.catalogPage);
  this.catalogTotal = total;
  this.catalogRange.textContent = this.rangeLabel(total, state.page);
  this.renderActiveFilters();
  this.results.replaceChildren();
  if (!books.length) {
    this.results.innerHTML = `<div class="${this.classPrefix}-library-state"><div class="${this.classPrefix}-library-state__icon" aria-hidden="true">&#128233;</div><strong>No books found</strong><p class="text-muted small mb-0">Try adjusting your search or filters.</p></div>`;
    this.renderPagination(state);
    return;
  }
  const grid = document.createElement('div');
  grid.className = 'row g-4';
  books.forEach((book) => grid.appendChild(this.bookCard(book)));
  this.results.appendChild(grid);
  this.renderPagination(state);
}

renderPagination(state) {
  this.pagination.replaceChildren();
  if (state.pages <= 1) return;
  this.pagination.innerHTML = `<div class="borrower-catalog__pagination-controls"><button type="button" class="btn btn-outline-secondary btn-sm" data-catalog-page="${state.page - 1}" ${state.previous ? '' : 'disabled'}>Previous</button><span aria-live="polite">Page ${state.page} of ${state.pages}</span><button type="button" class="btn btn-outline-secondary btn-sm" data-catalog-page="${state.page + 1}" ${state.next ? '' : 'disabled'}>Next</button></div>`;
}

setAllBooksVisible(visible) {
  this.allBooksPanel.hidden = !visible;
  this.showAllBooksButton.setAttribute('aria-expanded', String(visible));
  this.showAllBooksButton.textContent = visible ? 'Hide all books' : 'Show all books';
}
```

- [ ] **Step 5: Add delegated toggle and pager events**

In `bindEvents()`, add:

```js
this.showAllBooksButton.addEventListener('click', () => {
  const visible = !this.allBooksPanel.hidden;
  this.setAllBooksVisible(!visible);
  if (!visible) this.loadCatalog(1);
});

this.pagination.addEventListener('click', (event) => {
  const button = event.target.closest?.('[data-catalog-page]');
  if (!button || button.disabled) return;
  this.loadCatalog(Number(button.dataset.catalogPage));
});
```

Update the existing search form submit handler so it removes stale page state before navigating:

```js
this.form.addEventListener('submit', (event) => {
  event.preventDefault();
  const query = new URLSearchParams(new FormData(this.form));
  query.delete('page');
  window.location.href = this.form.action + '?' + query.toString();
});
```

- [ ] **Step 6: Run the shared catalog tests**

Run:

```powershell
node --test frontend/tests/borrower-catalog.test.js
```

Expected: all shared query, range, and template contracts pass after the templates are present; the full rendering behavior is covered by the existing browser-boundary contracts and the later full suite.

---

### Task 3: Add recommendation and All books markup to both pages

**Files:**
- Modify: `frontend/features/student/pages/search/search.html`
- Modify: `frontend/features/teacher/pages/borrow/borrow.html`

**Interfaces:**
- Consumes: the shared controller IDs from Task 2.
- Produces: role-styled recommendation and All books mounts without changing existing search form fields or borrow modal IDs.

- [ ] **Step 1: Add the student recommendation block before the filters**

Immediately after the student masthead, add:

```html
<section id="recommendation-panel" class="student-library-panel student-search-recommendations mb-4">
  <div class="student-library-panel__header student-search-recommendations__header">
    <div>
      <div class="student-library-eyebrow">Recommended</div>
      <h3 class="mb-1">Recommended books</h3>
      <p class="text-muted small mb-0">Five available titles from the current catalog.</p>
    </div>
  </div>
  <div id="book-recommendations" class="student-search-recommendations__state"></div>
  <div class="student-search-recommendations__footer">
    <button type="button" id="show-all-books" class="btn btn-primary" aria-controls="all-books-panel" aria-expanded="false">Show all books</button>
  </div>
</section>
```

- [ ] **Step 2: Add the student All books panel around the existing result panel**

Replace the existing student result wrapper with:

```html
<section id="all-books-panel" class="student-library-panel student-search-results" hidden>
  <div class="student-library-panel__header student-search-results__header">
    <div>
      <div class="student-library-eyebrow">Catalog</div>
      <h3 class="mb-1">All books</h3>
      <strong id="book-count">0</strong>
      <span class="text-muted" id="book-count-label">books found</span>
      <br /><small class="text-muted" id="trending-label">Showing all books</small>
    </div>
    <div class="text-muted small" id="availability-label"></div>
    <span id="catalog-range" class="student-search-results__range">0-0 of 0</span>
  </div>
  <div id="book-results" class="student-search-results__state">
    <div class="student-library-state"><strong>Show all books to browse the catalog.</strong></div>
  </div>
  <nav id="book-pagination" class="student-search-results__pagination" aria-label="All books pages"></nav>
</section>
```

Keep the existing `searchForm`, `active-filters`, and borrow modal markup unchanged.

- [ ] **Step 3: Add the teacher recommendation block before the filters**

Immediately after the teacher masthead, add:

```html
<section id="recommendation-panel" class="teacher-library-panel teacher-search-recommendations mb-4">
  <div class="teacher-library-panel__header teacher-search-recommendations__header">
    <div>
      <div class="teacher-library-eyebrow">Recommended</div>
      <h3 class="mb-1">Recommended books</h3>
      <p class="text-muted small mb-0">Five available titles from the current catalog.</p>
    </div>
  </div>
  <div id="book-recommendations" class="teacher-search-recommendations__state"></div>
  <div class="teacher-search-recommendations__footer">
    <button type="button" id="show-all-books" class="btn btn-primary" aria-controls="all-books-panel" aria-expanded="false">Show all books</button>
  </div>
</section>
```

- [ ] **Step 4: Add the teacher All books panel around the existing result panel**

Replace the existing teacher result wrapper with:

```html
<section id="all-books-panel" class="teacher-library-panel teacher-search-results" hidden>
  <div class="teacher-library-panel__header teacher-search-results__header">
    <div>
      <div class="teacher-library-eyebrow">Catalog</div>
      <h3 class="mb-1">All books</h3>
      <strong id="book-count">0</strong>
      <span class="text-muted" id="book-count-label">books found</span>
      <br /><small class="text-muted" id="trending-label">Showing all books</small>
    </div>
    <div class="text-muted small" id="availability-label"></div>
    <span id="catalog-range" class="teacher-search-results__range">0-0 of 0</span>
  </div>
  <div id="book-results" class="teacher-search-results__state">
    <div class="teacher-library-state"><strong>Show all books to browse the catalog.</strong></div>
  </div>
  <nav id="book-pagination" class="teacher-search-results__pagination" aria-label="All books pages"></nav>
</section>
```

Keep the existing `searchForm`, `active-filters`, and teacher borrow modal markup unchanged.

---

### Task 4: Preserve role-specific borrow actions and add the visual hierarchy

**Files:**
- Modify: `frontend/features/teacher/pages/borrow/teacher-borrow.page.js`
- Modify: `frontend/assets/css/student-search.css`
- Modify: `frontend/assets/css/teacher-search.css`

**Interfaces:**
- Consumes: shared `bookCard(book)` and the existing teacher modal trigger contract.
- Produces: one borrow action per book card, role-specific recommendation treatment, visible range/pager controls, and responsive recommendation/action layouts.

- [ ] **Step 1: Move the teacher card action into the shared card action hook**

Replace `TeacherBorrowPage.bookCard(book)` with:

```js
bookAction(book) {
  const availableQuantity = Number(book.available_quantity ?? (book.status === 'Available' ? 1 : 0));
  if (Boolean(book.already_borrowed)) {
    return '<span class="badge bg-info w-100 py-2">&#128214; You have this</span>';
  }
  if (availableQuantity <= 0) {
    return '<button class="btn btn-outline-secondary w-100" disabled>Unavailable</button>';
  }
  return `<button type="button" class="btn btn-accent teacher-search-card__action w-100" data-bs-toggle="modal" data-bs-target="#borrowModal" data-title-id="${this.escapeHtml(book.title_id ?? book.id)}" data-title="${this.escapeHtml(book.title || '')}" data-author="${this.escapeHtml(book.author || 'Unknown Author')}" data-available-quantity="${this.escapeHtml(book.available_quantity ?? 1)}" data-book-barcode="${this.escapeHtml(book.barcode || '')}" title="Add this title">Add to Borrow Cart</button>`;
}
```

Update the shared `BorrowerSearchPage.bookCard(book)` implementation to call `this.bookAction(book)` instead of building its action inline, and add this base method immediately before `bookCard(book)`:

```js
bookAction(book) {
  const availableQuantity = Number(book.available_quantity ?? (book.status === 'Available' ? 1 : 0));
  const borrowed = Boolean(book.already_borrowed);
  return borrowed
    ? '<span class="badge bg-info w-100 py-2">&#128214; You have this</span>'
    : availableQuantity > 0
      ? `<button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#borrowModal" data-title-id="${this.escapeHtml(book.title_id ?? book.id)}" data-title="${this.escapeHtml(book.title || '')}" data-author="${this.escapeHtml(book.author || 'Unknown Author')}" data-available-quantity="${this.escapeHtml(book.available_quantity ?? 1)}" data-book-barcode="${this.escapeHtml(book.barcode || '')}" title="Add this title">Add to Borrow Cart</button>`
      : '<button class="btn btn-outline-secondary w-100" disabled>Unavailable</button>';
}
```

The card body continues to call `this.bookAction(book)`, so recommendations and All books use the same safe action behavior without duplicate teacher buttons.

- [ ] **Step 2: Add the student Organic recommendation and pager styles**

Append to `frontend/assets/css/student-search.css`:

```css
.student-search-page .student-search-recommendations {
  border-radius: 22px;
}

.student-search-page .student-search-recommendations__header {
  align-items: flex-end;
  border-bottom: 1px solid var(--border-strong);
  display: flex;
  justify-content: space-between;
  margin-bottom: 1rem;
  padding-bottom: .9rem;
}

.student-search-page .student-search-recommendations__state > .row,
.student-search-page .student-search-results__state > .row {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin: 0;
}

.student-search-page .student-search-recommendations__state > .row > *,
.student-search-page .student-search-results__state > .row > * {
  padding: 0;
  width: auto;
}

.student-search-page .student-recommended-card,
.student-search-page .student-recommended-card .book-card {
  height: 320px;
  min-height: 320px;
}

.student-search-page .student-recommended-card .book-face {
  border-radius: 18px;
}

.student-search-page .student-search-recommendations__footer {
  border-top: 1px solid var(--border);
  margin-top: 1rem;
  padding-top: 1rem;
  text-align: center;
}

.student-search-page .student-search-results__header {
  align-items: flex-end;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
}

.student-search-page .student-search-results__range {
  color: var(--borrower-deep);
  font-size: .82rem;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  white-space: nowrap;
}

.student-search-page .borrower-catalog__pagination-controls {
  align-items: center;
  display: flex;
  gap: .75rem;
  justify-content: center;
  padding-top: 1.25rem;
}

@media (max-width: 680px) {
  .student-search-page .student-search-recommendations__header,
  .student-search-page .student-search-results__header {
    align-items: flex-start;
    flex-direction: column;
  }

  .student-search-page .student-search-recommendations__footer .btn {
    width: 100%;
  }

  .student-search-page .student-search-recommendations__state > .row,
  .student-search-page .student-search-results__state > .row {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 3: Add the teacher Swiss recommendation and pager styles**

Append to `frontend/assets/css/teacher-search.css`:

```css
.teacher-search-page .teacher-search-recommendations {
  border-left: 4px solid #002FA7;
  border-radius: 4px;
}

.teacher-search-page .teacher-search-recommendations__header {
  align-items: flex-end;
  border-bottom: 1px solid #D4E0E8;
  display: flex;
  justify-content: space-between;
  margin-bottom: 1rem;
  padding-bottom: .9rem;
}

.teacher-search-page .teacher-search-recommendations__state > .row,
.teacher-search-page .teacher-search-results__state > .row {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin: 0;
}

.teacher-search-page .teacher-search-recommendations__state > .row > *,
.teacher-search-page .teacher-search-results__state > .row > * {
  padding: 0;
  width: auto;
}

.teacher-search-page .teacher-recommended-card,
.teacher-search-page .teacher-recommended-card .book-card {
  height: 300px;
  min-height: 300px;
}

.teacher-search-page .teacher-recommended-card .book-face {
  border-radius: 4px;
}

.teacher-search-page .teacher-search-recommendations__footer {
  border-top: 1px solid #D4E0E8;
  margin-top: 1rem;
  padding-top: 1rem;
  text-align: center;
}

.teacher-search-page .teacher-search-results__header {
  align-items: flex-end;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
}

.teacher-search-page .teacher-search-results__range {
  color: #002FA7;
  font-size: .82rem;
  font-variant-numeric: tabular-nums;
  font-weight: 750;
  white-space: nowrap;
}

.teacher-search-page .borrower-catalog__pagination-controls {
  align-items: center;
  display: flex;
  gap: .75rem;
  justify-content: center;
  padding-top: 1.25rem;
}

@media (max-width: 680px) {
  .teacher-search-page .teacher-search-recommendations__header,
  .teacher-search-page .teacher-search-results__header {
    align-items: flex-start;
    flex-direction: column;
  }

  .teacher-search-page .teacher-search-recommendations__footer .btn {
    width: 100%;
  }

  .teacher-search-page .teacher-search-recommendations__state > .row,
  .teacher-search-page .teacher-search-results__state > .row {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 4: Run the role-specific catalog contracts**

Run:

```powershell
node --test frontend/tests/borrower-catalog.test.js frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
```

Expected: recommendation, All books, pager, and role-specific action contracts pass.

---

### Task 5: Verify, review, and commit the feature

**Files:**
- No additional source files; verify all files changed in Tasks 1–4.

**Interfaces:**
- Consumes: completed shared controller, role templates, role styles, and tests.
- Produces: a regression-safe borrower catalog feature with no backend changes.

- [ ] **Step 1: Run the focused catalog suite**

```powershell
node --test frontend/tests/borrower-catalog.test.js frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
```

Expected: all focused tests pass.

- [ ] **Step 2: Run the complete frontend suite**

```powershell
npm test
```

Expected: all frontend tests pass with zero failures, including existing borrow-cart, role-boundary, and modal tests.

- [ ] **Step 3: Run syntax and diff checks**

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

- Both catalog pages render five recommendations before the All books control.
- The initial unfiltered page keeps All books collapsed.
- Clicking Show all books requests `per_page=10`, reveals the panel, and changes the label to Hide all books.
- Range text uses `0-0 of 0`, `1-4 of 4`, or `1-10 of X` as appropriate.
- Search/filter navigation opens All books at page one.
- Previous and Next preserve the active query and disable at the correct edges.
- Student and teacher cards each retain one functioning Add to Borrow Cart action.
- No backend files changed.

- [ ] **Step 5: Commit the implementation**

```powershell
git add frontend/app/shared/pages/borrower-search.page.js frontend/features/teacher/pages/borrow/teacher-borrow.page.js frontend/features/student/pages/search/search.html frontend/features/teacher/pages/borrow/borrow.html frontend/assets/css/student-search.css frontend/assets/css/teacher-search.css frontend/tests/borrower-catalog.test.js frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
git commit -m "feat: add borrower catalog recommendations and paging"
```
