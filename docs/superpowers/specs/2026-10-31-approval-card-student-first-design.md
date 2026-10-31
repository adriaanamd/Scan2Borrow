# Student-First Approval Cards Design

## Goal

Make the student or borrower name the fastest-scannable piece of information in both staff approval workflows. Book titles remain visible, but act as secondary context.

## Scope

This change covers the approval cards rendered in the staff dashboard's:

- Pending Borrow Approvals modal.
- Return Approvals modal.

It is limited to card markup, card styling, and frontend contract coverage. Existing modal structure, counters, API calls, approval decisions, return evidence, metadata, and action controls remain unchanged.

## Approved direction

Use a shared identity hierarchy across both card types:

1. Large, high-contrast student/borrower name as the primary heading.
2. Smaller book title as supporting context directly below the name.
3. Author, barcode, dates, quantity, status, evidence, and decision controls retain their current supporting roles.

The pending borrow card keeps its existing book cover and dark card surface. The return card keeps its existing evidence photo and light card surface. The two cards should use parallel semantic class names for the borrower and book-title levels so the hierarchy is easy to maintain and test.

## Layout behavior

- Student/borrower names use approximately 1.35–1.5rem text with a strong weight and enough line height to wrap long names cleanly.
- Book titles use a smaller, muted treatment around the existing supporting-text scale; they must remain readable and not be truncated by a fixed-height content region.
- Existing metadata stays below the identity block, and existing action/evidence areas remain available.
- The layout remains responsive at narrow widths. Long names and titles wrap naturally rather than overflow.
- No new data is fabricated and no existing labels or approval actions are renamed.

## Implementation boundaries

Update the `approvalCard()` and `returnApprovalCard()` renderers in the staff dashboard controller, plus the dashboard-local approval-card styles. Add frontend contract coverage that checks the borrower name appears before the book title in both rendered templates and that the hierarchy classes are present.

Do not change backend services, database schema, endpoint paths, modal IDs, approval counts, polling, confirmation behavior, or unrelated working-tree files.

## Verification

Run the focused staff dashboard frontend tests first, then the complete frontend test suite. Review the final diff for whitespace errors and confirm only the approval-card implementation, its focused test coverage, and this spec are included in the task changes. Existing unrelated user changes must remain untouched.
