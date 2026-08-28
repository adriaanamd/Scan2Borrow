# Admin Dashboard Overview Design

## Scope

Improve only the authenticated `/staff/dashboard` Overview surface. Existing
staff pages, admin staff management, reports, navigation, authentication, SQL
schema, and approval workflows remain behaviorally unchanged.

## User outcome

An administrator or librarian can understand library activity at a glance from
the existing dashboard: current inventory and loan KPIs, borrowing activity for
the last twelve months, the current loan-status distribution, and the ten most
active borrowers.

## Visual direction

Use the Swiss anchor already selected for this work: neutral white surfaces,
Inter/Helvetica-style sans typography already present in the page, Yves Klein
blue as the primary accent, 1px hairline rules, tabular numerals, and
left-aligned asymmetric composition. The memorable move is a ranking rail that
visually offsets the two analytical charts while keeping the existing KPI row
and operational tables recognizable.

Charts will use native HTML/CSS and vanilla JavaScript only. The activity chart
will use accessible DOM bars with real month labels and counts. The status chart
will use a CSS conic-gradient ring paired with a text legend, so no third-party
chart library or fabricated SVG asset is introduced.

## Backend design

Extend the existing `StaffRepositoryInterface::dashboard()` data contract with
an `overview` object. `PdoStaffRepository` will calculate:

- `borrowing_activity`: twelve calendar-month buckets ending in the current
  month, counting borrowing records by `borrow_date`.
- `loan_status`: counts for the existing operational statuses, including
  available books, borrowed books, overdue loans, and pending approvals.
- `top_borrowers`: up to ten student/teacher accounts ordered by total
  borrowing records, with borrower id, display name, barcode, and count.

Queries remain parameterized and use the current tables and columns. Empty
results return zero-filled chart buckets and an empty ranking list. The current
`stats`, `recent`, and `pending` fields remain intact so existing dashboard
behavior and refresh polling continue to work.

## Frontend design

The existing `StaffPageController.dashboard()` will render the new `overview`
payload only when the current page is `staff-dashboard`. New dashboard-only
containers will be added to `staff-dashboard.html`; no other page template or
tab is modified.

The page will include:

1. Existing six KPI cards, unchanged in meaning and order.
2. A twelve-month borrowing activity panel with bars, month labels, counts,
   accessible labels, and a truthful empty state.
3. A loan-status panel with a CSS ring, legend, counts, and a truthful empty
   state.
4. A Top 10 Borrowers panel showing rank, name, borrower barcode, and total
   borrowing count, with an empty state when no borrowing data exists.
5. Existing Recent Transactions and Pending Approval sections, preserving their
   current interaction and polling behavior.

Responsive rules will collapse the analytical layout for smaller screens while
preserving the current dashboard’s spacing and Bootstrap compatibility.

## Error handling and security

The endpoint remains behind the existing staff authorization route. Existing
JSON error handling remains unchanged; if the dashboard request fails, the
current staff error toast is used. All displayed database values continue to be
escaped by the existing controller helper. No user-controlled values become
HTML or CSS without escaping/validation.

## Testing and verification

- Add repository tests for twelve-month bucket shape, status aggregates, top-ten
  ordering/limit, and empty data behavior using the existing SQLite test setup.
- Add controller/contract tests proving the `overview` payload is exposed while
  existing dashboard fields remain present.
- Add frontend markup/contract tests for the Overview-only containers and
  vanilla rendering hooks.
- Run the full PHPUnit suite, PHPStan level 9, and `node --check` for changed
  JavaScript.
- Smoke-test the authenticated dashboard API and page through the local Apache
  instance without modifying production data.

