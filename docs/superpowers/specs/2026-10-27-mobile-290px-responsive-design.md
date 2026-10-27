# 290px Mobile Responsiveness Design

## Context

Scan2Borrow is a server-rendered PHP application with a shared vanilla CSS/JS shell and page-specific feature styles. The current interface already has a mobile navigation drawer below 900px, but the global stylesheet still declares `html { min-width: 320px; }` and several shared/page surfaces retain fixed or minimum widths that can create horizontal page overflow below common phone widths.

## Goal

Make the existing interface usable at viewport widths as narrow as 290px without changing its visual language or information architecture.

## Constraints

- Preserve the current navy, blue, gold, white, and ruled library-card visual system.
- Do not redesign components, rename user-facing actions, or change navigation information architecture.
- Support a 290px viewport, including 1px borders, content padding, forms, modals, cards, and long labels.
- Keep data tables readable; tables with more columns than the viewport may scroll inside their existing table container rather than widening the document.
- Keep the existing navigation drawer behavior and accessible focus/close interactions.
- Do not add a frontend dependency or change backend behavior.
- Keep existing test coverage and CI quality gates intact.
- Commit the implementation in at least 30 atomic, testable commits, then push `master` to Margaret's repository.

## Recommended approach

Use shared CSS hardening first, followed by narrowly scoped page-family overrides only where existing styles have a documented fixed/minimum width. The shared shell will become fluid below 290px, mobile spacing will use `max()`/`clamp()`-style safe sizing, flex/grid children will be allowed to shrink, and overflow will be contained at the component boundary. Existing table scroll containers remain the deliberate fallback for dense tabular information.

Add static regression tests for the shared responsive contract and extend existing page-surface tests where a page-specific minimum width is changed. Verify the complete Node frontend suite plus the backend Composer quality gate locally when available, and verify the remote GitHub Actions run after pushing if credentials/network access are available.

## Acceptance criteria

1. At a 290px viewport, the document itself has no horizontal overflow on the shared shell, authentication surfaces, dashboards, forms, cards, modals, and feature pages covered by the existing frontend parity matrix.
2. The sidebar remains hidden off-canvas until opened; the topbar remains usable; drawer controls remain reachable and closable.
3. Content, page headers, action groups, card grids, form rows, and modal footers wrap or stack without clipped controls.
4. Dense tables scroll within their table card when they cannot reasonably reflow.
5. The existing visual styling remains recognizable and unchanged outside responsive sizing/flow behavior.
6. `npm test` passes, the applicable backend quality command passes when dependencies are available, and the pushed `master` branch is traceable to the verified local commits.

## Commit structure

The implementation will be split into at least 30 coherent commits covering: baseline responsive contract tests, shared root/shell rules, mobile drawer and topbar, content and page-head flow, cards and stats, forms and button groups, modal/auth surfaces, table containment, and page-specific feature families. Each commit must leave the relevant checks runnable and must not contain unrelated refactors.
