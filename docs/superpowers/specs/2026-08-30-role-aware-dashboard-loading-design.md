# Role-Aware Dashboard Loading Design

## Context

Scan2Borrow has three dashboard surfaces that load their data asynchronously:

- students use the Organic borrower dashboard;
- teachers use the Swiss borrower dashboard;
- staff/admin users use the navy, blue, and gold library-operations dashboard.

The existing dashboards expose their static structure while requests are in flight, but they do not provide a deliberate loading state. The new loading experience must fit each role, avoid generic spinner treatment, and mount only once for each dashboard page.

## Goals

- Give student, teacher, and admin dashboards visibly different loading experiences.
- Keep each loading treatment faithful to the dashboard visual language already present in the project.
- Keep the sidebar and topbar stable while the main dashboard content initializes.
- Render one loading element per dashboard and complete the initial loading transition once.
- Preserve existing API, dashboard rendering, polling, action, toast, and error behavior.
- Provide accessible status semantics and reduced-motion behavior.

## Non-goals

- Redesigning the dashboard content after loading.
- Adding a new image or icon asset pipeline.
- Changing loading states on non-dashboard pages.
- Replacing existing dashboard controllers or API services with a new framework.

## Visual direction

### Student: Organic library arrival

The student loader uses the existing rounded Organic system: warm light surface, sage/green accent, deep navy text, generous spacing, and the existing Fraunces/Epilogue typography. Its visual anchor is a small set of softly rounded stacked library-card/book shapes with a restrained breathing motion. Copy identifies the real destination: “Loading your library…”.

### Teacher: Swiss faculty workspace

The teacher loader uses the existing Swiss system: white/light-gray surface, sharp `#002FA7` blue, square corners, compact sans-serif type, hairline rules, and tabular alignment. Its visual anchor is a precise horizontal blue progress rule with a subtle grid/folio treatment. Copy identifies the role-specific destination: “Preparing your faculty workspace…”.

### Admin: Library operations

The admin loader stays within the existing operational dashboard identity: navy field, blue structure, gold signal color, ruled surfaces, and firm rectangular geometry. Its visual anchor is a compact operations panel made from bordered data strips, not a decorative consumer spinner. Copy identifies the destination: “Loading library operations…”.

The loader treatments remain content-area overlays. The role’s navigation and topbar remain visible, preserving orientation during the request.

## Architecture and lifecycle

Each dashboard template will contain exactly one role-specific loading element inside its existing content root. The element will expose a shared semantic hook and a role-specific class/data attribute. The dashboard content remains owned by the current page controller.

A small shared loading-state boundary will provide the common lifecycle contract:

- `show()` makes the loading state visible and marks the dashboard as busy;
- `hideOnce()` completes the initial transition at most once;
- `isComplete()` supports idempotent controller behavior.

Student, teacher, and staff dashboard controllers will call the boundary at initial load. On a successful first response, the controller renders the response through its existing render methods and then hides the loader. On failure, the loader is also hidden and the existing toast/error surface is retained. Later refreshes, polling, borrowing, returning, and approval actions must not replay the initial loading animation.

The dashboard root and loader will use `aria-busy`, `role="status"`, and `aria-live="polite"` appropriately. Decorative shapes will be hidden from assistive technology. CSS will include a `prefers-reduced-motion: reduce` path that disables animation while keeping the state legible.

## Testing

Add focused frontend tests that verify:

- student, teacher, and admin templates each contain one loading element;
- each loading element has the correct role hook, status semantics, and role-specific copy/classes;
- the shared lifecycle is idempotent and hides only once;
- dashboard controllers expose and use the loading lifecycle without changing their existing render boundaries;
- reduced-motion rules are present in the dashboard loading styles.

Run the complete frontend test suite and `git diff --check` after implementation.

## Alternatives considered

1. A single generic spinner component. This would be smaller, but it would not satisfy the role-aware visual requirement or fit the Organic, Swiss, and operations surfaces.
2. Three completely independent loading implementations. This would maximize visual freedom but duplicate accessibility and lifecycle behavior, increasing the chance of inconsistent error and repeat-load handling.
3. Rendering the entire dashboard from JavaScript after the API response. This would create a stronger blank-state transition, but it would be invasive and risk regressions in the existing dashboard markup and action flows.

The selected design uses one shared lifecycle boundary with three role-owned visual treatments, preserving both consistency and visual distinction.
