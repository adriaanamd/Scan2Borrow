# Show All Books Availability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Keep the student and teacher borrower-catalog “Show all books” toggle clickable regardless of the route or query parameters used to reach the page.

**Architecture:** Keep `BorrowerSearchPage` as the single owner of the toggle, catalog loading, and ARIA/label state. Move the existing button into a persistent controls row outside the recommendations panel in both borrower templates; the controller will then continue to work without a JavaScript behavior change. Add page-specific responsive styles for the new row.

**Tech Stack:** Vanilla HTML, CSS, native browser ES modules, Node.js built-in test runner.

## Global Constraints

- Preserve one `#show-all-books` control per borrower catalog page.
- Preserve the existing `aria-controls`, `aria-expanded`, button labels, query handling, pagination, and catalog error behavior.
- Keep all existing frontend test coverage and run `npm test` before completion.
- Do not change backend code or introduce dependencies.

---

### Task 1: Add the persistent-control regression test

**Files:**
- Modify: `frontend/tests/borrower-catalog.test.js`

**Interfaces:**
- Consumes: Both borrower templates as UTF-8 source strings through the existing `read()` helper.
- Produces: A contract test proving the toggle is outside the conditionally hidden recommendations panel.

- [ ] **Step 1: Write the failing test**

Add this test after the existing template-surface test:

```js
test('show all books controls stay outside hidden recommendation panels', () => {
  for (const [template, controlsClass] of [
    ['features/student/pages/search/search.html', 'student-search-catalog-controls'],
    ['features/teacher/pages/borrow/borrow.html', 'teacher-search-catalog-controls'],
  ]) {
    const source = read(template);
    const controlsStart = source.indexOf(`class="${controlsClass}`);
    const buttonStart = source.indexOf('id="show-all-books"');
    const recommendationStart = source.indexOf('id="recommendation-panel"');
    const recommendationEnd = source.indexOf('</section>', recommendationStart);

    assert.ok(controlsStart >= 0, `${template} should expose persistent catalog controls`);
    assert.ok(buttonStart > controlsStart, `${template} should place the toggle in the controls row`);
    assert.ok(buttonStart < recommendationStart || buttonStart > recommendationEnd, `${template} should keep the toggle outside recommendations`);
  }
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```text
node --test frontend/tests/borrower-catalog.test.js
```

Expected: the existing catalog tests pass, and `show all books controls stay outside hidden recommendation panels` fails because neither template has its persistent controls row yet.

- [ ] **Step 3: Commit the failing test**

```text
git add frontend/tests/borrower-catalog.test.js
git commit -m "test: require persistent show all books controls"
```

### Task 2: Move the toggle into persistent catalog controls

**Files:**
- Modify: `frontend/features/student/pages/search/search.html`
- Modify: `frontend/features/teacher/pages/borrow/borrow.html`
- Modify: `frontend/assets/css/student-search.css`
- Modify: `frontend/assets/css/teacher-search.css`

**Interfaces:**
- Consumes: The existing `BorrowerSearchPage.bindEvents()` lookup of `#show-all-books` and `#all-books-panel`.
- Produces: A persistent controls row in each template with the same button identity and accessibility attributes.

- [ ] **Step 1: Add the student persistent controls row**

Immediately after the student masthead and before `<section id="recommendation-panel"`, add:

```html
          <div class="student-search-catalog-controls mb-4">
            <button type="button" id="show-all-books" class="btn btn-primary" aria-controls="all-books-panel" aria-expanded="false">Show all books</button>
          </div>
```

Remove the existing student `student-search-recommendations__footer` wrapper and its button from inside the recommendations section.

- [ ] **Step 2: Add the teacher persistent controls row**

Immediately after the teacher masthead and before `<section id="recommendation-panel"`, add:

```html
          <div class="teacher-search-catalog-controls mb-4">
            <button type="button" id="show-all-books" class="btn btn-primary" aria-controls="all-books-panel" aria-expanded="false">Show all books</button>
          </div>
```

Remove the existing teacher `teacher-search-recommendations__footer` wrapper and its button from inside the recommendations section.

- [ ] **Step 3: Style the student controls row**

Add to `frontend/assets/css/student-search.css`:

```css
.student-search-page .student-search-catalog-controls {
  display: flex;
  justify-content: flex-end;
}

@media (max-width: 680px) {
  .student-search-page .student-search-catalog-controls .btn {
    width: 100%;
  }
}
```

- [ ] **Step 4: Style the teacher controls row**

Add to `frontend/assets/css/teacher-search.css`:

```css
.teacher-search-page .teacher-search-catalog-controls {
  display: flex;
  justify-content: flex-end;
}

@media (max-width: 680px) {
  .teacher-search-page .teacher-search-catalog-controls .btn {
    width: 100%;
  }
}
```

- [ ] **Step 5: Run the focused test to verify it passes**

Run:

```text
node --test frontend/tests/borrower-catalog.test.js
```

Expected: all tests in `borrower-catalog.test.js` pass, including the persistent-control regression test.

- [ ] **Step 6: Commit the implementation**

```text
git add frontend/features/student/pages/search/search.html frontend/features/teacher/pages/borrow/borrow.html frontend/assets/css/student-search.css frontend/assets/css/teacher-search.css
git commit -m "fix: keep show all books control available"
```

### Task 3: Run the complete repository checks

**Files:**
- No additional files; verification only.

**Interfaces:**
- Consumes: The committed frontend template, style, and contract-test changes.
- Produces: Fresh local verification evidence for the frontend and documented backend gates.

- [ ] **Step 1: Run the complete frontend test suite**

Run:

```text
npm test
```

Expected: every frontend test passes with zero failures.

- [ ] **Step 2: Run the documented PHP test gate**

Run:

```text
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml
```

Expected: PHPUnit passes, or if Composer dependencies are absent, record the exact missing executable and mark this gate unverified without changing backend code.

- [ ] **Step 3: Run the documented PHP static-analysis gate**

Run:

```text
C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon
```

Expected: PHPStan passes, or if Composer dependencies are absent, record the exact missing executable and mark this gate unverified without weakening the gate.

- [ ] **Step 4: Check the final diff and status**

Run:

```text
git diff --check
git status --short --branch
```

Expected: no whitespace errors; only the intended commits are present and the working tree is clean.
