# Student Activity Logs Organic Design

## Goal

Normalize the student activity-logs page so its presentation belongs to the existing Organic student experience, without changing teacher activity logs or activity behavior.

## Context

Student and teacher activity pages share the same `borrower-activity.css` stylesheet and the same activity timeline renderer. That shared surface currently uses a flat, cool, squared treatment that reads closer to the teacher dashboard than the Organic student dashboard. The student page already has a distinct `student-activity` body class and `data-page="student-activity"` root, which can safely host a student-only presentation layer.

## Scope

In scope:

- Add `frontend/assets/css/student-activity.css` for student-only Organic presentation rules.
- Load that stylesheet after `borrower-activity.css` from the student activity template only.
- Preserve existing activity markup hooks, renderer classes, pagination controls, PDF export, labels, and API behavior.
- Keep the student activity page usable at the repository’s 290px minimum viewport target.
- Add frontend contract coverage for stylesheet ownership, Organic markers, teacher isolation, and the student stylesheet link.

Out of scope:

- Do not modify `frontend/assets/css/borrower-activity.css`.
- Do not modify the teacher activity template or teacher activity controller.
- Do not modify the shared activity renderer or activity page controller.
- Do not change activity labels, data formatting, API endpoints, pagination semantics, or print behavior.

## Visual direction

Use the existing Organic student dashboard system:

- Typography: Fraunces for the activity masthead and Epilogue for activity content.
- Surfaces: the existing application background and card tokens, rounded 16–28px surfaces, and restrained soft depth.
- Accents: navy/deep text hierarchy, teal primary controls, and gold status/timeline emphasis.
- Timeline: retain the current activity row content and order, but give each row a soft student surface, rounded marker, clearer label/detail/time hierarchy, and status pills that remain readable.
- Controls: retain Previous, Next, and Export PDF semantics while giving them the student pill/button treatment.

## Isolation contract

The new stylesheet must scope its presentation to `.student-activity` or `[data-page="student-activity"]`. It must not contain teacher selectors. The student activity template must be the only activity template that links `student-activity.css`; the teacher template continues to load only the shared activity stylesheet. The implementation test will verify both the student link and teacher link absence.

## Behavior invariants

- Student activity continues to load `/scan2borrow/api/student/activity`.
- Teacher activity continues to load `/scan2borrow/api/teacher/activity`.
- The shared `BorrowerActivityPage` and `ActivityTimelineComponent` remain unchanged.
- Existing activity IDs remain present: `activity-title`, `activity-description`, `activity-range`, `activity-pagination`, `activity-previous`, `activity-next`, `export-activity-pdf`, and `activity-timeline`.
- Pagination remains ten rows per page and PDF export still prints the complete history.
- Empty and error states remain available and text-safe.
- Teacher activity logs remain visually and behaviorally unchanged.

## Verification

- Add a failing frontend contract test for the student stylesheet link, Organic markers, 290px guard, and teacher isolation.
- Run the focused activity tests through a red-green cycle.
- Run `npm.cmd test` after implementation.
- Run `git diff --check` and confirm only the student activity template, student activity stylesheet, its tests, and this design record are changed.

## Acceptance criteria

1. Student activity logs visually match the existing Organic student dashboard language.
2. Teacher activity logs remain unchanged by the implementation.
3. Activity renderer behavior, pagination, PDF export, and API contracts remain intact.
4. Student activity logs remain usable at 290px.
5. Focused and full frontend test suites pass with fresh output.
