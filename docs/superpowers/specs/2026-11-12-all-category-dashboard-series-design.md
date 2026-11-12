# Show Every Inventory Category in the Admin Dashboard Trend

## Context

The admin dashboard's `Categories borrowed over time` visualization currently builds its series only from categories that appear in borrowing records. As a result, an inventory category with no borrowing activity is absent from the chart. The dashboard should show every active inventory category, with zero values for months without borrowing activity.

## Goal

Ensure `overview.category_borrowing_activity.series` contains one 12-month series for every active inventory category, including categories with no borrowing history, while preserving the existing counts, response shape, sorting, and frontend rendering behavior.

## Recommended approach

Keep the existing borrowing-activity aggregation and initialize the category map from the repository's active inventory category list before applying the borrowing counts. The existing `reportCategories()` query already supports both schemas used by the repository:

- normalized schema: distinct categories from non-deleted book copies;
- legacy schema: distinct categories from non-deleted books.

The aggregation loop will continue to populate month/category counts from borrowing rows. Series generation will then map every initialized category across the existing twelve-month list, producing zeros where no count was recorded. The existing total-descending, name-ascending sort will remain unchanged.

## Scope and data flow

1. `PdoStaffRepository::dashboardOverview()` obtains the active inventory categories and initializes an empty count map for each category.
2. The current schema-specific borrowing query populates counts for matching category/month pairs.
3. The existing series transformation emits all initialized categories with twelve integer counts.
4. The frontend receives the same `category_borrowing_activity` structure and continues rendering it without a template or chart API change.

Categories outside active inventory remain excluded. Blank category names continue to be represented as `Uncategorized`, matching the repository's existing category normalization.

## Testing

Add or update repository tests to prove:

- an inventory category with no borrowing records appears with twelve zero counts;
- empty borrowing data still produces the inventory categories as zero-valued series;
- existing borrowed-category totals and ordering remain unchanged;
- normalized-schema dashboard behavior remains compatible.

Run the focused PHPUnit test first, then the complete backend quality gate (`composer run quality`) and the frontend Node test suite (`npm test`).

## Error handling and compatibility

If the category query returns no categories, the series remains empty as it does today for an empty inventory. No new endpoint, database migration, or frontend contract is introduced. Existing user changes in the worktree must remain untouched.
