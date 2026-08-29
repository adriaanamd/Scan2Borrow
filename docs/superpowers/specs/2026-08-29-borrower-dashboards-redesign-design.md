# Borrower Dashboard Redesign Design

Date: 2026-08-29
Status: Approved for implementation planning

## Context

Scan2Borrow has separate student and teacher dashboards with the correct borrowing, returning, profile, statistics, and active-loan content, but both currently use a generic visual treatment. The redesign should make the dashboards feel intentional and role-aware without changing the information they expose or the API behavior behind them.

## Direction

The dashboards will share the same information architecture and responsive behavior while using distinct visual personalities:

- Student: Organic library. A warm, personal reading surface using sage, moss, clay, terracotta, ochre, sand, and oat tones; rounded panels; warm humanist typography; and restrained grain.
- Teacher: Swiss faculty desk. A crisp operational workspace using white or neutral surfaces, Yves Klein blue, Helvetica-style sans typography, visible hairline rules, compact data panels, and tabular numerals.

The student differentiator is a shelf-like treatment for recommendations and active books. The teacher differentiator is a structured desk rail for profile and borrowing controls, with the loan table as the main visual anchor.

## Scope and content contract

This is a frontend-only redesign.

- Keep the existing student and teacher API endpoints, payloads, and page controllers.
- Keep all current dashboard content and actions; do not invent data or add unrelated modules.
- Preserve existing DOM IDs used by JavaScript controllers, including profile fields, stats, loan tables, borrow/return forms, cart hosts, and modal targets.
- Preserve bulk borrowing, return, receipt, due-date, fine, overdue, recommendation, capacity, and achievement behavior.
- Do not add database fields, migrations, or backend endpoints.
- Keep existing shared navigation and confirmation behavior intact.

## Information architecture

Both dashboards follow this visual order:

1. Shared navigation and page title.
2. Welcome/profile block with role metadata and library card.
3. Four statistics.
4. Role-specific work area using the content already supplied by each dashboard.
5. Active loans table with quantity, dates, status, and receipt links.
6. Existing borrow and return modals.

Student-specific content remains capacity, due-soon items, recommendations, and achievements. Teacher-specific content remains borrowing and return controls, due-date handling, and current activity. The redesign changes presentation, not these data boundaries.

## Visual system

### Shared layout primitives

Add a scoped borrower-dashboard styling layer for grid, spacing, cards, stat blocks, profile panels, tables, alerts, modal surfaces, focus states, and responsive breakpoints. Shared structural rules may be reused, but role tokens must remain scoped to prevent visual leakage between dashboards.

The layout should support a wide two-column work area and collapse to a single column below tablet width. Statistics use a compact two-column mobile grid. Tables remain readable through horizontal scrolling rather than hiding required columns.

### Student tokens

- Surface: sand `#E8DCC7`; elevated oat `#D4B895`.
- Accent: sage `#8B9D83`; deep text/status anchor: moss `#606C38`.
- Secondary accents: clay `#B08B6E`, terracotta `#C66B3D`, ochre `#C08E3A`.
- Typography: Fraunces for major display headings and a warm sans fallback for labels and controls.
- Shape: rounded corners in the 16-24px range, with soft panel layering.
- Texture: restrained 1-3% grain on the dashboard surface only.
- Motion: gentle transitions with a reduced-motion override.

### Teacher tokens

- Surface: pure white or neutral `#F7F7F8`.
- Accent: Yves Klein blue `#002FA7`.
- Typography: Helvetica Neue, Arial, or equivalent sans stack; tabular numerals for statistics and dates.
- Structure: visible 1px rules, asymmetric spacing, compact panels, and lightly rounded or squared edges.
- Texture: no grain; hierarchy comes from rules, spacing, and blue markers.
- Motion: restrained transitions with a reduced-motion override.

Existing semantic colors for danger, warning, success, and availability remain legible and must not be replaced by decorative colors.

## Components and implementation boundaries

- `frontend/features/student/pages/dashboard/dashboard.html`: retain the student content and stable IDs, add role-scoped structural classes, replace inline visual styling with dashboard classes, and preserve all modal/form markup contracts.
- `frontend/features/student/pages/dashboard/student-dashboard.page.js`: retain data rendering and actions; only make presentation changes when needed for the new structure or accessible state handling.
- `frontend/features/teacher/pages/dashboard/dashboard.html`: apply the teacher structure classes and Swiss visual treatment while keeping current forms and IDs.
- `frontend/features/teacher/pages/dashboard/teacher-dashboard.page.js`: retain API behavior and render contracts; avoid changing due-date, return, or bulk-borrow logic.
- `frontend/assets/css/`: add a focused borrower dashboard stylesheet or clearly scoped student/teacher dashboard styles. Do not destabilize staff/admin or guest pages.
- `frontend/assets/js/core/icons.js`: reuse the existing project icon treatment where dashboard decorations need icons; do not introduce emoji glyphs as icon substitutes.

## States and accessibility

- Loading and empty states should reserve stable space and retain the existing honest copy.
- Overdue and fine states must use text/badges plus color, never color alone.
- Borrow/return errors continue through the existing toast or modal messaging but gain clearer visual hierarchy.
- Preserve labels, semantic headings, keyboard focus, visible focus rings, sufficient contrast, and accessible button names.
- Respect `prefers-reduced-motion`.
- The grain texture must be decorative and ignored by assistive technology.

## Data flow

The existing page lifecycle remains:

1. The page controller loads the existing dashboard endpoint.
2. The payload is rendered into the existing dashboard regions and IDs.
3. Borrow/return actions submit through the existing services or fetch paths.
4. Success, error, and refresh behavior remains unchanged.

No new client-side state model is required. Any new class or wrapper must be presentation-only and must not become a second source of truth.

## Validation

Add or extend frontend tests to verify:

- Both dashboard templates retain their required page markers, role wrappers, stable IDs, modal/form targets, and module entries.
- Student and teacher controllers retain their existing render and submit boundaries.
- Student and teacher visual scope classes exist and do not rely on the admin stylesheet for required tokens.
- Quantity, current-loan, borrow, return, and receipt surfaces remain present.

Then run the complete frontend test suite and inspect the rendered dashboards at desktop and mobile widths. No backend test or migration is expected because the API and schema are unchanged.

## Out of scope

- Changing dashboard data, recommendations, borrowing limits, or business rules.
- Reworking shared navigation, authentication, or staff/admin dashboards.
- Adding new dashboard APIs or database columns.
- Replacing the existing bulk-borrow workflow.
