# Dashboard Renewal Actions

Status: Approved for implementation planning

## Problem

Student and teacher dashboards currently render renewal requests in a separate
Renewals panel below the main dashboard content. The active loans users need to
renew are listed in My Books, so the request action is visually detached from
the relevant book and adds a second place to scan.

## Approved behavior

For both student and teacher dashboards:

- Remove the standalone dashboard Renewals section and its dashboard mount.
- Keep My Books as the source of active-loan rows.
- Add an Actions column containing `View receipt` and `Renew` for each
  borrowable loan.
- `View receipt` keeps the existing transaction receipt URL and opens it in a
  new tab.
- `Renew` opens a modal for that specific loan with the current book title,
  due date, an optional reason field, and a `Request +7 days` submit button.
- Submitting the form uses the existing role-specific renewal endpoint.
- After a successful request, refresh the dashboard data so the row shows the
  existing renewal status instead of offering a duplicate request.
- If a renewal is already pending or approved, show its status in Actions.
- If a loan is pending or overdue and therefore not borrowable, show the
  existing explanatory state instead of a renewal request control.

The admin/staff renewal approval page remains unchanged. Its approval and
rejection controls continue to manage requests created by either borrower
role.

## Visual direction

The refactor keeps the established role-specific dashboard anchors rather than
introducing a third visual language:

- Student My Books remains Organic: warm humanist typography, rounded reading
  surface, and a calm accent action.
- Teacher My Books remains Swiss: Helvetica Neue, hairline table rules,
  tabular dates, and a precise action rail.

The shared interaction differentiator is a compact action rail in the final
table column: a quiet receipt link paired with one accent Renew control. The
modal carries the same role-scoped surface while making the reason and single
submit action explicit.

## Design

### Shared renewal interaction

Extend the existing renewal presentation boundary so the dashboard controller
passes each loan row into a shared modal component. The component owns:

- modal markup and accessibility labels;
- the selected loan id, title, and due date;
- reason input validation and submit state;
- calling the existing `RenewalService`;
- success/error feedback through the dashboard refresh and toast behavior.

The component must escape displayed loan data and disable duplicate submits
while the request is in flight. The role-specific service remains the source of
the endpoint path and payload shape.

### Dashboard row actions

Student and teacher `renderLoans()` methods will add the Actions header and
render role-scoped controls for the existing `current_loans` rows. Receipt
links continue using `transaction_code`. Renewal controls use the loan id and
the renewal state loaded from the existing renewal list. No backend contract
changes are required.

### Renewal state

The dashboard will load renewal records once per dashboard refresh and map them
by `loan_id`. A row with a matching renewal record renders its status label. A
borrowable row without a record renders `Renew`. Non-borrowable rows retain an
explanatory non-action state. The old standalone panel and its associated
dashboard initialization/load calls are removed.

## Data flow

```text
Dashboard load
  | current_loans + renewals
  v
My Books row -> View receipt | Renew
                    |
                    v
          Shared renewal modal
          reason + Request +7 days
                    |
                    v
       role-specific renewal endpoint
                    |
                    v
             refresh dashboard row
```

## Testing

Add frontend tests before implementation that assert:

- both dashboards no longer contain the standalone renewal mount;
- both My Books tables include an Actions column;
- rows expose receipt and renewal action hooks;
- the shared modal renders the selected loan, reason field, and
  `Request +7 days` control;
- the submit flow sends the existing `loan_id` and `reason` payload and
  refreshes the dashboard;
- pending/approved renewal states render their status and do not offer a
  duplicate request;
- pending and overdue loans do not offer a renewal request.

Run the focused renewal tests, the borrower dashboard contract tests, and the
full frontend suite. Run backend renewal tests to confirm the API contract
remains unchanged.

## Scope boundaries

This change does not alter renewal eligibility rules, the +7-day extension,
renewal database schema, staff approval behavior, receipt generation, or the
student/teacher dashboard data endpoints. It only moves the borrower renewal
entry point from the standalone dashboard section into each My Books row.
