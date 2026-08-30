# Teacher History UI Design

## Goal

Make the teacher borrowing history a genuinely separate faculty surface instead of a student history composition with teacher overrides.

## Design

The teacher history route keeps its existing teacher-owned HTML entry and `TeacherHistoryPage` controller. The controller continues to reuse only the neutral `BorrowerHistoryPage` data/rendering mechanics, configured with the teacher history API and a teacher-specific presentation prefix.

The teacher history template will use only `teacher-history-*` classes and a standalone `teacher-history.css` stylesheet. It will not load `student-history.css`, `student-library-surfaces.css`, or the duplicated teacher library surface stylesheet. The visual direction is Swiss: white and neutral gray surfaces, Helvetica Neue/system sans typography, Yves Klein blue accents, hairline rules, compact tabular numerals, square controls, and left-aligned ledger hierarchy.

The existing eight-column history table, `history-body` mount, quantities, dates, status badges, fine values, empty state, error state, navbar role, and teacher API endpoint remain unchanged. Only markup class ownership and presentation styles change.

## Acceptance Criteria

- Teacher history HTML, CSS, and controller contain no student history or student library surface references.
- Teacher history has no dependency on `teacher-library-surfaces.css`.
- Shared history rendering produces `teacher-history-*` presentation classes for the teacher configuration.
- Student history remains unchanged and continues to use its student classes and stylesheet.
- Existing frontend and backend test suites pass.
