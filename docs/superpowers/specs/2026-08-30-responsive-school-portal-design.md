# Responsive School Portal Design

## Context

Scan2Borrow is a static HTML and native ES-module school library portal with shared shell components and role-owned student, teacher, staff, guest, and auth pages. The desktop shell uses a fixed 248px sidebar and sticky top bar, while several content areas already have partial responsive rules.

The problem is that the shared shell does not yet provide a usable small-screen navigation pattern, and page content can still become cramped or overflow on phones.

## Direction

Preserve the current school-portal identity, role-aware links, routes, labels, and information architecture. Responsive behavior is a structural adaptation: the navy sidebar remains the visual anchor on desktop and becomes an off-canvas drawer on small screens. No separate mobile navigation tree will be introduced.

## Design

### Shared shell and navigation

- `AppNavbarComponent` remains the single source of truth for role-specific navigation links.
- The component will render an accessible mobile menu button alongside the existing sidebar markup.
- The existing sidebar will become an off-canvas drawer below the shared mobile breakpoint.
- A backdrop will communicate the drawer boundary and close it when tapped.
- The menu button will expose `aria-expanded`, `aria-controls`, and an accessible label.
- Escape closes the drawer. Selecting a navigation link closes it before navigation continues.
- Opening the drawer prevents document scrolling; closing it restores the prior scroll behavior.
- The component will remove event listeners in `destroy()`.
- Desktop behavior remains visually and functionally unchanged: the sidebar stays fixed and the main content keeps its sidebar offset.

### Responsive layout

- On small screens, `.main` removes the desktop sidebar margin and the top bar gains enough horizontal room for the menu button.
- Existing page content keeps its current order and role-specific styling.
- Shared content padding, page headers, cards, forms, and action groups use smaller fluid spacing at phone widths.
- Multi-column dashboard and summary grids collapse to one column when two columns no longer fit.
- Buttons in dense action groups may become full-width or wrap to prevent clipped controls.
- Tables preserve their columns and remain horizontally scrollable inside their existing table containers when hiding or stacking columns would remove useful information.
- Images, scanners, dialogs, and form controls stay within the viewport and keep touch targets at least 44px where practical.
- Auth and guest pages receive the same viewport-safe spacing treatment where they use shared layout classes.

### Breakpoints

- Desktop layout: above 900px, preserving the current fixed sidebar shell.
- Drawer layout: 900px and below, with the sidebar off-canvas by default.
- Phone refinements: 576px and below, stacking dense controls and reducing horizontal padding.
- The implementation will use the existing CSS breakpoint conventions where they already cover a page, adding only the shared breakpoint rules needed for shell consistency.

### Accessibility and interaction

- The drawer is keyboard reachable through the menu button and its links.
- Focus-visible styles remain consistent with the current gold focus treatment.
- The menu button has a visible three-line icon created with CSS, not a Unicode glyph.
- Drawer state changes use transitions but honor `prefers-reduced-motion: reduce`.
- No navigation link text, route, role resolution, active-link matching, logout confirmation, or session behavior changes.

## Implementation boundaries

1. Update the shared navbar component to render the mobile control and manage drawer lifecycle.
2. Update shared shell markup only if a stable hook is required for the overlay or menu button.
3. Add responsive rules to the shared stylesheet for shell, controls, cards, and viewport-safe defaults.
4. Add narrowly scoped page stylesheet overrides only where existing page layouts still overflow after the shared rules.
5. Add tests for rendered mobile navigation hooks, interaction state changes, cleanup, and regression protection for all role-specific navigation links.

## Verification

- Run the existing frontend Node test suite.
- Add and run component tests for menu rendering, ARIA state, open/close behavior, Escape handling, backdrop/link close behavior, and listener cleanup.
- Run the browser parity smoke test to ensure served modules and routes remain intact.
- Use browser inspection at representative widths around 900px, 768px, 576px, and 320px for student, teacher, staff, guest, and auth surfaces.
- Check keyboard-only operation and reduced-motion behavior.
- Run `git diff --check` before handoff.

## Out of scope

- Rebranding, palette changes, typography changes, route changes, or new portal features.
- Replacing existing tables with card views.
- Duplicating navigation markup for mobile.
- Refactoring unrelated page-specific CSS or backend behavior.
