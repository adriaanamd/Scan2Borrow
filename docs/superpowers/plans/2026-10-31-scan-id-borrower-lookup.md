# Scan ID Borrower Lookup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Let staff scan a borrower ID from the Borrowers directory to open a read-only modal showing the borrower’s current loans, while preserving and verifying the existing circulation-desk workflow.

**Architecture:** Reuse the existing staff-protected GET /api/staff/circulation/borrower endpoint and repository compatibility. Add BorrowerService.lookupByBarcode(), a focused BorrowerLookupModal component that owns scanner/modal state, and minimal Borrowers-page/template wiring. No backend production change or database migration is required; backend test coverage will prove the existing lookup response includes active loans.

**Tech Stack:** Vanilla ES modules, Node’s built-in test runner, Bootstrap 5.3 modal markup, shared BarcodeScannerComponent, PHP 8.5, PHPUnit 11, PHPStan 2.

## Global Constraints

- No new backend endpoint; reuse GET /api/staff/circulation/borrower?barcode=....
- No new database table, column, migration, or borrowing model.
- The modal is read-only and must not borrow, return, or alter borrower data.
- The endpoint remains staff-authorized server-side; client-side visibility is not authorization.
- Escape every borrower and loan value before inserting it into HTML.
- Camera scanning starts only while the modal is open and is stopped when it closes.
- Preserve the existing dirty changes in frontend/features/staff/services/index.js, frontend/tests/scanner.test.js, and uploads/202310449-606bb73417da81df.jpg; do not stage them in feature commits.
- Work directly on master because the user explicitly requested inline execution on master; do not create a worktree.
- Do not weaken, skip, focus, or disable tests, type checks, build steps, or CI lanes.
- Use the Swiss direction for the new surface: neutral white/near-white, Helvetica Neue/Arial sans, Yves Klein blue #002FA7, left alignment, and 1px hairline rules.

---

## File Map

Modify:

- frontend/features/staff/services/borrower.service.js — add the read-only borrower-ID lookup method.
- frontend/features/staff/pages/borrowers/borrowers.page.js — construct and destroy the lookup modal.
- frontend/features/staff/pages/borrowers/borrowers.html — add the Scan ID trigger and accessible modal markup.
- frontend/tests/staff-borrowers.test.js — cover the service lookup contract and page boundary.
- frontend/tests/staff-pages.test.js — assert the served Borrowers template exposes the modal.
- backend/tests/Unit/StaffCirculation/StaffCirculationControllerTest.php — assert lookup JSON includes an active loan.

Create:

- frontend/features/staff/components/borrower-lookup-modal/borrower-lookup-modal.component.js — modal state, scanner lifecycle, rendering, escaping, and focus restoration.
- frontend/tests/borrower-lookup-modal.test.js — deterministic component tests with a fake DOM and scanner factory.
- frontend/features/staff/pages/borrowers/borrowers.css — scoped Swiss styling for the modal and loan ledger.

Verify without modifying unless a regression appears:

- frontend/features/staff/services/staff-circulation.service.js
- frontend/features/staff/pages/circulation/circulation.page.js
- backend/src/Http/Controllers/StaffController.php
- backend/src/Application/StaffCirculation/StaffCirculationService.php
- backend/src/Infrastructure/Persistence/PdoStaffCirculationRepository.php

## Interfaces

    // frontend/features/staff/services/borrower.service.js
    lookupByBarcode(barcode) -> Promise<ApiPayload>

    // frontend/features/staff/components/borrower-lookup-modal/borrower-lookup-modal.component.js
    new BorrowerLookupModal(root, { service, trigger, document, window, scannerFactory }).start()
    lookup(barcode) -> Promise<object|null>
    destroy() -> Promise<void>

Successful payload:

    {
      data: {
        borrower: { id: 7, barcode: 'STU-001', name: 'Ada Lovelace', role: 'student', status: 'active', active_loans: 1 },
        active_loans: [{ id: 55, copy_barcode: 'COPY-001', title: 'Clean Code', due_date: '2026-08-27' }],
      },
    }

### Task 1: Add the borrower lookup service contract

Files:

- Modify: frontend/tests/staff-borrowers.test.js
- Modify: frontend/features/staff/services/borrower.service.js

Interfaces:

- Consumes: existing BorrowerService({ api }).
- Produces: lookupByBarcode(barcode), which trims input and calls /scan2borrow/api/staff/circulation/borrower.

- [ ] Step 1: Write the failing test.

In the existing service contract test, call await service.lookupByBarcode('  STU-001  '); and append this expected call:

    { method: 'GET', path: '/scan2borrow/api/staff/circulation/borrower', params: { barcode: 'STU-001' } }

- [ ] Step 2: Run the test and verify the expected failure.

Run:

    node --test frontend/tests/staff-borrowers.test.js

Expected: TypeError: service.lookupByBarcode is not a function.

- [ ] Step 3: Implement the minimal method.

Add this method to BorrowerService after details:

    lookupByBarcode(barcode) {
      return this.api.get('/scan2borrow/api/staff/circulation/borrower', {
        barcode: String(barcode || '').trim(),
      });
    }

- [ ] Step 4: Run the focused test and verify it passes.

Run the same command. Expected: every test in staff-borrowers.test.js passes with zero failures.

- [ ] Step 5: Commit only this task’s files.

    git add -- frontend/features/staff/services/borrower.service.js frontend/tests/staff-borrowers.test.js
    git commit -m "feat: add staff borrower barcode lookup service"

The commit must not include the pre-existing service-barrel, scanner-test, or upload files.

### Task 2: Build the read-only lookup modal component

Files:

- Create: frontend/tests/borrower-lookup-modal.test.js
- Create: frontend/features/staff/components/borrower-lookup-modal/borrower-lookup-modal.component.js

Interfaces:

- Consumes: service.lookupByBarcode(barcode), a modal root, and an injected scanner factory returning { start(), destroy() }.
- Produces: BorrowerLookupModal.start(), .lookup(barcode), .onShown(), .onHidden(), and .destroy().

The component queries these exact IDs: borrower-lookup-form, borrower-lookup-barcode, borrower-lookup-scanner, borrower-lookup-status, borrower-lookup-result, borrower-lookup-name, borrower-lookup-id, borrower-lookup-role, borrower-lookup-status-value, borrower-lookup-active, borrower-lookup-loans, borrower-lookup-details, and borrower-lookup-circulation.

- [ ] Step 1: Write the failing tests.

Create borrowerLookupFixture() with nodes exposing hidden, disabled, value, textContent, innerHTML, dataset, href, focus(), addEventListener(), and removeEventListener(). Add tests for these exact behaviors:

    test('lookup renders borrower identity and active loans', async () => {
      const fixture = borrowerLookupFixture();
      const modal = new BorrowerLookupModal(fixture.root, {
        service: { lookupByBarcode: async (barcode) => ({ data: {
          borrower: { id: 7, barcode, name: 'Ada Lovelace', role: 'student', status: 'active', active_loans: 1 },
          active_loans: [{ title: 'Clean Code', copy_barcode: 'COPY-001', due_date: '2026-08-27' }],
        } }) },
        document: fixture.document,
        window: {},
      }).start();

      await modal.lookup('  STU-001  ');

      assert.equal(fixture.nodes.result.hidden, false);
      assert.equal(fixture.nodes.name.textContent, 'Ada Lovelace');
      assert.equal(fixture.nodes.id.textContent, 'STU-001');
      assert.match(fixture.nodes.loans.innerHTML, /Clean Code/);
      assert.match(fixture.nodes.loans.innerHTML, /COPY-001/);
      assert.match(fixture.nodes.loans.innerHTML, /2026-08-27/);
      assert.equal(fixture.nodes.details.href, '/scan2borrow/staff/borrower?id=7');
    });

    test('lookup shows an explicit empty state for zero active loans', async () => {
      const fixture = borrowerLookupFixture();
      const modal = new BorrowerLookupModal(fixture.root, {
        service: { lookupByBarcode: async () => ({ data: { borrower: { id: 7, barcode: 'STU-001', name: 'Ada Lovelace', active_loans: 0 }, active_loans: [] } }) },
        document: fixture.document,
        window: {},
      }).start();

      await modal.lookup('STU-001');

      assert.match(fixture.nodes.loans.innerHTML, /No active loans/);
      assert.doesNotMatch(fixture.nodes.loans.innerHTML, /COPY-|Clean Code/);
    });

    test('failed lookup clears stale data and shows the server error', async () => {
      const fixture = borrowerLookupFixture();
      let fail = false;
      const modal = new BorrowerLookupModal(fixture.root, {
        service: { lookupByBarcode: async () => {
          if (fail) throw new Error('Student account not found for that ID.');
          return { data: { borrower: { id: 7, name: 'Ada Lovelace' }, active_loans: [{ title: 'Clean Code' }] } };
        } },
        document: fixture.document,
        window: {},
      }).start();

      await modal.lookup('STU-001');
      fail = true;
      await modal.lookup('UNKNOWN');

      assert.equal(fixture.nodes.result.hidden, true);
      assert.equal(fixture.nodes.loans.innerHTML, '');
      assert.equal(fixture.nodes.status.textContent, 'Student account not found for that ID.');
      assert.equal(fixture.nodes.status.dataset.state, 'error');
    });

    test('scanner lifecycle shares lookup and closes cleanly', async () => {
      const fixture = borrowerLookupFixture();
      let options;
      let lookedUpBarcode = '';
      let destroyed = false;
      const modal = new BorrowerLookupModal(fixture.root, {
        service: { lookupByBarcode: async (barcode) => { lookedUpBarcode = barcode; return { data: { borrower: { id: 7, barcode, name: 'Ada Lovelace', active_loans: 0 }, active_loans: [] } }; } },
        document: fixture.document,
        window: {},
        scannerFactory: (_root, scannerOptions) => { options = scannerOptions; return { start() {}, destroy() { destroyed = true; } }; },
      }).start();

      modal.onShown();
      await options.onDecoded('STU-001');
      await modal.onHidden();

      assert.equal(lookedUpBarcode, 'STU-001');
      assert.equal(destroyed, true);
    });

- [ ] Step 2: Run the test and verify the missing-feature failure.

Run:

    node --test frontend/tests/borrower-lookup-modal.test.js

Expected: the import fails because the new component file does not exist.

- [ ] Step 3: Implement the component.

Implement BorrowerLookupModal with these exact rules:

1. Import BarcodeScannerComponent from ../../../../app/shared/components/barcode-scanner/barcode-scanner.component.js.
2. start() binds the form submit, shown.bs.modal, and hidden.bs.modal listeners, clears the result, and shows Scan or enter a borrower ID to begin.
3. onShown() creates one scanner using scannerFactory(scannerRoot, { document, window, onDecoded }), starts it, and focuses borrower-lookup-barcode.
4. onHidden() awaits scanner destroy(), clears all result text and loan HTML, resets the status, and focuses the trigger.
5. lookup(barcode) trims the value, rejects blank input with Scan or enter a borrower ID barcode., sets loading state, calls service.lookupByBarcode(value), renders data.borrower plus an array data.active_loans, clears stale state on errors, and always re-enables controls.
6. renderResult() sets name, barcode, role, status, active count, the encoded borrower-detail URL, and an escaped loan ledger. A zero-length loan list renders exactly No active loans.
7. setBusy() disables only the lookup form controls. setStatus() writes text via textContent and stores the state in data-state.
8. escape() replaces &, <, >, double quote, and apostrophe before using loan values in innerHTML.
9. destroy() removes all three listeners and awaits onHidden().

- [ ] Step 4: Run the focused component test and verify it passes.

Run the same Node command. Expected: all four modal tests pass with zero failures.

- [ ] Step 5: Commit only the component task.

    git add -- frontend/features/staff/components/borrower-lookup-modal/borrower-lookup-modal.component.js frontend/tests/borrower-lookup-modal.test.js
    git commit -m "feat: add borrower lookup modal component"

### Task 3: Wire the modal into the Borrowers directory

Files:

- Modify: frontend/features/staff/pages/borrowers/borrowers.page.js
- Modify: frontend/features/staff/pages/borrowers/borrowers.html
- Create: frontend/features/staff/pages/borrowers/borrowers.css
- Modify: frontend/tests/staff-borrowers.test.js
- Modify: frontend/tests/staff-pages.test.js

Interfaces:

- Consumes: BorrowerLookupModal and BorrowerService.lookupByBarcode().
- Produces: the /staff/students Scan ID trigger, Bootstrap modal, and BorrowersPage.destroy().

- [ ] Step 1: Write failing page/template tests.

Add a template test that reads borrowers.html and asserts it contains id="borrower-lookup-trigger", data-bs-target="#borrower-lookup-modal", id="borrower-lookup-modal", id="borrower-lookup-form", id="borrower-lookup-scanner", id="borrower-lookup-result", View borrower, and Open circulation.

Add a page-boundary test with a fake document, service, and lookupModalFactory spy. Call await page.start() and assert the factory receives the page service and trigger, then await page.destroy() and assert the returned modal’s destroy() ran.

- [ ] Step 2: Run the focused tests and verify they fail.

    node --test frontend/tests/staff-borrowers.test.js frontend/tests/staff-pages.test.js

Expected: the new template assertions fail because the markup is absent and the page-boundary test fails because no modal is constructed.

- [ ] Step 3: Wire BorrowersPage.

Import BorrowerLookupModal, accept lookupModalFactory with default (root, options) => new BorrowerLookupModal(root, options), and add this logic before load():

    const modalRoot = this.root.getElementById?.('borrower-lookup-modal');
    if (modalRoot) {
      this.lookupModal = this.lookupModalFactory(modalRoot, {
        service: this.service,
        trigger: this.root.getElementById?.('borrower-lookup-trigger'),
        document: this.root,
        window: this.window,
      }).start();
    }
    return this.load();

Add async destroy() { await this.lookupModal?.destroy?.(); }. Leave directory search and row rendering unchanged.

- [ ] Step 4: Add the trigger, modal markup, and stylesheet link.

Add a Scan ID button using data-bs-toggle="modal" and data-bs-target="#borrower-lookup-modal" beside the directory search controls. Add an accessible modal containing:

    #borrower-lookup-modal
    #borrower-lookup-modal-title = Scan borrower ID
    #borrower-lookup-form
    #borrower-lookup-barcode
    #borrower-lookup-scanner with data-scan-target="borrower-lookup-barcode"
    #borrower-lookup-status role="status" aria-live="polite"
    #borrower-lookup-result hidden
    #borrower-lookup-name, #borrower-lookup-id, #borrower-lookup-role
    #borrower-lookup-status-value, #borrower-lookup-active
    #borrower-lookup-loans
    #borrower-lookup-details = View borrower
    #borrower-lookup-circulation = Open circulation

The camera button must not have data-scan-submit; the decoded callback owns lookup submission. Link borrowers.css after style.css.

- [ ] Step 5: Add Swiss-scoped styling.

Create borrowers.css with a flat modal surface: background #f7f7f8, border 1px solid #002fa7, border-radius 0, box-shadow none, and font-family "Helvetica Neue", Arial, sans-serif. Use #002fa7 for header/rules and 1px #c8c8c8 rules for loan rows. Use a responsive single-column loan row below 480px; add no gradients, grain, or decorative shadows.

- [ ] Step 6: Run the focused frontend tests and verify they pass.

    node --test frontend/tests/staff-borrowers.test.js frontend/tests/staff-pages.test.js frontend/tests/borrower-lookup-modal.test.js

Expected: every selected test passes with zero failures.

- [ ] Step 7: Commit the Borrowers integration.

    git add -- frontend/features/staff/pages/borrowers/borrowers.page.js frontend/features/staff/pages/borrowers/borrowers.html frontend/features/staff/pages/borrowers/borrowers.css frontend/tests/staff-borrowers.test.js frontend/tests/staff-pages.test.js
    git commit -m "feat: add scan id borrower lookup to staff directory"

### Task 4: Lock the backend lookup response contract

Files:

- Modify: backend/tests/Unit/StaffCirculation/StaffCirculationControllerTest.php

Interfaces:

- Consumes: existing StaffController::circulationBorrower() and its repository test double.
- Produces: regression coverage proving a staff lookup serializes active-loan data without adding an endpoint.

- [ ] Step 1: Add the contract assertion and fixture data.

After the existing Ada Lovelace assertion, add:

    self::assertStringContainsString('COPY-001', $lookup->toString());

Change the fixture active_loans from [] to:

    'active_loans' => [[
        'id' => 55,
        'copy_barcode' => 'COPY-001',
        'title' => 'Clean Code',
        'due_date' => '2026-08-27',
    ]],

Set the fixture borrower’s active_loans count to 1.

- [ ] Step 2: Run the focused backend test.

    C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml backend\tests\Unit\StaffCirculation\StaffCirculationControllerTest.php

Expected: the focused controller test passes, proving the existing production path already returns active loans. Do not add a new backend endpoint.

- [ ] Step 3: Commit the backend contract test.

    git add -- backend/tests/Unit/StaffCirculation/StaffCirculationControllerTest.php
    git commit -m "test: cover active loans in staff borrower lookup"

### Task 5: Run complete verification and review the diff

Files:

- Verify only; no new files.

- [ ] Step 1: Run the complete frontend gate.

    npm test

Expected: Node exits 0 with zero frontend failures.

- [ ] Step 2: Run the complete backend PHPUnit gate.

    C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml

Expected: PHPUnit exits 0 with zero failures/errors.

- [ ] Step 3: Run the complete backend PHPStan gate.

    C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend\phpstan.neon

Expected: PHPStan exits 0 with no errors.

- [ ] Step 4: Check whitespace, scope, and dirty user changes.

    git diff --check
    git status --short --branch
    git diff --stat origin/master...HEAD
    git diff -- frontend/features/staff/services/index.js frontend/tests/scanner.test.js

Expected: git diff --check is clean; feature commits contain only planned files; the service-barrel and scanner-test edits remain unstaged; the upload remains untracked; branch is still master.

- [ ] Step 5: Check the approved acceptance criteria.

    [ ] /staff/students has Scan ID trigger and modal.
    [ ] Manual and camera scan use the same lookup method.
    [ ] Successful lookup shows borrower identity and exact active loans.
    [ ] Zero-loan lookup shows an explicit empty state with no fabricated rows.
    [ ] Failed lookup clears stale results and shows an actionable error.
    [ ] Closing the modal destroys the scanner and restores trigger focus.
    [ ] /staff/circulation remains covered by existing circulation tests.
    [ ] Backend endpoint remains staff-protected and unchanged.
    [ ] npm test, PHPUnit, PHPStan, and diff checks have fresh passing output.

## Plan self-review

- Spec coverage: The approved goals and acceptance criteria are covered by Tasks 1–5; the existing circulation workflow is preserved and verified through existing tests.
- Placeholder scan: The plan contains no unresolved placeholder markers or incomplete implementation instructions.
- Type/signature consistency: BorrowerService.lookupByBarcode() is consumed by BorrowerLookupModal; BorrowersPage injects that service into the modal; tests use start, lookup, onShown, onHidden, and destroy consistently.
- Scope: This is one cohesive frontend enhancement with backend regression coverage; no database or unrelated refactor is included.
