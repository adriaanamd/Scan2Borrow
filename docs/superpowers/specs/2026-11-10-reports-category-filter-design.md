# Reports Category Filter Design

**Date:** 2026-11-10
**Status:** Approved for planning

## Goal

Allow staff administrators to filter every System Reports view and export by the book category while preserving the existing report type and date filters.

## Scope

The category is the book category stored in `book_titles.category_name` for the normalized schema and `books.category_name` for the legacy schema. The feature applies to the existing inventory, borrowed, returned, and overdue reports.

The feature includes:

- A category dropdown with `All Categories` plus categories currently present in book data.
- Server-side filtering for on-screen JSON reports and CSV exports.
- Category propagation to the existing print/PDF view and browser PDF export.
- Automatic report refresh when the selected category changes.
- Composition with existing report type and from/to date filters.

Excel export is out of scope because the application currently supports CSV and browser-generated PDF only.

## Architecture and Data Flow

Extend `StaffRepositoryInterface::report()` with a fourth `category` argument. The repository will normalize an empty category to the unfiltered case and build a parameterized category predicate for both schema variants. The category predicate will be composed with the existing report-type and date predicates for inventory, borrowed, returned, and overdue queries.

Add a repository method that returns distinct category options from the active book source. Empty or whitespace-only values will be exposed as `Uncategorized` when they are present; options will be sorted consistently and deduplicated.

The staff controller will forward `category` to the JSON report and CSV export handlers and include the category options in the JSON report payload. The print/PDF page will receive the same query parameter, so all report surfaces use the same server-side dataset.

No new tables or migrations are required.

## User Interface and Export Behavior

The existing Reports filter form will add a `Category` select beside report type and the date fields. Its empty value represents `All Categories`. The options are loaded from the backend rather than hard-coded, and the selected value is preserved in the URL and form state.

Changing the category submits or reloads the existing report flow immediately, matching the current report-type behavior. The selected category is included in the JSON request, CSV export URL, and print/PDF URL. The report header and generated PDF metadata will identify the active category so the scope is visible after export.

CSV remains a server-side download. PDF remains the existing browser-side `jsPDF`/AutoTable export. The category filter is applied before either export is generated, ensuring that an export contains no records from other categories.

## Validation and Error Handling

Category values are trimmed and matched as exact category names. A stale or invalid category produces zero matching records instead of falling back to all categories. Staff authorization remains enforced by the existing report routes.

The frontend will preserve the current empty/loading/error states. If category options cannot be loaded, the report page will retain `All Categories` and display the existing report-loading error behavior rather than inventing options.

## Testing Strategy

Backend tests will verify:

- Category options are derived from actual book data.
- Inventory, borrowed, returned, and overdue reports exclude records from other categories.
- `All Categories` returns the unfiltered dataset.
- Category and date filters compose correctly.
- CSV export forwards and applies the category.
- Legacy and normalized schema paths use the correct category column.

Frontend tests will verify:

- The category dropdown renders `All Categories` and backend-provided options.
- Changing category reloads the report with the selected value.
- API, CSV, and print/PDF URLs preserve the category alongside type and dates.
- The active category is shown in the report metadata.

Existing staff authorization, report type, date, pagination, and export tests must continue to pass.

## Non-Goals

- No new Excel library or Excel-specific export format.
- No changes to borrower-facing reports or dashboards.
- No changes to recommendations, activity, inventory mutation, or category management.
- No hard-coded category list that can drift from the catalog.
