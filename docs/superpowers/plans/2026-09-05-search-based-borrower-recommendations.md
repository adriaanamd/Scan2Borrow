# Search-Based Borrower Recommendations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Personalize the five-title borrower recommendation shelves using bounded search-based content filtering and a truthful new-title fallback.

**Architecture:** A CSRF-protected endpoint records deliberate searches. A service reads no more than 20 rows, builds a weighted profile, and asks an indexed PDO repository for at most five ranked/fallback titles. The existing shared borrower page calls role-scoped APIs while retaining catalog, cart, modal, and waitlist behavior.

**Tech Stack:** PHP 8.2, PDO/MySQL/MariaDB full-text and composite indexes, vanilla ES modules, Node test runner, PHPUnit 11, PHPStan 2.

## Global Constraints

- Use only existing PHP and MySQL/MariaDB: no external cache, vector database, hosted search, queue, API, library, or dependency.
- Track only deliberate non-empty text searches. Bound history to 20, profile terms to 25, and results to five.
- Rank explicit keywords, category, title, author, then publisher/description; exclude archived, unavailable, and currently borrowed titles.
- Use prepared statements, fixed limits, indexed joins, no unbounded history, no N+1 reads, and no full-catalog PHP ranking.
- Require session and CSRF for writes; preserve student/teacher route aliases and all current borrower interactions.
- Show `Based on your searches.` only for personalized results, otherwise `Newly added available books.`
- Keep migrations idempotent and data preserving. Run targeted tests after each task and the complete frontend, PHPUnit, and PHPStan gates before completion.

---

### Task 1: Add the local recommendation schema and contract

**Files:**

- Create: `sql/upgrade_search_recommendations.sql`
- Modify: `README.md`
- Modify: `backend/tests/Feature/SchemaContractTest.php`

**Interfaces:** Produces normalized title-keyword mappings and the named indexes used by the repositories.

- [ ] **Step 1: Write the failing schema contract**

Add `testSearchRecommendationMigrationCreatesNormalizedIndexes()` and add the migration filename to `testAllExistingUpgradeScriptsRemainAvailable()`. The new test reads the migration and requires:

```php
foreach ([
    'CREATE TABLE IF NOT EXISTS `search_history`',
    'KEY `idx_search_history_user_created` (`user_id`, `created_at`, `id`)',
    'CREATE TABLE IF NOT EXISTS `keywords`',
    'CREATE TABLE IF NOT EXISTS `book_title_keywords`',
    'UNIQUE KEY `uq_book_title_keyword` (`title_id`, `keyword_id`)',
    'KEY `idx_book_title_keywords_keyword_title` (`keyword_id`, `title_id`)',
    'FULLTEXT KEY `ft_book_titles_title` (`title`)',
    'FULLTEXT KEY `ft_book_titles_category` (`category_name`)',
    'FULLTEXT KEY `ft_book_titles_author` (`author`)',
    'FULLTEXT KEY `ft_book_titles_publisher_description` (`publisher`, `description`)',
    'KEY `idx_copies_status_deleted_title` (`status`, `deleted_at`, `title_id`)',
    'KEY `idx_transactions_user_return_id` (`user_id`, `return_date`, `id`)',
] as $marker) self::assertStringContainsString($marker, $migration);
```

- [ ] **Step 2: Verify the test is red**

Run:

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml --filter SchemaContractTest
```

Expected: FAIL because the migration or markers are absent.

- [ ] **Step 3: Create the migration and README instruction**

Start with:

```sql
-- Run after sql/upgrade_bulk_borrowing.sql.
USE `scan2borrow_2.0`;
```

Create `search_history`, `keywords`, and `book_title_keywords` with `CREATE TABLE IF NOT EXISTS`; the mapping foreign key must target `book_titles(id)`, never legacy `books(id)`. Follow existing migrations: use a temporary procedure that checks `information_schema.statistics` for each named index before its `ALTER TABLE ... ADD INDEX` or `ADD FULLTEXT`, then drop it. Add indexes only. List the migration after `upgrade_profile_change_requests.sql` in both README installation sequences.

- [ ] **Step 4: Verify the test is green**

Run the Step 2 command. Expected: PASS.

- [ ] **Step 5: Commit Task 1**

```powershell
git add README.md sql/upgrade_search_recommendations.sql backend/tests/Feature/SchemaContractTest.php
git commit -m "feat: add search recommendation schema"
```

### Task 2: Build a test-first bounded profile and service layer

**Files:**

- Create: `backend/src/Application/DTO/SearchProfile.php`
- Create: `backend/src/Application/DTO/RecommendationResult.php`
- Create: `backend/src/Application/Services/SearchHistoryService.php`
- Create: `backend/src/Application/Services/RecommendationService.php`
- Create: `backend/src/Infrastructure/Persistence/SearchHistoryRepositoryInterface.php`
- Create: `backend/src/Infrastructure/Persistence/RecommendationRepositoryInterface.php`
- Create: `backend/tests/Unit/Recommendation/{SearchProfileTest,SearchHistoryServiceTest,RecommendationServiceTest}.php`

**Interfaces:** Produces `SearchHistoryService::record(int, string): void` and `RecommendationService::forBorrower(int): RecommendationResult`.

- [ ] **Step 1: Write failing unit tests**

Use in-memory interface fakes. Assert `record(7, '  C++  ')` persists `C++`; blank/whitespace, over-255-character, and non-positive-user inputs throw `InvalidArgumentException`. Assert `SearchProfile::fromRecentSearches()` removes punctuation-only terms, de-duplicates case-insensitively, preserves `C++`/`C#`, caps at 25 terms, and gives later history greater term weight.

```php
$history = new FakeSearchHistoryRepository(['old gardening', 'php security', 'PHP testing']);
$catalog = new FakeRecommendationRepository([['id' => 9, 'title' => 'PHP Testing']]);
$result = (new RecommendationService($history, $catalog))->forBorrower(7);
self::assertTrue($result->personalized());
self::assertSame(20, $history->receivedLimit);
self::assertSame(5, $catalog->receivedLimit);
self::assertGreaterThan($catalog->receivedProfile->weights()['gardening'], $catalog->receivedProfile->weights()['php']);
```

Also assert empty history is fallback-only, while partial personalized results request only the remaining fallback count and never return duplicate `id`/`title_id` values.

- [ ] **Step 2: Verify the tests are red**

Run:

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml backend/tests/Unit/Recommendation
```

Expected: FAIL because DTOs/services/interfaces are absent.

- [ ] **Step 3: Implement the fixed contracts and services**

```php
interface SearchHistoryRepositoryInterface {
    public function record(int $userId, string $query): void;
    /** @return list<string> */
    public function recentQueries(int $userId, int $limit): array;
}
interface RecommendationRepositoryInterface {
    /** @return list<array<string, mixed>> */
    public function recommend(SearchProfile $profile, int $userId, int $limit): array;
    /** @return list<array<string, mixed>> */
    public function newestEligible(int $userId, int $limit): array;
}
```

Implement immutable `SearchProfile` methods `fromRecentSearches(array): self`, `weights(): array`, `terms(): array`, and `isEmpty(): bool`. Normalize Unicode whitespace, retain `+`/`#`, ignore terms shorter than two characters except `C#`, cap a term at 50 characters, and process oldest-to-newest with weights `1..count($queries)`. `SearchHistoryService` collapses whitespace, enforces a positive user ID and 1â€“255 characters, then writes. `RecommendationService` defines `HISTORY_LIMIT = 20` and `RESULT_LIMIT = 5`, reads only bounded history, never loads catalog candidates into PHP, and removes duplicate title IDs while filling a partial ranked result.

- [ ] **Step 4: Verify Task 2 is green**

Run:

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml backend/tests/Unit/Recommendation
C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon
```

Expected: both commands exit 0.

- [ ] **Step 5: Commit Task 2**

```powershell
git add backend/src/Application/DTO backend/src/Application/Services backend/src/Infrastructure/Persistence backend/tests/Unit/Recommendation
git commit -m "feat: add bounded recommendation services"
```


### Task 3: Implement prepared PDO ranking and normalized keyword persistence

**Files:**

- Create: backend/src/Infrastructure/Persistence/PdoSearchHistoryRepository.php
- Create: backend/src/Infrastructure/Persistence/PdoRecommendationRepository.php
- Modify: backend/src/Infrastructure/Persistence/PdoBookRepository.php
- Modify: backend/src/Http/Controllers/BookController.php
- Create: backend/tests/Unit/Infrastructure/PdoSearchHistoryRepositoryTest.php
- Create: backend/tests/Unit/Infrastructure/PdoRecommendationRepositoryTest.php
- Modify: backend/tests/Unit/Infrastructure/PdoBookRepositoryTest.php

**Interfaces:** Implements Task 2 contracts; synchronizes the staff inventory keywords field against normalized titles.

- [ ] **Step 1: Write failing persistence tests**

Use the existing SQLite in-memory style. Assert recentQueries(7, 20) reads only user 7 in created_at DESC, id DESC order and record() saves user/query. Extend normalized-book tests: create keywords and book_title_keywords, create with php/testing/PHP, assert two terms and mappings, update to security, and assert stale mappings disappear.

Because SQLite does not implement production full-text syntax, add a package-visible pure rankingSqlForTests(): string and assert its template contains MATCH(t.title) AGAINST, MATCH(t.category_name) AGAINST, MATCH(t.author) AGAINST, book_title_keywords, an available-copy predicate, NOT EXISTS, and LIMIT :limit.

- [ ] **Step 2: Verify persistence tests are red**

Run:

~~~powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml --filter "Pdo(SearchHistory|Recommendation|BookRepository)Test"
~~~

Expected: FAIL because repositories, ranking SQL, or keyword synchronization are absent.

- [ ] **Step 3: Implement prepared, bounded persistence**

PdoSearchHistoryRepository::record() executes one prepared INSERT into search_history (user_id, search_query). recentQueries() binds user_id and integer limit, clamps to 20, selects only search_query, and orders by newest created_at/id.

The recommendation template binds profile_query, keyword terms, user_id, and limit. Use boolean-mode full-text MATCH/AGAINST with score constants keyword 16, category 12, title 10, author 7, publisher/description 3. Use indexed EXISTS for live available copies and indexed NOT EXISTS across active normalized borrowing rows for current-loan exclusion. Order score DESC, created_at DESC, title ID DESC. The fallback uses identical eligibility/exclusion/card fields but newest ordering. Both queries return at most five and calculate copy counts inside one statement.

Add syncTitleKeywords(int $titleId, array $keywords): void inside the normalized create/update transaction: lowercase/trim/deduplicate terms, delete mappings only for this title, insert names with INSERT ... ON DUPLICATE KEY UPDATE, and insert mappings with INSERT IGNORE. Return comma-separated current keywords in normalized catalog rows. Add a BookController keywords(array $body): array helper that splits the existing comma-separated form input and passes it to BookMutationRequest.

- [ ] **Step 4: Verify Task 3 is green**

Run:

~~~powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml --filter "Pdo(SearchHistory|Recommendation|BookRepository)Test"
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml
C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon
~~~

Expected: all commands exit 0.

- [ ] **Step 5: Commit Task 3**

~~~powershell
git add backend/src/Infrastructure/Persistence backend/src/Http/Controllers/BookController.php backend/tests/Unit/Infrastructure
git commit -m "feat: rank recommendations from search history"
~~~

### Task 4: Expose authorized history and recommendation endpoints

**Files:**

- Create: backend/src/Http/Controllers/SearchHistoryController.php
- Create: backend/src/Http/Controllers/RecommendationController.php
- Modify: backend/src/Http/Routing/BookRouteTable.php
- Modify: backend/src/Bootstrap/ApplicationFactory.php
- Create: backend/tests/Feature/SearchRecommendationControllerTest.php
- Modify: backend/src/Http/Documentation/ApiEndpointCatalog.php

**Interfaces:** Produces POST /api/{student,teacher}/search-history and GET /api/{student,teacher}/recommendations.

- [ ] **Step 1: Write failing endpoint and route tests**

Following BookControllerTest session-store fakes, assert all aliases route; unauthenticated and staff identities receive 401; valid student/teacher requests receive 200; missing CSRF on POST receives 419; blank search receives 422. Assert safe success bodies are { ok: true, data: { recorded: true } } and { ok: true, data: { books: [{ id: 12, title: 'PHP Testing' }], personalized: true } }. Assert no response exposes history, profile terms, score, or SQL.

- [ ] **Step 2: Verify endpoint tests are red**

Run:

~~~powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml backend/tests/Feature/SearchRecommendationControllerTest.php
~~~

Expected: FAIL due to missing controllers/routes.

- [ ] **Step 3: Implement controllers, routing, factory wiring, and API documentation**

Both controllers use the existing student/teacher role policy. SearchHistoryController::record() validates CSRF before search, maps service InvalidArgumentException to 422, and returns { ok: true, data: { recorded: true } }. RecommendationController::index() calls forBorrower(identity user ID) and returns only books/personalized.

Extend BookRouteTable::routes() to accept the new controllers and append POST student/teacher search-history aliases and GET student/teacher recommendations aliases. Wire one PDO history repository, one PDO recommendation repository, both services, and both controllers in ApplicationFactory. Document aliases, authorization, requests, and safe response fields in the API catalog.

- [ ] **Step 4: Verify Task 4 is green**

Run:

~~~powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml backend/tests/Feature/SearchRecommendationControllerTest.php
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml
C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon
~~~

Expected: all commands exit 0.

- [ ] **Step 5: Commit Task 4**

~~~powershell
git add backend/src/Bootstrap/ApplicationFactory.php backend/src/Http backend/tests/Feature
git commit -m "feat: expose borrower recommendation APIs"
~~~


### Task 5: Render endpoint-backed recommendations in both borrower catalogs

**Files:**

- Modify: frontend/app/shared/pages/borrower-search.page.js
- Modify: frontend/features/student/pages/search/student-search.page.js
- Modify: frontend/features/teacher/pages/borrow/teacher-borrow.page.js
- Modify: frontend/features/student/pages/search/search.html
- Modify: frontend/features/teacher/pages/borrow/borrow.html
- Modify: frontend/tests/borrower-catalog.test.js
- Create: frontend/tests/borrower-search-recommendations.test.js
- Modify: frontend/tests/student-library-surfaces.test.js
- Modify: frontend/tests/teacher-borrow-history-surfaces.test.js

**Interfaces:** Consumes Task 4 envelope { ok, data: { books, personalized } } and produces safe tracking plus truthful recommendation supporting copy.

- [ ] **Step 1: Write failing frontend contracts**

Create borrower-search-recommendations.test.js. It must assert recommendationCopy(true) is Based on your searches. and recommendationCopy(false) is Newly added available books. Stub fetch and a minimal document/window/form: recordSearch('  php testing  ') sends one POST to the configured history API with application/x-www-form-urlencoded data containing search=php+testing and CSRF; blank input makes no request; a rejected request resolves so navigation may continue. Assert loadRecommendations() calls recommendationApi with no catalog query and passes payload.data.personalized to rendering.

Update borrower-catalog.test.js to remove the generic recommendationQuery expectation and require the exact subclass endpoint pairs:
student: /scan2borrow/api/student/search-history and /scan2borrow/api/student/recommendations;
teacher: /scan2borrow/api/teacher/search-history and /scan2borrow/api/teacher/recommendations.
Update both surface tests to require id="recommendation-supporting-copy".

- [ ] **Step 2: Verify frontend contracts are red**

Run:

~~~powershell
node --test frontend/tests/borrower-search-recommendations.test.js frontend/tests/borrower-catalog.test.js frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
~~~

Expected: FAIL because methods, endpoint values, or mounts are absent.

- [ ] **Step 3: Implement tracking and rendering**

Extend BorrowerSearchPage constructor options with searchHistoryApi and recommendationApi; pass exact role aliases from both subclasses. Add:

~~~js
recommendationCopy(personalized) {
  return personalized ? 'Based on your searches.' : 'Newly added available books.';
}

async recordSearch(search) {
  const normalized = String(search || '').trim().replace(/\s+/g, ' ');
  if (!normalized) return;
  try {
    await fetch(this.searchHistoryApi, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        'X-Requested-With': 'fetch',
      },
      body: new URLSearchParams({ search: normalized, csrf: this.csrf }),
    });
  } catch {
    // Optional tracking must not prevent catalog navigation.
  }
}
~~~

In the existing form handler, preserve the current destination URL, call recordSearch(query.get('search')), then navigate in finally; filter-only submits must not record history. Request only recommendationApi in loadRecommendations(), validate the existing ok envelope, and call renderRecommendations(books, Boolean(personalized)). Set recommendation-supporting-copy through textContent before retaining existing card rendering. Add the supporting-copy paragraph to both recommendation headers without changing existing IDs, classes, keyboard behavior, cart, waitlist, or all-books controls.

- [ ] **Step 4: Verify Task 5 is green**

Run:

~~~powershell
node --test frontend/tests/borrower-search-recommendations.test.js frontend/tests/borrower-catalog.test.js frontend/tests/student-library-surfaces.test.js frontend/tests/teacher-borrow-history-surfaces.test.js
npm test
~~~

Expected: all tests pass with no focused or skipped test added.

- [ ] **Step 5: Commit Task 5**

~~~powershell
git add frontend/app/shared/pages/borrower-search.page.js frontend/features/student/pages/search frontend/features/teacher/pages/borrow frontend/tests
git commit -m "feat: personalize borrower book recommendations"
~~~

### Task 6: Run the complete CI-equivalent verification gate

**Files:**

- Modify only a task-owned file if a fresh verification failure identifies its root cause.

**Interfaces:** Consumes all prior tasks and produces fresh test, analysis, and diff evidence.

- [ ] **Step 1: Inspect scope and whitespace**

Run:

~~~powershell
git status --short
git diff --check HEAD~5..HEAD
git log -5 --oneline
~~~

Expected: only task-owned files are present and diff check emits no whitespace errors.

- [ ] **Step 2: Run the complete frontend gate**

Run:

~~~powershell
npm test
~~~

Expected: exit 0 in under 110 seconds.

- [ ] **Step 3: Run the complete backend gate**

Run:

~~~powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml
C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon
~~~

Expected: both exit 0 in under 110 seconds, with no PHPUnit failure/warning/risky test and no PHPStan error.

- [ ] **Step 4: Audit every requirement against the fresh evidence**

Confirm: no external infrastructure; role-scoped authenticated APIs; CSRF write; 20-search/five-result caps; indexes; keyword persistence; current-loan/availability exclusion; bounded prepared queries; fallback; honest copy; non-blocking tracking; preserved borrower interactions; migration and README instructions; frontend, PHPUnit, and PHPStan pass. For a gap, write its failing regression test first, fix only the root cause, and rerun Steps 2â€“3.

- [ ] **Step 5: Commit only verification root-cause fixes and report results**

If a root-cause fix was necessary, use the exact changed path and its regression-test path shown by `git status --short`, stage only those two paths, and commit with `fix: verify search recommendations`. If no fix was necessary, do not create an empty commit. Report each actual command and exit result. Remote GitHub Actions remains unverified unless a push and run are available.
