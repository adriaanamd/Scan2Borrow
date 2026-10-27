# Student Account Settings Organic Design

## Goal

Normalize the student account-settings page so its presentation belongs to the existing Organic student experience, without changing teacher account settings or any account-settings behavior.

## Context

The student and teacher settings pages currently expose separate HTML and JavaScript entrypoints, but their settings stylesheets use the same Swiss/teacher visual treatment. The student dashboard already establishes a distinct Organic system with Fraunces headings, Epilogue body copy, navy/teal/gold accents, rounded surfaces, and softer depth.

## Scope

In scope:

- Update `frontend/features/student/pages/settings/settings.css` to be student-only and Organic.
- Preserve all existing student settings markup hooks, form names, IDs, API endpoints, approval states, and photo-upload behavior.
- Preserve narrow-viewport behavior through the existing responsive layout and a student-specific 290px guard where necessary.
- Add frontend contract coverage for the student visual boundary and teacher isolation.

Out of scope:

- Do not modify `frontend/features/teacher/pages/settings/settings.css`.
- Do not modify teacher settings HTML, JavaScript, services, models, or backend code.
- Do not change shared navigation, typography tokens, API payloads, or approval workflow copy.

## Visual direction

Use the existing Organic student dashboard language rather than inventing a second student theme:

- Typography: Fraunces for settings headings and Epilogue for the settings content.
- Surfaces: warm application background, rounded white/soft cards, restrained organic shadows, and the existing border tokens.
- Accents: existing navy/deep tone for hierarchy, primary teal for active controls, and gold accent for protected/status emphasis.
- Structure: a generous editable-profile card paired with a rounded request-status card; retain the two-column desktop layout and stack it on narrow screens.
- Controls: rounded student-style inputs and action button, with clear focus treatment and unchanged labels/semantics.

## Isolation contract

The student stylesheet must scope its settings presentation to `[data-page="student-settings"]` and must not contain teacher settings selectors. The teacher stylesheet remains unchanged and is the sole source of teacher settings presentation. The implementation test will read both stylesheets and fail if the student stylesheet introduces teacher selectors or if the teacher stylesheet is changed in the task diff.

## Behavior invariants

- Existing student settings fields remain present: first name, middle name, last name, email, contact number, course, year level, department, position, profile photo, and protected Student ID.
- Existing student settings element IDs and `data-page="student-settings"` remain unchanged.
- `StudentSettingsPage` continues to load and submit through `StudentProfileChangeService`.
- Pending administrator review continues to disable submission and render requested values.
- Safe profile-photo path handling remains unchanged.
- Teacher settings remains visually and behaviorally unchanged.

## Verification

- Add a failing frontend contract test for Organic typography, student-only scope, rounded surfaces, and narrow settings layout.
- Run the focused student settings tests through a red-green cycle.
- Run `npm.cmd test` after implementation.
- Run `git diff --check` and confirm only student settings presentation, its tests, and this design record are changed.

## Acceptance criteria

1. Student account settings visually matches the existing Organic student dashboard language.
2. Teacher account settings remains unchanged by the implementation.
3. Student settings behavior and markup contracts remain intact.
4. Student settings remains usable at the repository’s 290px minimum viewport target.
5. Focused and full frontend test suites pass with fresh output.
