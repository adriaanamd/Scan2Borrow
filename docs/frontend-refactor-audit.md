# Vanilla Angular-like frontend audit

The frontend is still served as static HTML and native browser ES modules. It now follows an Angular-like ownership model without introducing Angular or a client-side router.

## Ownership model

- `frontend/app/` contains application-wide bootstrap, API, session, guards, and shared UI components.
- `frontend/features/auth/` owns authentication pages and authentication services.
- `frontend/features/student/`, `teacher/`, `guest/`, and `staff/` own their models, services, components, page controllers, templates, and page entries.
- `frontend/assets/` remains the shared visual and browser-helper asset layer.
- `frontend/tests/` contains native Node contract tests for modules and served-template parity.

Each canonical template has one `data-app-page` marker and one `type="module"` entry. `frontend/app/bootstrap/page-registry.js` remains available for role-level bootstrapping, while direct page entries preserve the existing static-page deployment model.

## Server cutover

`backend/src/Http/Routing/PageRouteTable.php` now maps every application route to a feature-owned template. `backend/tests/Support/FrontendPagePaths.php` is the single test-side route/template map used by page contracts.

Apache continues to expose static assets and browser modules, but denies direct access to both the legacy `frontend/pages` templates and canonical feature HTML templates. HTML is therefore returned through the existing PHP page controller, which preserves CSRF injection and route authorization.

## Compatibility boundary

Legacy page controllers and templates remain in the repository as compatibility fixtures while the route table and canonical templates are exercised. They are not the route targets and are denied direct web access. Existing shared core helpers remain available because they are reusable browser infrastructure. The pre-existing working-tree changes in `frontend/assets/js/pages/registration.js` and `frontend/assets/js/pages/student-search.js` were preserved.

## Verification

- `npm test` — 68 passing native-module tests, including served feature-template parity.
- `powershell -File tests/browser/frontend-module-parity.ps1` — local HTTP route/module/source protection smoke check passed.
- PHP lint passed for every changed PHP contract and support file.
- `git diff --check` passed for the refactor changes.
- Full PHPUnit execution is not available in this checkout because Composer dependencies (`vendor/bin/phpunit`) are absent; the project’s PHP runtime is available at `C:\xampp\php\php.exe`.

The refactor intentionally preserves existing routes, API paths, form names, redirects, polling intervals, scanner/camera constraints, print behavior, Bootstrap/CSS assets, and visible page markup. The parity tests focus on those contracts rather than introducing a new UI system.
