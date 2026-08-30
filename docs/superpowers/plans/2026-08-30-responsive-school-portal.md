# Responsive School Portal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Make every applicable Scan2Borrow portal surface usable on phones and tablets with a shared accessible hamburger drawer while preserving existing role-aware navigation, routes, and visual identity.

**Architecture:** Keep the existing sidebar as the only navigation source. The live classic script and the tested module component will each attach the same drawer contract to the existing sidebar and current page top bar; no duplicate mobile link tree will be introduced. Shared CSS will own shell behavior and viewport-safe defaults, while existing page-specific styles will receive only narrow overrides where their content still needs to stack or scroll.

**Tech Stack:** Native browser ES modules, classic deferred browser JavaScript, CSS media queries, Bootstrap 5.3 utilities already loaded by templates, and Node's built-in node:test runner.

## Global Constraints

- Preserve current school-portal identity, role-aware links, routes, labels, and information architecture.
- Use the existing sidebar as the single source of truth for role-specific navigation links.
- Desktop layout remains unchanged above 900px: fixed sidebar and main-content offset remain in place.
- Drawer layout applies at 900px and below; phone refinements apply at 576px and below.
- Do not change navigation link text, route, role resolution, active-link matching, logout confirmation, or session behavior.
- Use a CSS three-line menu icon; do not add Unicode glyphs as icon substitutes.
- Honor prefers-reduced-motion: reduce and remove event listeners in destroy().
- Tables preserve useful columns and remain horizontally scrollable instead of hiding meaningful data.
- Do not add dependencies or change backend behavior.

---

### Task 1: Add failing tests for the shared drawer contract

**Files:**
- Modify: frontend/tests/navbar-cache.test.js
- Modify: frontend/tests/layout-components.test.js

**Interfaces:**
- Consumes: the existing classic AppNavbar VM loader and AppNavbarComponent constructor.
- Produces: regression tests for sidebar-toggle, sidebar-backdrop, ARIA state, drawer state transitions, Escape/link/backdrop closing, and cleanup.

- [ ] **Step 1: Extend the classic-navbar test harness with DOM-capable fakes**

Add a small fake element/document fixture in frontend/tests/navbar-cache.test.js that supports only the methods the navbar needs: appendChild, remove, addEventListener, removeEventListener, querySelector, querySelectorAll, closest, classList, dataset, style, hidden, setAttribute, and click dispatch. Keep the existing loadNavbar(window) tests unchanged so role-cache coverage remains independent of drawer behavior.

The fixture must expose document.body, document.createElement, document.querySelector, and document-level keydown listeners. The fake .app element must return the fake .topbar from querySelector('.topbar'), and the fake root must return the .app from closest('.app').

- [ ] **Step 2: Write failing classic-navbar interaction tests**

Add tests with these assertions:

~~~js
test('classic navbar creates an accessible mobile control and opens the drawer', async () => {
  const fixture = createNavbarFixture('/scan2borrow/student/dashboard');
  const AppNavbar = loadNavbar(fixture.window);
  const navbar = new AppNavbar(fixture.root);

  await navbar.start();
  assert.equal(fixture.toggle.getAttribute('aria-controls'), 'app-sidebar');
  assert.equal(fixture.toggle.getAttribute('aria-expanded'), 'false');

  fixture.toggle.click();

  assert.equal(fixture.root.classList.contains('is-open'), true);
  assert.equal(fixture.backdrop.hidden, false);
  assert.equal(fixture.toggle.getAttribute('aria-expanded'), 'true');
  assert.equal(fixture.document.body.classList.contains('nav-drawer-open'), true);
  assert.equal(fixture.document.body.style.overflow, 'hidden');
});

test('classic navbar closes the drawer from Escape, backdrop, and navigation links', async () => {
  const fixture = createNavbarFixture('/scan2borrow/student/dashboard');
  const AppNavbar = loadNavbar(fixture.window);
  const navbar = new AppNavbar(fixture.root);

  await navbar.start();
  fixture.toggle.click();
  fixture.document.dispatchEvent({ type: 'keydown', key: 'Escape' });
  assert.equal(fixture.root.classList.contains('is-open'), false);

  fixture.toggle.click();
  fixture.backdrop.click();
  assert.equal(fixture.root.classList.contains('is-open'), false);

  fixture.toggle.click();
  fixture.navLink.click();
  assert.equal(fixture.root.classList.contains('is-open'), false);
});

test('classic navbar destroy removes drawer listeners and restores body overflow', async () => {
  const fixture = createNavbarFixture('/scan2borrow/student/dashboard');
  const AppNavbar = loadNavbar(fixture.window);
  const navbar = new AppNavbar(fixture.root);

  await navbar.start();
  fixture.toggle.click();
  navbar.destroy();

  assert.equal(fixture.document.body.classList.contains('nav-drawer-open'), false);
  assert.equal(fixture.document.body.style.overflow, '');
  assert.equal(fixture.document.listenerCount('keydown'), 0);
});
~~~

createNavbarFixture(pathname) must return { document, window, root, toggle, backdrop, navLink }, with window.location.pathname set to pathname, a session storage stub that returns student, and root.dataset.navbarRole = student. The fixture should make root.querySelector('[data-nav-path]') return navLink and root.querySelectorAll('[data-nav-path]') return [navLink] after rendering.

- [ ] **Step 3: Add module-component contract tests**

In frontend/tests/layout-components.test.js, add a test that uses a minimal injected document fixture to instantiate new AppNavbarComponent(root, { session, window, document }), calls start(), clicks the toggle, and asserts the same aria-expanded, .is-open, and body.nav-drawer-open contract. Add a second assertion that destroy() removes the keydown listener. The module component must expose the same behavior as the classic runtime because the component is the maintained shared implementation contract.

- [ ] **Step 4: Run the focused tests and verify they fail for the missing drawer behavior**

Run:

~~~powershell
node --test frontend/tests/navbar-cache.test.js frontend/tests/layout-components.test.js
~~~

Expected: the existing role/navigation tests pass, while the new drawer tests fail because the toggle/backdrop and lifecycle methods do not exist yet.

- [ ] **Step 5: Commit the failing tests**

~~~powershell
git add frontend/tests/navbar-cache.test.js frontend/tests/layout-components.test.js
git commit -m "test: define responsive navbar drawer contract"
~~~

### Task 2: Implement the accessible drawer in both navbar implementations

**Files:**
- Modify: frontend/assets/js/core/app-navbar.js
- Modify: frontend/app/shared/components/app-navbar/app-navbar.component.js

**Interfaces:**
- Consumes: the existing role-specific renderBorrower, renderStaff, renderGuest, and setActiveLink methods.
- Produces: setupResponsiveControls(), openDrawer(), closeDrawer(), toggleDrawer(), handleKeydown(), and destroy() behavior used by the tests and CSS hooks.

- [ ] **Step 1: Add the drawer state fields and lifecycle hooks**

In each navbar constructor, initialize the same fields:

~~~js
this.document = options?.document || root.ownerDocument || globalThis.document;
this.toggle = null;
this.backdrop = null;
this.previousBodyOverflow = '';
this.handleKeydown = this.handleKeydown.bind(this);
this.handleToggleClick = this.toggleDrawer.bind(this);
this.handleBackdropClick = this.closeDrawer.bind(this);
this.handleNavigationClick = this.handleNavigationClick.bind(this);
~~~

For the classic constructor, accept constructor(root, options = {}) and resolve options.window || globalThis.window. For the module component, preserve its existing { session, window } options while adding the optional document field.

- [ ] **Step 2: Add setupResponsiveControls() using the current top bar**

After each successful role render and before setActiveLink(), call setupResponsiveControls(). The method must:

~~~js
setupResponsiveControls() {
  if (!this.document?.createElement) return;

  this.root.id = this.root.id || 'app-sidebar';
  const app = this.root.closest?.('.app');
  const topbar = app?.querySelector?.('.topbar') || this.document.querySelector?.('.topbar');
  if (!topbar) return;

  this.toggle = topbar.querySelector?.('.sidebar-toggle') || this.document.createElement('button');
  this.toggle.type = 'button';
  this.toggle.className = 'sidebar-toggle';
  this.toggle.setAttribute('aria-controls', this.root.id);
  this.toggle.setAttribute('aria-expanded', 'false');
  this.toggle.setAttribute('aria-label', 'Open navigation');
  this.toggle.innerHTML = '<span class="sidebar-toggle__bars" aria-hidden="true"></span>';
  if (!this.toggle.parentNode) topbar.insertBefore(this.toggle, topbar.firstChild || null);

  this.backdrop = app?.querySelector?.('.sidebar-backdrop') || this.document.createElement('button');
  this.backdrop.type = 'button';
  this.backdrop.className = 'sidebar-backdrop';
  this.backdrop.hidden = true;
  this.backdrop.setAttribute('aria-label', 'Close navigation');
  if (!this.backdrop.parentNode) (app || this.document.body).appendChild(this.backdrop);

  this.toggle.addEventListener('click', this.handleToggleClick);
  this.backdrop.addEventListener('click', this.handleBackdropClick);
  this.root.addEventListener('click', this.handleNavigationClick);
  this.document.addEventListener('keydown', this.handleKeydown);
}
~~~

The implementation must avoid binding duplicate listeners when setupResponsiveControls() is called more than once. If an existing control was found, remove previously bound listeners before rebinding or return early when the component is already initialized.

- [ ] **Step 3: Add the drawer state methods**

Implement the same methods in both files:

~~~js
openDrawer() {
  if (!this.toggle || !this.backdrop) return;
  this.previousBodyOverflow = this.document.body?.style.overflow || '';
  this.root.classList.add('is-open');
  this.backdrop.hidden = false;
  this.toggle.setAttribute('aria-expanded', 'true');
  this.toggle.setAttribute('aria-label', 'Close navigation');
  this.document.body?.classList.add('nav-drawer-open');
}

closeDrawer() {
  if (!this.toggle || !this.backdrop) return;
  this.root.classList.remove('is-open');
  this.backdrop.hidden = true;
  this.toggle.setAttribute('aria-expanded', 'false');
  this.toggle.setAttribute('aria-label', 'Open navigation');
  this.document.body?.classList.remove('nav-drawer-open');
  if (this.document.body) this.document.body.style.overflow = this.previousBodyOverflow;
}

toggleDrawer() {
  if (this.root.classList.contains('is-open')) this.closeDrawer();
  else this.openDrawer();
}

handleKeydown(event) {
  if (event.key === 'Escape' && this.root.classList.contains('is-open')) this.closeDrawer();
}

handleNavigationClick(event) {
  if (event.target.closest?.('[data-nav-path]')) this.closeDrawer();
}
~~~

The root navigation click handler must not prevent default navigation. closeDrawer() must be idempotent so destroy() can call it safely.

- [ ] **Step 4: Implement cleanup**

Replace the empty destroy() method in both implementations with:

~~~js
destroy() {
  this.toggle?.removeEventListener('click', this.handleToggleClick);
  this.backdrop?.removeEventListener('click', this.handleBackdropClick);
  this.root.removeEventListener('click', this.handleNavigationClick);
  this.document?.removeEventListener?.('keydown', this.handleKeydown);
  this.closeDrawer();
  this.toggle = null;
  this.backdrop = null;
}
~~~

If the implementation removes the dynamically-created backdrop during cleanup, do so only for elements created by this navbar instance; never remove a page-owned element that was found and reused.

- [ ] **Step 5: Run focused tests and fix only drawer failures**

Run:

~~~powershell
node --test frontend/tests/navbar-cache.test.js frontend/tests/layout-components.test.js
~~~

Expected: all role-cache, teacher-route, guest-navigation, module-component, and new drawer tests pass.

- [ ] **Step 6: Commit the shared behavior**

~~~powershell
git add frontend/assets/js/core/app-navbar.js frontend/app/shared/components/app-navbar/app-navbar.component.js frontend/tests/navbar-cache.test.js frontend/tests/layout-components.test.js
git commit -m "feat: add responsive portal navigation drawer"
~~~

### Task 3: Replace the narrow rail with responsive shell CSS

**Files:**
- Modify: frontend/assets/css/style.css
- Modify: frontend/assets/css/borrower-dashboards.css

**Interfaces:**
- Consumes: .is-open, .sidebar-toggle, .sidebar-backdrop, and .nav-drawer-open hooks from Task 2.
- Produces: desktop-preserving shell layout, off-canvas drawer behavior at 900px and below, and phone-safe spacing/stacking.

- [ ] **Step 1: Write the shared CSS rules for the hidden control and drawer primitives**

Add these rules near the existing sidebar/topbar rules in frontend/assets/css/style.css:

~~~css
.sidebar-toggle,
.sidebar-backdrop { display: none; }

.sidebar-toggle {
    align-items: center;
    background: #fff;
    border: 1px solid var(--border-strong);
    border-radius: 4px;
    color: var(--navy);
    height: 42px;
    justify-content: center;
    padding: 0;
    width: 42px;
}

.sidebar-toggle:hover,
.sidebar-toggle:focus-visible { background: var(--sky); border-color: var(--primary); }

.sidebar-toggle__bars,
.sidebar-toggle__bars::before,
.sidebar-toggle__bars::after {
    background: currentColor;
    content: "";
    display: block;
    height: 2px;
    position: relative;
    width: 20px;
}

.sidebar-toggle__bars::before { position: absolute; top: -6px; }
.sidebar-toggle__bars::after { position: absolute; top: 6px; }
.sidebar-toggle[aria-expanded="true"] .sidebar-toggle__bars { background: transparent; }
.sidebar-toggle[aria-expanded="true"] .sidebar-toggle__bars::before { top: 0; transform: rotate(45deg); }
.sidebar-toggle[aria-expanded="true"] .sidebar-toggle__bars::after { top: 0; transform: rotate(-45deg); }

@media (max-width: 900px) {
    body.nav-drawer-open { overflow: hidden; }
    .sidebar {
        box-shadow: 12px 0 30px rgba(16, 47, 82, .18);
        transform: translateX(-100%);
        transition: transform .22s ease;
        width: min(290px, 86vw);
        z-index: 30;
    }
    .sidebar.is-open { transform: translateX(0); }
    .main { margin-left: 0; }
    .topbar { gap: 12px; padding-left: 16px; }
    .sidebar-toggle { display: inline-flex; flex: 0 0 auto; }
    .sidebar-backdrop {
        background: rgba(16, 47, 82, .46);
        border: 0;
        cursor: pointer;
        display: block;
        inset: 0;
        padding: 0;
        position: fixed;
        z-index: 25;
    }
    .sidebar-backdrop[hidden] { display: none; }
    .sidebar-nav a { min-height: 46px; }
}

@media (max-width: 576px) {
    .topbar { min-height: 64px; padding: 11px 14px; }
    .topbar-title { min-width: 0; }
    .topbar-title::before { height: 22px; }
    .topbar-user { max-width: 42%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .user-name { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .content { padding: 18px 14px 28px; }
    .page-head { align-items: flex-start; flex-direction: column; gap: 12px; padding-left: 12px; }
    .page-head .btn { width: 100%; }
    .card, .soft-card, .table-card { max-width: 100%; }
    .table-card { overflow: hidden; padding: 14px; }
    .table-responsive { max-width: 100%; -webkit-overflow-scrolling: touch; }
    .modal-dialog { margin: .75rem; }
    .modal-footer { align-items: stretch; flex-direction: column-reverse; }
    .modal-footer .btn { width: 100%; }
    .auth-wrap { padding: 12px; }
    .auth-body { padding: 22px 18px 26px; }
    .hero-card { padding: 20px 16px; }
    .libcard { min-width: 0; }
    .form-control, .form-select, .btn { min-height: 44px; }
}
~~~

Do not retain the old max-width: 768px 76px rail rules. Keep the existing max-width: 980px 216px desktop adjustment only if it remains above the new drawer breakpoint; the new max-width: 900px rules must win below 900px. Keep the print rules unchanged and ensure print still sets .main { margin-left: 0; }.

- [ ] **Step 2: Add phone stacking for borrower dashboard stats**

Append to the existing phone media section in frontend/assets/css/borrower-dashboards.css:

~~~css
@media (max-width: 576px) {
  .borrower-dashboard__stats { grid-template-columns: minmax(0, 1fr); }

  .borrower-dashboard--student .student-dashboard__hero .d-flex.gap-2,
  .borrower-dashboard--teacher .teacher-dashboard__hero .d-flex.gap-2 {
    align-items: stretch;
    flex-direction: column;
  }

  .borrower-dashboard--student .student-dashboard__hero .d-flex.gap-2 .btn,
  .borrower-dashboard--teacher .teacher-dashboard__hero .d-flex.gap-2 .btn { width: 100%; }
}
~~~

Use the existing role-scoped selectors so staff, guest, and auth styles are not affected by borrower-only layout assumptions.

- [ ] **Step 3: Run style and source contract tests**

Run:

~~~powershell
npm test
~~~

Expected: all existing frontend tests pass, including page parity and legacy-frontend removal contracts. If a test asserts the old 76px rail, update only that test to assert the new drawer hook and preserve its original purpose; do not weaken route or template assertions.

- [ ] **Step 4: Commit the responsive shell styles**

~~~powershell
git add frontend/assets/css/style.css frontend/assets/css/borrower-dashboards.css
git commit -m "style: make portal shell mobile friendly"
~~~

### Task 4: Verify served pages and responsive behavior across roles

**Files:**
- Modify: none unless verification identifies a concrete regression in the files above.
- Test: frontend/tests/*.test.js, tests/browser/frontend-module-parity.ps1

**Interfaces:**
- Consumes: the committed drawer runtime and shared CSS from Tasks 2–3.
- Produces: evidence that all applicable portal roles remain navigable and viewport-safe.

- [ ] **Step 1: Run the complete frontend test suite**

~~~powershell
npm test
~~~

Expected: all tests pass with zero failures.

- [ ] **Step 2: Run the served module and route parity smoke test**

~~~powershell
powershell -File tests/browser/frontend-module-parity.ps1
~~~

Expected: the existing parity smoke test passes, with canonical feature pages still serving their single module entry and shared navbar script.

- [ ] **Step 3: Inspect representative pages at target widths**

Use the local XAMPP site and inspect these widths: 1024px, 900px, 768px, 576px, and 320px.

Check one page from each applicable role: student dashboard, teacher dashboard, staff dashboard, guest dashboard, and staff login/auth. At widths 900px and below, confirm the menu button is visible, the sidebar is hidden until opened, the backdrop closes it, Escape closes it, and the page does not scroll horizontally. At widths above 900px, confirm the fixed sidebar, 216px adjustment around 980px, main offset, and desktop top bar remain unchanged.

- [ ] **Step 4: Check keyboard and reduced-motion behavior**

Tab to the menu button, open it with Enter/Space, tab through visible navigation links, close with Escape, and verify focus-visible outlines remain visible. Enable reduced motion in the browser and confirm the drawer transitions do not animate perceptibly.

- [ ] **Step 5: Run final whitespace/status checks**

~~~powershell
git diff --check
git status --short
git log -4 --oneline
~~~

Expected: no whitespace errors; only the intentional responsive commits and the pre-existing untracked problem-css/ directory remain visible. Do not add or remove problem-css/.

