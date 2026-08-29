# Teacher Borrow and History Surface Design

## Goal

Improve the teacher-facing Borrow Books modal and shared borrowing history page using the existing teacher dashboard’s Swiss/data-oriented visual language while preserving behavior, content, routes, navigation, and API contracts.

## Direction

Use the teacher dashboard’s existing white/blue/navy/gold system palette, Inter/Epilogue data typography, hairline rules, compact tabular information, and left-aligned hierarchy. The differentiator is a borrowing-desk modal with clear scan/cart sequencing and a history ledger that makes transaction codes, quantities, dates, statuses, and fines easy to scan.

## Borrow surface

- Restyle the existing teacher dashboard `#borrowModal` and its `#borrowForm` rather than creating a new route.
- Preserve the bulk cart IDs, barcode scanner input, due-date control, error/message hosts, submit action, and teacher borrow endpoints.
- Add scoped teacher modal hooks for header, scan section, cart section, and responsive spacing.
- Keep destructive confirmation behavior unchanged and do not alter sidebar markup.

## Teacher history surface

- Preserve the shared `/student/history` route, `history-body` target, eight table columns, and `/api/student/history` request.
- Resolve the authenticated role through the existing session/navbar contract and expose a teacher-specific visual scope without duplicating the page.
- Use a compact ledger layout with hairline rules, tabular numerals, quantity emphasis, status/fine hierarchy, overdue row treatment, empty/error states, and horizontal overflow on narrow screens.
- Students must retain the existing student library treatment.

## Boundaries and safety

- Do not change `app-navbar`, sidebar structure, route paths, backend code, database schema, palette tokens, or API payloads.
- Keep all new CSS selectors scoped to teacher dashboard/modal or teacher history content; no navigation selectors.
- Preserve escaped dynamic values and existing status/date/quantity rendering.

## Verification

- Add failing markup/style/controller contracts before implementation.
- Verify each focused contract fails for the missing surface, then implement and verify it passes.
- Run the complete frontend test suite and inspect the final diff before merging.
