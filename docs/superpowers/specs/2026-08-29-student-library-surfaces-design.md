# Student library surfaces design

## Goal

Apply the student dashboard's established Organic-inspired visual language to the Search Books and My History pages without changing their information architecture, borrowing behavior, API contracts, or sidenav.

## Visual direction

Use the existing student dashboard direction: Fraunces for display headings, Epilogue for body/UI text, the current navy/primary/accent/card/border tokens, generous rounded surfaces, subtle grain, and calm library-reading-room hierarchy. The memorable move is a shared “reading surface” treatment: a warm, layered page introduction followed by clearly separated catalog or record panels with strong status and quantity emphasis.

## Search Books

- Keep every current search field, filter option, active-filter chip, result count, availability label, book-card flip interaction, quantity display, borrow cart, and modal action.
- Restyle the page head as a student catalog masthead.
- Restyle the filter form as a layered reading desk with clear field grouping and accessible focus states.
- Preserve the book-card front/back interaction while improving spacing, cover fallback, status, availability, quantity, and action hierarchy.
- Keep all presentation styling scoped to the student search page and its existing `student-search.css` boundary.

## My History

- Keep the complete existing borrowing record and all eight data columns.
- Restyle the page head as a record-library masthead and the table as a readable ledger.
- Preserve status and overdue/fine semantics, while making them more visually scannable.
- Add responsive table behavior inside the existing content region; do not remove or rename data IDs.
- Give loading, empty, and error states the same intentional student surface treatment.

## Constraints

- Do not change the sidenav markup, navbar scripts, route paths, API endpoints, or backend.
- Do not invent statistics, book records, labels, or user data.
- Do not add new colors outside the existing system tokens.
- Preserve existing destructive-action confirmation behavior and borrow-cart behavior.
- Respect reduced-motion preferences and keyboard focus visibility.
