# Inventory Catalog and Copy Workflow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the ambiguous title/barcode inventory workflow with reliable title-level quantity management and explicit physical-copy management.

**Architecture:** Keep `book_titles` as the catalog aggregate and `book_copies` as the physical inventory source of truth. Title mutations accept `title_id`, synchronize active copies transactionally, and generate identifiers; copy mutations accept `copy_id` and validate identifiers independently. Expose separate API actions while retaining compatibility aliases for the existing page.

**Tech Stack:** PHP 8.2+, framework-free PDO/MySQL backend, PHPUnit 11, vanilla ES modules, Bootstrap modals/offcanvas, existing normalized SQL migration.

## Global Constraints

- A title-level inventory row never requires a barcode; physical copies always have unique barcodes.
- `book_titles.quantity` must equal the number of non-archived `book_copies` after successful title mutations.
- Quantity increases create generated copies; quantity decreases archive only removable available copies.
- Active borrowed/reserved copies cannot be removed or manually marked available.
- Existing installations must use `sql/upgrade_bulk_borrowing.sql`; no schema reset or destructive data cleanup is allowed.
- Preserve the unrelated user edit in `frontend/features/guest/pages/profile/guest-profile.page.js`.
- Run tests before claiming completion and merge the isolated branch to `master` only after verification.

---

### Task 1: Define title/copy mutation contracts and failing regression tests

**Files:**
- Modify: `backend/src/Application/DTO/BookMutationRequest.php`
- Create: `backend/src/Application/DTO/BookCopyMutationRequest.php`
- Create: `backend/src/Application/Validators/BookCopyMutationValidator.php`
- Modify: `backend/src/Application/Validators/BookMutationValidator.php`
- Modify: `backend/src/Infrastructure/Persistence/BookMutationRepositoryInterface.php`
- Modify: `backend/src/Infrastructure/Persistence/BookAdministrationRepositoryInterface.php`
- Test: `backend/tests/Unit/Infrastructure/PdoBookRepositoryTest.php`
- Test: `backend/tests/Feature/BookControllerTest.php`

**Interfaces:**
- `BookMutationRequest` continues to carry title metadata and `quantity`; barcode/accession are optional title-creation seed values and are not required for title updates.
- Add `BookCopyMutationRequest(int $copyId, string $barcode, string $accessionNo, string $floorNo, string $sectionName, string $shelfNo, string $rowNo, string $dueDate, string $returnDate, string $status)`.
- Add repository methods `copies(int $titleId): array`, `updateCopy(BookCopyMutationRequest $request): void`, `archiveCopies(array $ids): int`, `restoreCopies(array $ids): int`, and `deleteCopies(array $ids): int`.

- [ ] **Step 1: Write the failing repository tests.** Add tests proving a normalized title update with `barcode: ''`, `accessionNo: ''`, and `quantity: 14` is accepted by the service/repository contract; add tests for generated copy uniqueness, copy identifier updates, and duplicate copy identifier rejection.

- [ ] **Step 2: Run the focused tests and verify RED.**

Run:

```powershell
C:\xampp\php\php.exe backend/vendor/bin/phpunit -c backend/phpunit.xml --filter "PdoBookRepositoryTest|BookControllerTest"
```

Expected: the new tests fail because grouped title updates still validate title barcode fields and copy operations do not exist.

- [ ] **Step 3: Implement the DTO and validator contracts.** Keep `BookMutationValidator::firstError()` responsible only for title requirements: title must be non-empty and quantity must be at least one; do not require barcode during update. Make `BookCopyMutationValidator` reject blank barcode and non-positive copy IDs, and return exact messages such as `Copy barcode is required.` and `Copy accession number is already in use.`.

- [ ] **Step 4: Run the focused tests and verify GREEN.**

Run the same PHPUnit command. Expected: contract and validator tests pass; repository behavior tests may remain red until Task 2.

- [ ] **Step 5: Commit the contract boundary.**

```powershell
git add backend/src/Application backend/src/Infrastructure/Persistence backend/tests
git commit -m "Define explicit title and copy inventory contracts"
```

### Task 2: Implement transactional title and copy persistence

**Files:**
- Modify: `backend/src/Infrastructure/Persistence/PdoBookRepository.php`
- Modify: `backend/src/Application/Services/BookMutationService.php`
- Modify: `backend/src/Application/Services/BookArchiveService.php`
- Test: `backend/tests/Unit/Infrastructure/PdoBookRepositoryTest.php`

**Interfaces:**
- `PdoBookRepository::create(BookMutationRequest): int` creates one `book_titles` row and `quantity` active copies.
- `PdoBookRepository::update(int $titleId, BookMutationRequest): void` updates title metadata and synchronizes active copies without reading title barcode identity.
- `PdoBookRepository::copies(int $titleId): array` returns copy rows ordered by active status and ID.
- `PdoBookRepository::updateCopy(BookCopyMutationRequest): void` updates one copy and never changes its title.

- [ ] **Step 1: Add failing persistence cases for the complete workflow.** Cover: create title with quantity 3 and no barcode/accession; update title from 1 to 14 with no barcode/accession; update title while one copy is borrowed; reject reduction below active copies; update a copy barcode; reject duplicate copy barcode; and roll back title/copies when an identifier insert fails.

- [ ] **Step 2: Run the repository test class and verify RED.**

```powershell
C:\xampp\php\php.exe backend/vendor/bin/phpunit -c backend/phpunit.xml backend/tests/Unit/Infrastructure/PdoBookRepositoryTest.php
```

- [ ] **Step 3: Refactor title synchronization around a single transaction.** In normalized mode, lock the title’s active copies with `SELECT ... FOR UPDATE` on MySQL, calculate active-copy count, archive only available copies when reducing, update title metadata and quantity, and insert the exact difference when increasing. Generate barcodes as `PENDING-{titleId}-{index}-{random}` and accessions as `ACC-{titleId}-{index}` while checking database uniqueness.

- [ ] **Step 4: Add copy persistence.** Query copies by `title_id`; update copy fields using `copy_id`; perform duplicate checks with `id <> :copy_id`; and make archive/restore/delete operate on `book_copies` for normalized installations while preserving active-loan protection.

- [ ] **Step 5: Make unprepared legacy installations fail explicitly.** Before any title quantity create/update operation, detect the absence of `book_titles` and `book_copies` and throw `InvalidArgumentException('Run sql/upgrade_bulk_borrowing.sql before managing quantities.')` instead of silently writing the legacy `books` row without quantity.

- [ ] **Step 6: Run the repository test class and verify GREEN.** Use the command from Step 2. Expected: all repository tests pass.

- [ ] **Step 7: Commit the persistence implementation.**

```powershell
git add backend/src/Application backend/src/Infrastructure/Persistence backend/tests/Unit/Infrastructure/PdoBookRepositoryTest.php
git commit -m "Implement transactional title quantity and copy management"
```

### Task 3: Split controller/API actions and expose useful 422 responses

**Files:**
- Modify: `backend/src/Http/Controllers/BookController.php`
- Modify: `backend/src/Http/Routing/BookRouteTable.php`
- Create: `backend/src/Http/Controllers/BookCopyController.php`
- Modify: `backend/src/Bootstrap/ApplicationFactory.php`
- Test: `backend/tests/Feature/BookControllerTest.php`

**Interfaces:**
- `POST /api/books` accepts `action=create_title|update_title`; `title_id` identifies titles. Existing `create|update` actions translate to these aliases for compatibility.
- `GET /api/book-copies?title_id=<id>` returns `{ok:true,data:[...]}`.
- `POST /api/book-copies` accepts `action=update|archive|restore|delete` and uses `copy_id`/`ids`.

- [ ] **Step 1: Add failing controller tests.** Assert that `update_title` with an empty barcode and quantity 14 reaches the mutation service; invalid duplicate copy barcode returns HTTP 422 with the specific message; missing title ID and missing copy ID return HTTP 422; and copy list returns title-scoped rows.

- [ ] **Step 2: Run the controller tests and verify RED.**

```powershell
C:\xampp\php\php.exe backend/vendor/bin/phpunit -c backend/phpunit.xml backend/tests/Feature/BookControllerTest.php
```

- [ ] **Step 3: Implement explicit title actions.** Parse `title_id` for update, use `bookRequest()` without title barcode validation, catch `InvalidArgumentException`, and return `{ok:false,message:<specific message>}` with status 422. Preserve CSRF and role checks.

- [ ] **Step 4: Implement `BookCopyController`.** Reuse the session/CSRF/role checks, parse `title_id` for reads and `copy_id` for mutations, call the repository/service methods, and return the updated copy/title counts in successful responses.

- [ ] **Step 5: Register the controller and routes.** Construct the controller in `ApplicationFactory` and register `/api/book-copies` GET/POST routes after `/api/books`.

- [ ] **Step 6: Run the controller tests and verify GREEN.** Expected: all focused controller tests pass.

- [ ] **Step 7: Commit the API boundary.**

```powershell
git add backend/src/Http backend/src/Bootstrap/ApplicationFactory.php backend/tests/Feature/BookControllerTest.php
git commit -m "Separate title and physical copy inventory APIs"
```

### Task 4: Make inventory UI title-first and add copy management

**Files:**
- Modify: `frontend/features/staff/pages/inventory/inventory.html`
- Modify: `frontend/features/staff/pages/inventory/inventory.page.js`
- Modify: `frontend/features/staff/services/inventory.service.js`
- Create: `frontend/features/staff/components/copy-panel/copy-panel.component.js`
- Test: `frontend/tests/quantity-display.test.js`
- Test: `backend/tests/Feature/FrontendModuleLayoutTest.php`

**Interfaces:**
- Inventory rows use `data-title-id`/`book.title_id`; title edit submits `action=update_title,title_id,quantity` and never submits a grouped-row barcode as identity.
- `InventoryService.listTitles(params)`, `createTitle(data)`, `updateTitle(data)`, `listCopies(titleId)`, `updateCopy(data)`, and `copyAction(action, ids)` call the corresponding API paths.
- `CopyPanelComponent.open(titleId)` renders copy rows and emits `onChanged` after successful mutations.

- [ ] **Step 1: Add failing frontend contract tests.** Assert that inventory markup contains the copy panel; title editing uses `title_id`; the form does not require a barcode for title updates; generated-copy response data is rendered; and the generic `Request failed.` path is replaced with the server message.

- [ ] **Step 2: Run the frontend tests and verify RED.**

```powershell
npm test -- --runInBand
```

Expected: the new inventory contract assertions fail against the current title/barcode form.

- [ ] **Step 3: Update inventory rendering.** Display title metadata plus total/available/reserved/borrowed counts, store `title_id`, add a View copies button, and keep destructive confirmation for archive/delete.

- [ ] **Step 4: Update title form submission.** Remove required barcode behavior from the title edit path; allow optional seed barcode/accession only on create; submit explicit `create_title`/`update_title` actions; refresh the title list after success; and show the exact response message on failure.

- [ ] **Step 5: Implement the copy panel.** Load copies by title ID, render each copy’s barcode/accession/status/location, submit copy edits by copy ID, use confirmation for archive/delete, and refresh the parent inventory list after any copy mutation.

- [ ] **Step 6: Run frontend tests and verify GREEN.** Use the command from Step 2. Expected: all frontend tests pass.

- [ ] **Step 7: Commit the inventory UI.**

```powershell
git add frontend/features/staff frontend/tests backend/tests/Feature/FrontendModuleLayoutTest.php
git commit -m "Overhaul inventory UI around titles and physical copies"
```

### Task 5: Align documentation, migration contracts, and all quantity consumers

**Files:**
- Modify: `README.md`
- Modify: `backend/src/Http/Documentation/ApiEndpointCatalog.php`
- Modify: `backend/tests/Feature/SchemaContractTest.php`
- Modify: `backend/tests/Feature/StaffDashboardContractTest.php`
- Modify: `backend/tests/Unit/Infrastructure/PdoStaffRepositoryTest.php`
- Modify: `backend/tests/Unit/Infrastructure/PdoBorrowerPortalRepositoryTest.php`

- [ ] **Step 1: Add failing cross-surface assertions.** Assert that API documentation names title/copy actions, README explicitly requires the normalized migration, dashboard totals equal active physical-copy counts after a quantity change, and borrower/search/report responses preserve total and available quantities.

- [ ] **Step 2: Run the focused cross-surface tests and verify RED.**

```powershell
C:\xampp\php\php.exe backend/vendor/bin/phpunit -c backend/phpunit.xml --filter "SchemaContractTest|StaffDashboardContractTest|PdoStaffRepositoryTest|PdoBorrowerPortalRepositoryTest"
```

- [ ] **Step 3: Update documentation and API catalog.** Document title-level quantity editing, generated physical identifiers, copy-level management, and the exact migration order/error. Add the new `/api/book-copies` endpoints to the endpoint catalog.

- [ ] **Step 4: Audit and correct quantity consumers.** Verify staff dashboard, borrower portals, reports, recent transactions, and search use `book_copies` counts and never count grouped title rows as physical copies.

- [ ] **Step 5: Run the focused cross-surface tests and verify GREEN.** Use the command from Step 2.

- [ ] **Step 6: Commit documentation and consistency updates.**

```powershell
git add README.md backend/src/Http/Documentation backend/tests
git commit -m "Document and verify consistent inventory quantities"
```

### Task 6: Full verification and integration

**Files:**
- No new production files; inspect all changes and preserve `frontend/features/guest/pages/profile/guest-profile.page.js`.

- [ ] **Step 1: Run the full backend suite.**

```powershell
C:\xampp\php\php.exe backend/vendor/bin/phpunit -c backend/phpunit.xml
```

Expected: zero failures; existing PHPUnit deprecation notices may remain and must be reported.

- [ ] **Step 2: Run the full frontend suite.**

```powershell
npm test -- --runInBand
```

Expected: zero failures.

- [ ] **Step 3: Verify the live database without destructive changes.** Confirm `book_titles.quantity` equals the count of non-archived copies per title and call the repository dashboard method to confirm totals match the same copy counts.

- [ ] **Step 4: Reproduce the original failure path.** Submit a normalized title update with an empty title barcode and quantity 14; verify HTTP 200, fourteen active copies, unique copy barcodes, and dashboard total increase. Submit a duplicate copy barcode; verify HTTP 422 with the targeted conflict message and no partial mutation.

- [ ] **Step 5: Run `git diff --check`, inspect status, and merge.**

```powershell
git diff --check
git status --short
git checkout master
git merge --no-ff feature/inventory-overhaul -m "Merge inventory catalog and copy overhaul"
```

Do not stage or modify the unrelated guest profile change. After merge, rerun both full test commands from `master`.
