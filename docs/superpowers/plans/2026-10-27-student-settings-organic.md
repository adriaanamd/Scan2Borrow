# Student Settings Organic Normalization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Normalize only the student account-settings presentation to the existing Organic student dashboard language while preserving teacher settings and all settings behavior.

**Architecture:** Keep the student and teacher settings stylesheets independent. Add a contract test beside the existing student settings tests, then replace only the student settings presentation rules with selectors scoped to `[data-page="student-settings"]`; leave the teacher stylesheet and all HTML/JavaScript behavior unchanged.

**Tech Stack:** Vanilla HTML, scoped CSS, native Node.js test runner, existing frontend feature tests.

## Global Constraints

- Student account settings must use the existing Organic student dashboard language: Fraunces headings, Epilogue content, navy/teal/gold accents, rounded surfaces, and softer depth.
- Do not modify `frontend/features/teacher/pages/settings/settings.css`.
- Do not modify teacher settings HTML, JavaScript, services, models, or backend code.
- Preserve all existing student settings markup hooks, form names, IDs, API endpoints, approval states, and photo-upload behavior.
- Student settings must remain usable at the repository’s 290px minimum viewport target.
- Run focused tests before and after implementation, then run `npm.cmd test` and `git diff --check`.

---

### Task 1: Add the failing student Organic presentation contract

**Files:**
- Modify: `frontend/tests/student-settings-approval.test.js`
- Read-only reference: `frontend/features/student/pages/settings/settings.css`
- Read-only reference: `frontend/features/teacher/pages/settings/settings.css`

**Interfaces:**
- Consumes: the existing `readFileSync` setup and student settings root path in `student-settings-approval.test.js`.
- Produces: a static contract proving the student stylesheet contains Organic presentation markers, a 290px guard, and no teacher page scope.

- [ ] **Step 1: Write the failing test**

Append this test to `frontend/tests/student-settings-approval.test.js`:

```js
test('student settings owns an Organic presentation without touching teacher settings', () => {
  const studentCss = fs.readFileSync(path.join(root, 'settings.css'), 'utf8');
  const teacherCss = fs.readFileSync(
    path.resolve(root, '../../../../teacher/pages/settings/settings.css'),
    'utf8',
  );

  assert.match(studentCss, /\[data-page="student-settings"\][\s\S]*font-family:\s*Epilogue/);
  assert.match(studentCss, /\[data-page="student-settings"\][\s\S]*font-family:\s*Fraunces/);
  assert.match(studentCss, /\[data-page="student-settings"\][\s\S]*border-radius:\s*22px/);
  assert.match(studentCss, /@media\s*\(max-width:\s*320px\)/);
  assert.doesNotMatch(studentCss, /data-page="teacher-settings"/);
  assert.match(teacherCss, /data-page="teacher-settings"/);
});
```

- [ ] **Step 2: Run the focused test to verify it fails**

Run:

```powershell
node --test frontend/tests/student-settings-approval.test.js
```

Expected: the existing student settings behavior tests pass, and the new Organic presentation test fails because the student stylesheet currently uses the shared Swiss/profile-blue rules and contains teacher selectors.

- [ ] **Step 3: Commit the failing contract**

```powershell
git add frontend/tests/student-settings-approval.test.js
git -c user.name='REuhLys04' -c user.email='kaleanfreix@gmail.com' commit -m "test: define Organic student settings presentation"
```

### Task 2: Replace only the student settings presentation

**Files:**
- Modify: `frontend/features/student/pages/settings/settings.css`
- Do not modify: `frontend/features/teacher/pages/settings/settings.css`
- Do not modify: `frontend/features/student/pages/settings/settings.html`
- Do not modify: `frontend/features/student/pages/settings/student-settings.page.js`

**Interfaces:**
- Consumes: the unchanged student settings markup classes, IDs, and `[data-page="student-settings"]` root.
- Produces: a student-only Organic stylesheet with the existing two-column desktop structure, narrow stacking, reduced-motion behavior, and 290px protection.

- [ ] **Step 1: Replace teacher-shared selectors with student-scoped Organic rules**

Use this structure in `frontend/features/student/pages/settings/settings.css`, preserving the existing class hooks:

```css
[data-page="student-settings"] {
  --student-settings-surface: var(--app-bg);
  --student-settings-panel: var(--card);
  --student-settings-deep: var(--navy);
  --student-settings-accent: var(--accent);
  --student-settings-primary: var(--primary);
  --student-settings-rule: var(--border);
  --student-settings-muted: var(--muted);
  background: var(--student-settings-surface);
  color: var(--student-settings-deep);
  font-family: Epilogue, "Segoe UI", system-ui, sans-serif;
}

[data-page="student-settings"] h2,
[data-page="student-settings"] h3 {
  font-family: Fraunces, Georgia, serif;
  letter-spacing: -.035em;
}

[data-page="student-settings"] .page-head {
  border-left-color: var(--student-settings-primary);
}

[data-page="student-settings"] .profile-request-shell {
  align-items: start;
  display: grid;
  gap: 24px;
  grid-template-columns: minmax(0, 1fr) 320px;
}

[data-page="student-settings"] .profile-request-card,
[data-page="student-settings"] .profile-request-status {
  background: var(--student-settings-panel);
  border: 1px solid var(--student-settings-rule);
  border-radius: 22px;
  box-shadow: 0 12px 30px rgba(16, 47, 82, .08);
  min-width: 0;
}

[data-page="student-settings"] .profile-request-card {
  padding: clamp(20px, 3vw, 32px);
}

[data-page="student-settings"] .profile-request-card__head {
  align-items: start;
  border-bottom: 1px solid var(--student-settings-rule);
  display: flex;
  gap: 16px;
  justify-content: space-between;
  margin-bottom: 24px;
  padding-bottom: 16px;
}

[data-page="student-settings"] .profile-request-card h3,
[data-page="student-settings"] .profile-request-status h3 {
  color: var(--student-settings-deep);
  font-size: 1.35rem;
  margin: 4px 0 0;
}

[data-page="student-settings"] .profile-request-kicker {
  color: var(--student-settings-primary);
  display: block;
  font-size: .68rem;
  font-weight: 800;
  letter-spacing: .12em;
  text-transform: uppercase;
}

[data-page="student-settings"] .profile-request-lock {
  background: var(--sky);
  border: 1px solid var(--border);
  border-radius: 999px;
  color: var(--student-settings-deep);
  font-size: .72rem;
  padding: 6px 10px;
  white-space: nowrap;
}

[data-page="student-settings"] .profile-request-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

[data-page="student-settings"] .profile-request-grid .form-control {
  border-color: var(--border-strong);
  border-radius: 12px;
  min-height: 46px;
}

[data-page="student-settings"] .profile-request-grid .form-control:focus {
  border-color: var(--student-settings-primary);
  box-shadow: 0 0 0 .2rem rgba(7, 89, 133, .14);
}

[data-page="student-settings"] .profile-request-grid .form-label,
[data-page="student-settings"] .profile-request-photo .form-label {
  color: var(--student-settings-deep);
  font-size: .76rem;
  font-weight: 750;
}

[data-page="student-settings"] .profile-request-readonly {
  background: var(--sky);
  border-left: 4px solid var(--student-settings-accent);
  border-radius: 0 14px 14px 0;
  padding: 12px 14px;
}

[data-page="student-settings"] .profile-request-readonly small,
[data-page="student-settings"] .profile-request-photo-row small {
  color: var(--student-settings-muted);
  display: block;
  font-size: .74rem;
  margin-top: 5px;
}

[data-page="student-settings"] .profile-request-photo-row {
  align-items: center;
  border-top: 1px solid var(--student-settings-rule);
  display: grid;
  gap: 24px;
  grid-template-columns: minmax(0, 1fr) 120px;
  margin-top: 24px;
  padding-top: 20px;
}

[data-page="student-settings"] .profile-request-photo {
  align-items: center;
  background: var(--sky);
  border: 1px solid var(--student-settings-rule);
  border-radius: 18px;
  color: var(--student-settings-muted);
  display: flex;
  height: 120px;
  justify-content: center;
  overflow: hidden;
  text-align: center;
}

[data-page="student-settings"] .profile-request-photo img {
  height: 100%;
  object-fit: cover;
  width: 100%;
}

[data-page="student-settings"] .profile-request-form__foot {
  align-items: center;
  border-top: 1px solid var(--student-settings-rule);
  display: flex;
  gap: 16px;
  justify-content: space-between;
  margin-top: 24px;
  padding-top: 20px;
}

[data-page="student-settings"] .profile-request-note,
[data-page="student-settings"] .profile-request-status p {
  color: var(--student-settings-muted);
  font-size: .84rem;
  line-height: 1.55;
}

[data-page="student-settings"] .profile-request-note {
  margin: 0;
}

[data-page="student-settings"] .profile-request-form__foot .btn {
  background: var(--student-settings-accent);
  border-color: var(--student-settings-accent);
  border-radius: 999px;
  color: var(--student-settings-deep);
  font-weight: 800;
}

[data-page="student-settings"] .profile-request-form__foot .btn:hover,
[data-page="student-settings"] .profile-request-form__foot .btn:focus {
  background: var(--accent-2);
  border-color: var(--accent-2);
  color: var(--student-settings-deep);
}

[data-page="student-settings"] .profile-request-status {
  border-top: 5px solid var(--student-settings-accent);
  padding: 24px;
  position: sticky;
  top: 24px;
}

[data-page="student-settings"] .profile-request-diff {
  border-top: 1px solid var(--student-settings-rule);
  margin-top: 18px;
}

[data-page="student-settings"] .profile-request-diff__row {
  border-bottom: 1px solid var(--student-settings-rule);
  font-size: .8rem;
  padding: 10px 0;
}

@media (max-width: 900px) {
  [data-page="student-settings"] .profile-request-shell {
    grid-template-columns: 1fr;
  }

  [data-page="student-settings"] .profile-request-status {
    position: static;
  }
}

@media (max-width: 620px) {
  [data-page="student-settings"] .profile-request-grid,
  [data-page="student-settings"] .profile-request-photo-row {
    grid-template-columns: 1fr;
  }

  [data-page="student-settings"] .profile-request-form__foot {
    align-items: stretch;
    flex-direction: column;
  }

  [data-page="student-settings"] .profile-request-form__foot .btn {
    width: 100%;
  }
}

@media (max-width: 320px) {
  [data-page="student-settings"] .profile-request-card,
  [data-page="student-settings"] .profile-request-status {
    border-radius: 16px;
    padding: 16px 12px;
  }

  [data-page="student-settings"] .profile-request-card__head {
    align-items: flex-start;
    flex-direction: column;
    gap: 10px;
  }

  [data-page="student-settings"] .profile-request-lock {
    white-space: normal;
  }
}

@media (prefers-reduced-motion: reduce) {
  [data-page="student-settings"] * {
    scroll-behavior: auto !important;
    transition-duration: .01ms !important;
  }
}
