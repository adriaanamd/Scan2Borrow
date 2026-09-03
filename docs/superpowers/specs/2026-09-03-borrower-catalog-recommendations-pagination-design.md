# Borrower Catalog Recommendations and Pagination Design

## Problem

The student Search Books page and teacher Borrow Books page currently show only the first page of the borrower catalog, with no clear distinction between featured titles and the complete catalog. Borrowers need a small recommendation section first, followed by an explicit way to browse every book without loading an unbounded result set.

## Design direction

Preserve the existing role-specific catalog surfaces: the student page keeps its Organic treatment with rounded cards and warm accent tokens, while the teacher page keeps its Swiss treatment with white surfaces, blue rules, and compact data hierarchy.

The visible interaction is a two-level catalog hierarchy:

1. A Recommended section presents exactly five available titles.
2. A Show all books control sits directly below the five recommendations.
3. Activating it reveals the complete catalog in pages of ten, with a range such as `1-10 of 42` and previous/next controls.

The single memorable move is the transition from a small recommendation shelf into a measured catalog ledger: the recommendation count stays fixed at five, while the All books range makes the size and position of the full collection explicit.

## Scope

In scope:

- Student `Search Books` and teacher `Borrow Books` catalog pages.
- Shared borrower catalog behavior used by both role-specific controllers.
- Five-title recommendation fetch and presentation.
- All-books reveal state, ten-item pagination, range text, and empty/error states.
- Existing search, category, availability, floor, and sort filters.
- Existing Add to Borrow Cart behavior and student/teacher modal presentation.

Out of scope:

- Changes to borrowing eligibility, cart submission, or barcode lookup.
- Changes to staff inventory pagination or staff dashboards.
- A new backend endpoint or database query. The existing borrower book endpoint already accepts `page` and `per_page` and returns `total`.
- Personalized recommendations. The borrower endpoint does not provide a personalization signal, so the section is labeled `Recommended` and uses five available catalog titles selected by the current catalog ordering.

## Page behavior

### Initial page

When the page has no search or catalog filters:

- Fetch five available titles from the role-specific borrower book endpoint using `status=Available`, `per_page=5`, `page=1`, and the current newest ordering.
- Render those titles in a role-styled Recommended section.
- Keep the All books result panel collapsed behind the `Show all books` control.
- Keep the existing search/filter controls available so a borrower can go directly to the complete catalog.

The recommendation request is independent of the all-books request, so the initial page does not fetch the first catalog page just to hide it.

### Show all books

When `Show all books` is activated:

- Reveal an All books panel below the recommendation section and the existing search/filter controls.
- Request the current catalog with `page=1` and `per_page=10`, preserving any active search/filter/sort parameters.
- Change the control to `Hide all books` so the borrower can return to the recommendation-only view without losing the current page state.
- Render the existing book cards inside the All books panel.
- Display `1-10 of X` using the API total. If fewer than ten results exist, display the actual range, such as `1-4 of 4`.

When a search or filter is submitted, the page opens the All books panel automatically and starts at page one. Recommendations remain above the panel only for the unfiltered landing state; filtered/search results are treated as the borrower’s direct catalog intent.

### Pagination

- Page size is ten, matching the current borrower catalog behavior.
- Show Previous and Next controls only when applicable.
- Disable Previous on the first page and Next on the final page.
- Preserve the current search/filter/sort parameters when changing pages.
- Update the range text after every page request.
- If the current page becomes empty because the catalog changed, return to the nearest valid page and request it again.

## Component and data boundaries

The shared `BorrowerSearchPage` remains responsible for common catalog behavior:

- `loadRecommendations()` requests and stores five recommended books.
- `loadCatalog(page)` requests the current catalog page with `per_page=10`.
- `renderRecommendations(books)` controls the recommendation root.
- `renderCatalog(books, total, page)` controls the All books root and range/pager.
- `setAllBooksVisible(visible)` toggles the panel and button state.
- Search and filter submission resets the page to one, sets the All books panel visible, and preserves the existing role-owned form action.

Student and teacher subclasses continue to provide their existing copy, card styling, and borrow-modal behavior. No role-specific endpoint is changed: the student controller continues to use `/scan2borrow/api/student/books`, and the teacher controller continues to use `/scan2borrow/api/teacher/books`.

## Visual and content rules

- Use the existing student Organic and teacher Swiss tokens; do not introduce a third visual language.
- Keep the five recommendations visually lighter than the full catalog so the page hierarchy is immediately readable.
- Use real book fields returned by the API for title, author, category, cover, and availability.
- Use standard UI labels: `Recommended`, `Show all books`, `Hide all books`, `All books`, `Previous`, and `Next`.
- Do not claim recommendations are personalized.
- Escape book values before HTML insertion and retain the existing safe URL handling.
- Preserve existing hover/focus behavior and add visible keyboard focus to the new control and pagination buttons.

## Error and empty states

- If recommendations fail, keep the recommendation section with a concise unavailable state and leave `Show all books` usable.
- If the all-books request fails, show the existing catalog error treatment inside the All books panel and leave retry/search controls available.
- If no recommendations are available, show an honest empty message and keep the full catalog control visible.
- If no books match a search/filter, show the existing no-results state and `0-0 of 0` without rendering enabled pagination.

## Verification

Frontend tests will cover:

- Both role pages expose the recommendation root, `Show all books` control, All books root, range marker, and pager controls.
- The shared controller requests five recommendation records and ten catalog records.
- The initial unfiltered view loads recommendations without displaying the All books panel.
- Search/filter actions open All books and reset to page one.
- Range text is correct for full, partial, and empty pages.
- Previous/Next controls preserve query parameters and disable at the correct boundaries.
- Student and teacher borrow-card/modal behavior remains intact.

The complete frontend suite will run after implementation. No backend source changes are expected; the existing borrower book contract remains the backend verification boundary.
