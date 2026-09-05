# Admin Reports Pagination and Direct PDF Export Design

## Goal

Make the admin/staff Reports page fetch and display the selected report dataset, provide clear client-side pagination, and download a complete PDF directly from the current page without opening a print tab.

## Current Context

- `frontend/features/staff/pages/reports/reports.page.js` already calls `StaffReportService.load()` and renders the API response, but the visible table has no pagination state or controls.
- The report API at `/scan2borrow/api/staff/reports` returns the complete filtered dataset as `headers` and `data`.
- The current Generate Report link opens `/staff/reports?...&print=1` in a new tab, waits for the page to render, and calls `window.print()`.
- The reports page is shared by the admin/librarian staff surface and must preserve the existing report type and date filters.

## Approved Design

### Data flow and state

`ReportsPage.load()` will fetch the complete filtered report once through the existing `StaffReportService`. The page will retain the latest report headers, all rows, active filters, current page, and a fixed page size of 10. Loading, empty, and request-error states will be surfaced through the existing report status element. A failed fetch will not trigger PDF generation.

The existing backend response remains unchanged. Pagination is client-side because the endpoint already returns the complete report and direct PDF export must include every filtered row.

### Screen pagination

The table will render only the current 10-row slice. A footer below the table will show `1–10 of 47` (with correct handling for zero rows and the final partial page), plus Previous and Next controls. Controls will be disabled at the first and last page. Loading a new report resets the current page to 1. Navigation uses buttons so it does not reload the page or lose fetched data.

The total records metadata will use the complete fetched row count, not the current page count. CSV export URLs will continue to use the active type and date filters and will export the complete filtered dataset.

### Direct PDF export

The Reports page will replace the new-tab Generate Report link with a Download PDF action. The action will use the complete fetched headers and rows to generate a landscape A4 PDF in the current page through pinned jsPDF and jsPDF-AutoTable browser assets. It will download a file named from the selected report type and active date range. Pagination controls and the on-screen 10-row limit will never affect PDF contents.

The existing `?print=1` route behavior will remain compatible for bookmarked or manually opened URLs: it will still fetch and render the complete report before invoking browser printing. The normal Reports page action will no longer open that route or a new tab.

### Accessibility and resilience

- Pagination controls will have explicit accessible labels and disabled states.
- The summary will use `aria-live="polite"` so page changes are announced.
- PDF generation will be disabled until report data is ready and will show an actionable status if the PDF library is unavailable or generation fails.
- Existing text-safe cell rendering will remain unchanged.
- Existing reduced-motion behavior remains respected by the shared stylesheet.

## Testing

- Add unit coverage proving the report page stores the full fetched dataset, renders only the current 10-row slice, reports the correct range, and moves between pages without refetching.
- Add coverage proving filters reset pagination and PDF generation receives all rows rather than the visible page slice.
- Add markup coverage for the PDF library assets and pagination controls.
- Preserve the existing print-readiness test and all existing frontend contracts.
- Run `npm.cmd test` as the complete frontend gate.

## Scope

This change is limited to the staff Reports page, its report service if URL helpers need adjustment, shared report markup/styles, and frontend tests. No database schema, report query, or backend API response changes are required.
