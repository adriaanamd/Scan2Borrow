# Student Activity Logs Organic Normalization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Normalize only the student activity-logs presentation to the existing Organic student dashboard language while preserving teacher activity and all activity behavior.

**Architecture:** Keep `borrower-activity.css` as the shared baseline for both roles. Add a student-only stylesheet loaded after that baseline from the student activity template; scope every override to `.student-activity` and leave the teacher template, shared renderer, and shared page controller unchanged.

**Tech Stack:** Vanilla HTML/CSS/JavaScript, native Node.js test runner, existing shared activity renderer and page controller.

## Global Constraints

- Student activity logs must use the existing Organic student dashboard system: Fraunces masthead, Epilogue content, navy/teal/gold accents, rounded surfaces, and restrained soft depth.
- Do not modify `frontend/assets/css/borrower-activity.css`.
- Do not modify the teacher activity template or teacher activity controller.
- Do not modify the shared activity renderer or activity page controller.
- Do not change activity labels, data formatting, API endpoints, pagination semantics, or print behavior.
- Student activity logs must remain usable at the repository’s 290px minimum viewport target.
- Use the existing Margaret commit identity: `REuhLys04 <kaleanfreix@gmail.com>`.
- Push the finished `master` branch to `https://github.com/adriaanamd/scan2borrow-copy.git`.

---

### Task 1: Add the failing student activity presentation contract

**Files:**
- Modify: `frontend/tests/borrower-activity.test.js`
- Read-only reference: `frontend/features/student/pages/activity/activity.html`
- Read-only reference: `frontend/features/teacher/pages/activity/activity.html`

**Interfaces:**
- Consumes: the existing `read(relativePath)` helper and role-specific activity templates.
- Produces: a static contract for the student stylesheet link, Organic markers, narrow breakpoint, and teacher isolation.

- [ ] **Step 1: Write the failing test**

Append this test to `frontend/tests/borrower-activity.test.js`:

```js
test('student activity owns an Organic presentation without changing teacher activity', () => {
  const studentHtml = read('features/student/pages/activity/activity.html');
  const teacherHtml = read('features/teacher/pages/activity/activity.html');

  assert.match(studentHtml, /assets\/css\/student-activity\.css/);
  assert.doesNotMatch(teacherHtml, /assets\/css\/student-activity\.css/);

  const studentCss = read('assets/css/student-activity.css');
  assert.match(studentCss, /\.student-activity\s*\{[\s\S]*font-family:\s*Epilogue/);
  assert.match(studentCss, /\.student-activity[\s\S]*font-family:\s*Fraunces/);
  assert.match(studentCss, /\.student-activity[\s\S]*border-radius:\s*22px/);
  assert.match(studentCss, /@media\s*\(max-width:\s*320px\)/);
  assert.doesNotMatch(studentCss, /teacher-activity|borrower-dashboard--teacher/);
});
```

- [ ] **Step 2: Run the focused test to verify it fails**

Run:

```powershell
node --test frontend/tests/borrower-activity.test.js
```

Expected: the existing activity behavior tests pass, and the new test fails at the missing `student-activity.css` link because the student page currently loads only the shared stylesheet.

- [ ] **Step 3: Commit the failing contract**

```powershell
git add frontend/tests/borrower-activity.test.js
git -c user.name='REuhLys04' -c user.email='kaleanfreix@gmail.com' commit -m "test: define Organic student activity presentation"
```

### Task 2: Add the student-only Organic activity surface

**Files:**
- Create: `frontend/assets/css/student-activity.css`
- Modify: `frontend/features/student/pages/activity/activity.html:10`
- Do not modify: `frontend/assets/css/borrower-activity.css`
- Do not modify: `frontend/features/teacher/pages/activity/activity.html`
- Do not modify: `frontend/app/shared/components/activity-timeline/activity-timeline.component.js`
- Do not modify: `frontend/app/shared/pages/borrower-activity.page.js`

**Interfaces:**
- Consumes: the existing `.student-activity` body class, `[data-page="student-activity"]` root, shared `borrower-activity__*` classes, and activity renderer hooks.
- Produces: a student-only stylesheet loaded after the shared activity CSS, with Organic presentation and narrow viewport guards.

- [ ] **Step 1: Load the student stylesheet after the shared baseline**

In `frontend/features/student/pages/activity/activity.html`, keep the shared link and add the student link immediately after it:

```html
    <link href="/scan2borrow/frontend/assets/css/borrower-activity.css" rel="stylesheet" />
    <link href="/scan2borrow/frontend/assets/css/student-activity.css" rel="stylesheet" />
```

Do not add the student stylesheet link to the teacher activity template.

- [ ] **Step 2: Create the scoped Organic stylesheet**

Create `frontend/assets/css/student-activity.css` with this complete content:

```css
.student-activity {
  --student-activity-surface: var(--app-bg);
  --student-activity-panel: var(--card);
  --student-activity-deep: var(--navy);
  --student-activity-primary: var(--primary);
  --student-activity-accent: var(--accent);
  --student-activity-rule: var(--border);
  --student-activity-muted: var(--muted);
  background: var(--student-activity-surface);
  color: var(--student-activity-deep);
  font-family: Epilogue, "Segoe UI", system-ui, sans-serif;
}

.student-activity .borrower-activity__content {
  max-width: 1120px;
  position: relative;
}

.student-activity .borrower-activity__content::before {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120' viewBox='0 0 120 120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.25'/%3E%3C/svg%3E");
  content: "";
  inset: 0;
  opacity: .02;
  pointer-events: none;
  position: absolute;
}

.student-activity .borrower-activity__masthead,
.student-activity .borrower-activity__panel {
  position: relative;
  z-index: 1;
}

.student-activity .borrower-activity__masthead {
  border-left: 0;
  border-bottom: 1px solid var(--student-activity-rule);
  margin-bottom: 24px;
  padding: 0 0 18px;
}

.student-activity .borrower-activity__masthead h2 {
  color: var(--student-activity-deep);
  font-family: Fraunces, Georgia, serif;
  font-size: clamp(1.8rem, 3vw, 2.5rem);
  letter-spacing: -.04em;
  margin: 0 0 6px;
}

.student-activity .borrower-activity__masthead p {
  color: var(--student-activity-muted);
}

.student-activity .borrower-activity__eyebrow {
  color: var(--student-activity-primary);
}

.student-activity .borrower-activity__panel {
  background: var(--student-activity-panel);
  border: 1px solid var(--student-activity-rule);
  border-radius: 22px;
  box-shadow: 0 12px 30px rgba(16, 47, 82, .08);
  padding: 18px 22px 22px;
}

.student-activity .borrower-activity__toolbar {
  align-items: center;
  border-bottom: 1px solid var(--student-activity-rule);
  padding: 0 0 14px;
}

.student-activity .borrower-activity__range {
  color: var(--student-activity-muted);
}

.student-activity .borrower-activity__actions,
.student-activity .borrower-activity__pagination {
  align-items: center;
  display: flex;
  gap: 8px;
}

.student-activity .borrower-activity__actions .btn {
  border-radius: 999px;
  font-weight: 750;
}

.student-activity #export-activity-pdf {
  background: var(--student-activity-accent);
  border-color: var(--student-activity-accent);
  color: var(--student-activity-deep);
}

.student-activity #export-activity-pdf:hover,
.student-activity #export-activity-pdf:focus {
  background: var(--accent-2);
  border-color: var(--accent-2);
  color: var(--student-activity-deep);
}

.student-activity .borrower-activity__list {
  display: grid;
  gap: 10px;
  padding-top: 16px;
}

.student-activity .borrower-activity__item {
  align-items: flex-start;
  background: rgba(255, 255, 255, .72);
  border: 1px solid var(--student-activity-rule);
  border-radius: 16px;
  box-shadow: 0 6px 16px rgba(16, 47, 82, .05);
  display: flex;
  gap: 14px;
  padding: 14px 16px;
}

.student-activity .borrower-activity__item:last-child {
  border-bottom: 1px solid var(--student-activity-rule);
}

.student-activity .borrower-activity__item:focus-within {
  outline: 3px solid rgba(212, 167, 44, .45);
  outline-offset: 3px;
}

.student-activity .activity-rec-cover {
  background: var(--student-activity-primary);
  border-radius: 12px 5px 12px 5px;
  box-shadow: 4px 3px 0 rgba(16, 47, 82, .14);
  flex: 0 0 48px;
  height: 48px;
  width: 48px;
}

.student-activity .borrower-activity__body {
  min-width: 0;
}

.student-activity .borrower-activity__label {
  color: var(--student-activity-deep);
  font-size: .95rem;
  font-weight: 750;
}

.student-activity .borrower-activity__details,
.student-activity .borrower-activity__time {
  color: var(--student-activity-muted);
  overflow-wrap: anywhere;
}

.student-activity .borrower-activity__time {
  font-variant-numeric: tabular-nums;
  margin-top: 4px;
}

.student-activity .activity-rec-status {
  background: var(--sky) !important;
  border-color: var(--border) !important;
  border-radius: 999px;
  color: var(--student-activity-deep) !important;
  flex: none;
  white-space: nowrap;
}

.student-activity .borrower-activity__state {
  color: var(--student-activity-muted);
  margin: 18px 0 4px;
}

.student-activity .borrower-activity__state--error {
  color: var(--danger);
}

@media (max-width: 768px) {
  .student-activity .borrower-activity__panel {
    padding: 16px;
  }

  .student-activity .borrower-activity__toolbar {
    align-items: flex-start;
    flex-direction: column;
  }

  .student-activity .borrower-activity__actions {
    justify-content: space-between;
    width: 100%;
  }

  .student-activity .borrower-activity__item {
    gap: 10px;
  }
}

@media (max-width: 320px) {
  .student-activity .borrower-activity__content {
    padding-left: 10px;
    padding-right: 10px;
  }

  .student-activity .borrower-activity__masthead {
    margin-bottom: 16px;
  }

  .student-activity .borrower-activity__panel {
    border-radius: 16px;
    padding: 12px 10px;
  }

  .student-activity .borrower-activity__actions,
  .student-activity .borrower-activity__pagination {
    align-items: stretch;
    flex-wrap: wrap;
    width: 100%;
  }

  .student-activity .borrower-activity__actions .btn {
    white-space: normal;
  }

  .student-activity .borrower-activity__item {
    border-radius: 14px;
    flex-wrap: wrap;
    padding: 12px;
  }

  .student-activity .borrower-activity__body {
    flex: 1 1 calc(100% - 58px);
  }

  .student-activity .activity-rec-status {
    margin-left: 58px;
    white-space: normal;
  }
}

@media (prefers-reduced-motion: reduce) {
  .student-activity * {
    scroll-behavior: auto !important;
    transition-duration: .01ms !important;
  }
}
```

- [ ] **Step 3: Run the focused activity tests to verify they pass**

Run:

```powershell
node --test frontend/tests/borrower-activity.test.js
```

Expected: all activity tests pass, including the new student Organic presentation contract.

- [ ] **Step 4: Commit the student-only implementation**

```powershell
git add frontend/assets/css/student-activity.css frontend/features/student/pages/activity/activity.html
git -c user.name='REuhLys04' -c user.email='kaleanfreix@gmail.com' commit -m "fix: normalize student activity to Organic UI"
```

### Task 3: Run the complete frontend gate and push `master`

**Files:**
- No further source changes unless verification identifies a concrete regression.

**Interfaces:**
- Consumes: the committed student activity stylesheet, template link, and activity contract test.
- Produces: a clean, verified `master` branch synchronized to the user-owned remote.

- [ ] **Step 1: Run the full frontend suite**

```powershell
npm.cmd test
```

Expected: exit code 0 with zero failures.

- [ ] **Step 2: Check diff hygiene and teacher isolation**

```powershell
git diff --check
git diff --name-only HEAD~2..HEAD
git status --short --branch
```

Expected: only `frontend/assets/css/student-activity.css`, `frontend/features/student/pages/activity/activity.html`, and the activity test appear in the implementation commits; teacher activity files and shared activity files are absent; the working tree is clean after commit.

- [ ] **Step 3: Push the requested branch**

```powershell
git push origin master
```

Expected: `master` updates on `https://github.com/adriaanamd/scan2borrow-copy.git` without force-pushing.

- [ ] **Step 4: Verify the remote head**

```powershell
git status --short --branch
git ls-remote --heads origin master
```

Expected: local `master` tracks `origin/master`, the worktree is clean, and the remote hash matches local `HEAD`.
