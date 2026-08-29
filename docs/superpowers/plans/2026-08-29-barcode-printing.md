# Barcode printing implementation plan

## Constraints and verification

- Work only on `feature/barcode-printing` in `.worktrees/barcode-printing`; preserve unrelated changes on `master`.
- Follow TDD: add a focused failing test or contract before each behavior, implement the smallest change, then run the focused test.
- Keep the work between 15 and 40 meaningful commits. Each checkpoint below is intended to produce one commit when it contains a real, reviewable change.
- Database changes must be a new migration, and README/API documentation must describe the migration and endpoints.
- Run `npm test`, backend PHPUnit, PHPStan if available, and targeted migration/security checks before integration.

## Tasks

1. Commit the approved design and plan documents.
2. Add failing backend migration contract tests for `printed_at`, batch tables, indexes, and idempotent migration guards.
3. Add `sql/upgrade_barcode_printing.sql`; make the migration contract pass.
4. Add barcode-print DTOs/value objects and repository interfaces with strict input/output shapes.
5. Add repository contract tests for active unprinted selection, immutable snapshots, history, and re-export lookup.
6. Implement the PDO barcode-print repository with transactions, row locks, and parameterized queries.
7. Add service tests for batch creation, skip behavior, and opaque token validation.
8. Implement the barcode-print application service and transactional result handling.
9. Add controller tests for staff authorization, CSRF, positive title IDs, history lookup, and batch lookup.
10. Implement the protected controller and route table wiring.
11. Wire dependencies in `ApplicationFactory` and add the endpoint catalog documentation.
12. Update README installation order and document irreversible export semantics.
13. Add frontend contract tests for the Copies panel export control, status display, and print route.
14. Add the Copies panel controls and API client behavior for unprinted exports, skip messaging, and history.
15. Add the print page markup and shared-palette print stylesheet, including PDF/export guidance.
16. Implement the print page controller, barcode rendering, escaping, loading/error states, and print action.
17. Add frontend integration tests for print-page query handling, re-export URLs, and no-sidenav scope.
18. Run focused backend/frontend verification; fix any defects and commit the verification changes.
19. Run the full test suites and static analysis; perform a security and regression review.
20. Request code review, address findings, run final verification, then merge the feature branch into `master`.

## Expected files

- `sql/upgrade_barcode_printing.sql`
- `backend/src/Application/DTO/`
- `backend/src/Application/Services/BarcodePrintService.php`
- `backend/src/Http/Controllers/BarcodePrintController.php`
- `backend/src/Http/Routing/BarcodePrintRouteTable.php`
- `backend/src/Infrastructure/Persistence/PdoBarcodePrintRepository.php`
- `backend/src/Infrastructure/Persistence/BarcodePrintRepositoryInterface.php`
- `frontend/features/staff/components/copy-panel/`
- `frontend/features/staff/pages/barcodes/`
- relevant backend/frontend tests, `README.md`, and `ApiEndpointCatalog.php`
