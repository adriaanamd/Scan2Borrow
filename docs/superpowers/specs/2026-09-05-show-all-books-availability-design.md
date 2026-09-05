# Show All Books Availability Design

## Problem

The student and teacher borrower catalog pages place the `Show all books` button inside the recommendations panel. When a borrower arrives with a search or filter query, the page controller hides that entire panel because the catalog results are already active. This makes the control unavailable on filtered catalog visits, including dashboard recommendation links.

## Goal

Keep one `Show all books` toggle available on both borrower catalog pages regardless of the route or query parameters used to reach the page. Preserve the existing in-page behavior: clicking it expands the catalog and loads page one; clicking it again hides the catalog.

## Design

Move the existing `#show-all-books` button out of the recommendations panel into a persistent catalog-controls row between the page masthead and the recommendations/results sections. Keep the same ID, `aria-controls`, and `aria-expanded` contract so `BorrowerSearchPage` remains the shared owner of the interaction.

The recommendations panel may continue to hide when filtered intent is present. The persistent controls row must remain visible in both the student and teacher templates. Existing catalog loading, pagination, query preservation, and button label updates remain unchanged.

Use the existing page-specific CSS conventions for spacing and responsive behavior. The control should remain easy to tap on narrow screens without changing catalog card layout or unrelated navigation.

## Data flow and states

- No-query arrival: recommendations load, the catalog stays hidden, and the persistent button reads `Show all books`.
- Filtered/query arrival: recommendations hide, catalog results load, and the persistent button remains available and reflects the visible catalog state.
- Button click from the landing view: the catalog becomes visible and page one loads using the current query parameters.
- Button click while the catalog is visible: the catalog hides and the button label/ARIA state update together.

## Error handling

Reuse the current catalog loading and error rendering. Moving the control must not introduce a second request path or swallow catalog errors. The button remains usable after a failed catalog request so the borrower can retry by toggling the catalog or changing filters.

## Testing

Add a frontend contract test for both templates that verifies the persistent controls row contains `#show-all-books` and that the button is not nested inside `#recommendation-panel`. Retain the existing shared catalog query and state tests. Run the complete frontend test command and the repository-documented PHP checks where available; this change does not alter PHP code.
