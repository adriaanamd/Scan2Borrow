# Search-based borrower recommendations

This runbook lets a second Scan2Borrow checkout enable the student and teacher
recommendation shelves locally. The feature uses the existing PHP/MySQL stack;
it does not require a cache, queue, hosted search service, or any other
external infrastructure.

## 1. Sync and configure the copy

From the project root, make sure the recommendation commits are present and the
working tree is clean:

```powershell
Set-Location 'C:\xampp\htdocs\scan2borrow'
git switch master
git pull --ff-only
git status --short --branch
```

If dependencies are not already present on the copy, install the PHP tools
before running the checks:

```powershell
if (-not (Test-Path 'backend\vendor\autoload.php')) {
    composer install --working-dir=backend --no-interaction
}
```

Copy `config\.env.example` to `config\.env` if needed and set the local
database values. The bundled SQL scripts target `scan2borrow_2.0`:

```dotenv
DB_HOST=localhost
DB_PORT=3306
DB_NAME=scan2borrow_2.0
DB_USER=root
DB_PASS=
```

If `DB_NAME` is different, replace the database name in each SQL script's
`USE` statement before running it. Do not point these commands at a database that
contains data you need to preserve until the target and backup have been
confirmed.

## 2. Prepare the schema

The recommendation migration must run after the normalized catalog migration
(`upgrade_bulk_borrowing.sql`). For a fresh local database, import the base
schema first and then apply the feature migrations required by the checkout:

```powershell
Set-Location 'C:\xampp\htdocs\scan2borrow'
$mysql = 'C:\xampp\mysql\bin\mysql.exe'

Get-Content -Raw 'sql\database.sql' |
    & $mysql --protocol=tcp -h localhost -P 3306 -u root
if ($LASTEXITCODE -ne 0) { throw 'Base schema import failed.' }

$upgradeFiles = @(
    'sql\upgrade_bulk_borrowing.sql',
    'sql\upgrade_approval_status_sync.sql',
    'sql\upgrade_return_approval.sql',
    'sql\upgrade_barcode_printing.sql',
    'sql\upgrade_copy_audit_trail.sql',
    'sql\upgrade_profile_change_requests.sql',
    'sql\upgrade_search_recommendations.sql'
)

foreach ($file in $upgradeFiles) {
    Write-Host "Applying $file"
    Get-Content -Raw $file | & $mysql --protocol=tcp -h localhost -P 3306 -u root
    if ($LASTEXITCODE -ne 0) { throw "Migration failed: $file" }
}
```

For an existing database, do not re-import `database.sql`. Apply only missing
upgrade scripts in the repository's documented order, ending with:

```powershell
Get-Content -Raw 'sql\upgrade_search_recommendations.sql' |
    & 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root
if ($LASTEXITCODE -ne 0) { throw 'Recommendation migration failed.' }
```

The recommendation migration is idempotent: it creates missing normalized
keyword/history tables and adds guarded indexes, so a rerun is safe after a
partial setup. It is not applied automatically by the PHP application.

## 3. Verify the recommendation schema

Run this read-only check against the configured database:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' --protocol=tcp -h localhost -P 3306 -u root `
    --database='scan2borrow_2.0' -e @"
SHOW TABLES LIKE 'search_history';
SHOW TABLES LIKE 'keywords';
SHOW TABLES LIKE 'book_title_keywords';
SHOW INDEX FROM search_history;
SHOW INDEX FROM book_title_keywords;
SHOW INDEX FROM book_titles WHERE Index_type = 'FULLTEXT';
SHOW INDEX FROM book_copies WHERE Key_name = 'idx_copies_status_deleted_title';
SHOW INDEX FROM borrowing_transactions WHERE Key_name = 'idx_transactions_user_return_id';
"@
```

The output must include `book_title_keywords`, the
`idx_search_history_user_created` lookup, four full-text indexes on
`book_titles`, and the availability/current-loan indexes.

## 4. What the feature does

- Only deliberate, non-empty text searches are recorded. Filter-only changes,
  empty searches, automatic page loads, and recommendation loads are ignored.
- The latest 20 searches form a weighted profile; newer searches carry more
  weight. Terms are matched against managed keywords, title, category, author,
  publisher, and description.
- Results are limited to five non-archived titles with an available copy, and a
  borrower's current loans are excluded.
- If history is empty or no ranked match exists, the shelf fills with the
  newest eligible titles and reports `personalized: false`.

The inventory Keywords field is synchronized to normalized title keywords when
staff create or edit a title. No manual backfill command is required beyond
running the migration; edit a title once if an old row needs its keywords
re-saved.

## 5. API and browser behavior

Authenticated role-scoped endpoints are:

| Method | Endpoint | Body/response |
| --- | --- | --- |
| GET | `/api/student/recommendations` | Up to five books and `personalized`. |
| GET | `/api/teacher/recommendations` | Up to five books and `personalized`. |
| POST | `/api/student/search-history` | Form fields `search` and `csrf`. |
| POST | `/api/teacher/search-history` | Form fields `search` and `csrf`. |

The response envelope never exposes raw search history, profile terms, scores,
SQL, or internal errors. Tracking failures do not block catalog navigation.
The student Search Books page and teacher Borrow Books page already pass their
role-specific endpoints and render `Based on your searches.` or
`Newly added available books.` from the response flag.

The successful response shape is:

```json
{
  "ok": true,
  "data": {
    "books": [{ "id": 12, "title": "PHP Testing" }],
    "personalized": true
  }
}
```

The search-history response is `{ "ok": true, "data": { "recorded": true } }`.

## 6. Verify the copy

From the project root:

```powershell
npm test
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml backend\tests\Unit\Recommendation backend\tests\Unit\Infrastructure\PdoSearchHistoryRepositoryTest.php backend\tests\Unit\Infrastructure\PdoRecommendationRepositoryTest.php backend\tests\Feature\BorrowerRecommendationControllerTest.php backend\tests\Feature\SchemaContractTest.php
C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon
```

Then sign in as a student or teacher, submit two different text searches,
return to the unfiltered catalog, and confirm the supporting copy says
`Based on your searches.`. A new user should see
`Newly added available books.` until a deliberate search is recorded.

The repository may still report unrelated pre-existing PHPUnit/PHPStan debt;
the recommendation-focused tests above should pass without modifying those
baseline failures.

## Troubleshooting

- **`book_titles` or `book_title_keywords` is missing:** run
  `upgrade_bulk_borrowing.sql` first, then rerun
  `upgrade_search_recommendations.sql`.
- **The API always shows fallback books:** confirm the POST history request is
  returning 200, the borrower session is valid, and the search is non-empty.
- **A custom database name fails:** update the `USE` clause in the migration
  files; `--database` alone cannot override a script's explicit `USE`.
- **A title has no keyword matches:** save its comma-separated Keywords value
  from the staff inventory editor so the normalized mapping is refreshed.
