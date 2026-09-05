# Search-Based Borrower Recommendations Design

## Goal

Replace the generic five-title recommendation shelf on the student Search Books
and teacher Borrow Books pages with a content-based filtering (CBF) result based
on the authenticated borrower's recent deliberate text searches. The design must
remain responsive when many borrowers use the catalog concurrently, without any
external cache, vector database, hosted search service, queue, or API.

## Scope

In scope:

- Student and teacher catalog recommendation shelves.
- Persisting validated, non-empty text-search events for authenticated borrowers.
- A ranked CBF query over book-title content and managed keywords.
- Persisting the existing staff inventory keyword input against normalized titles.
- Cold-start and partial-result fallback to newly added available titles.
- An idempotent MySQL migration for the recommendation tables and indexes.
- Backend and frontend tests covering behavior, authorization, and contracts.

Out of scope:

- Guest recommendations, book-view tracking, collaborative filtering, and
  borrowing-history-based recommendations.
- External infrastructure of any kind.
- Changes to borrowing, waitlist, cart, or all-books pagination behavior.

## User experience

The existing five-card `Recommended` shelf remains in both role-specific visual
systems. When the borrower has usable search history, its supporting copy reads
`Based on your searches.` When the borrower has no usable history, or the ranked
result has no matches, it reads `Newly added available books.`

The student and teacher catalog pages submit an intentionally entered non-empty
text search to a role-scoped, CSRF-protected endpoint before navigating to the
existing filtered catalog URL. Recording failure never prevents the catalog
search; it simply means the search will not affect later recommendations.

The recommendation request is independent of the all-books search request and
continues to return at most five cards. Existing card, cart, waitlist, modal,
and accessibility behavior is reused unchanged.

## Recommendation algorithm

The system uses content-based filtering, not collaborative filtering. It derives
a short borrower profile from the 20 newest recorded searches, normalizes and
deduplicates their terms, and gives newer searches greater weight. Terms are
matched against title content and explicit librarian-managed keywords.

The ranking priorities are:

1. Exact managed keyword matches.
2. Category and title full-text matches.
3. Author full-text matches.
4. Description and publisher full-text matches.

Only non-archived titles with an available physical copy are eligible. Titles in
the borrower's current loans are excluded. Ties are resolved by newest title,
then title ID, so results are deterministic.

For a borrower with history, the ranked query first returns matching eligible
titles, then fills any remaining positions with the newest eligible titles that
were not already selected. For a borrower with no usable terms, the endpoint
uses only the newest-eligible-title query. In either case it returns at most
five results and accurately marks whether the result is personalized.

## Local data model and indexing

The migration preserves `search_history` as an append-only audit of deliberate
searches. It ensures the existing lookup index is `(user_id, created_at, id)`;
the primary key makes newest-first bounded history reads efficient.

The legacy `book_keywords` table references physical `books`, whereas borrower
catalog cards now use normalized `book_titles`. The migration creates the
keyword tables when absent and uses title-scoped keyword mappings rather than
the legacy mapping:

- `keywords` continues to hold normalized unique keyword names.
- `book_title_keywords(title_id, keyword_id)` holds unique title/keyword pairs.
  Its primary lookup index is `(keyword_id, title_id)` and its unique constraint
  is `(title_id, keyword_id)`.

The existing comma-separated staff inventory `keywords` field becomes a
normalized title attribute. A title create or update validates, normalizes, and
replaces only that title's mappings in the same transaction as the title change;
the inventory response includes the current keywords for editing.

The migration adds the full-text indexes needed for the weighted title fields:
title; category; author; and publisher/description. It also adds or confirms
the composite title-copy and borrower-current-loan indexes required by the
availability and exclusion predicates. Migration statements are guarded so they
can be applied safely once to a prepared database without dropping data.

## Request flow and component boundaries

1. `BorrowerSearchPage` intercepts a deliberate text-search submission. It
   invokes a role-scoped history API with the CSRF token, then performs the
   existing catalog navigation regardless of tracking success.
2. `SearchHistoryController` authorizes student/teacher identities and delegates
   validated input to `SearchHistoryService`; validation rejects blank and
   overlong values before storage.
3. `RecommendationController` authorizes the same role aliases and delegates to
   `RecommendationService` with the session user ID.
4. `RecommendationService` requests at most 20 history rows from the
   repository, builds the short weighted term profile in PHP, then requests the
   five-title result.
5. `PdoRecommendationRepository` uses prepared parameters and indexed joins /
   full-text predicates. It never performs a query per book, scans unbounded
   history, or returns a full catalog for PHP-side ranking.
6. `BorrowerSearchPage` renders existing book cards and uses the API's
   `personalized` marker to select the honest supporting copy.

Student and teacher routes remain aliases over shared controllers and services:
`/api/student/...` and `/api/teacher/...`. Their page-specific endpoints and
presentation remain role-owned.

## Performance and concurrency constraints

Each recommendation request does bounded work: one indexed read of at most 20
history rows, one indexed ranking query returning at most five titles, and only
when a partial personalized result must be completed, one additional indexed
fallback query for the remaining positions. Full-text indexes select matching catalog candidates before the
availability and loan-exclusion checks; keyword joins begin from the indexed
keyword-to-title mapping. Queries are prepared, use a fixed result limit, and
do not use offset pagination, N+1 reads, table-wide history aggregation, or
per-request schema introspection.

Search-history writes are a single insert after validation. Recommendation reads
perform no writes and require no shared mutable cache, which avoids cache stampede
and invalidation issues under concurrent use. Database connections, PHP workers,
and MySQL limits remain deployment concerns, but the feature adds a fixed small
query budget rather than work proportional to total users, history, or catalog
size.

## Failure handling and privacy

- A tracking request failure is non-blocking and does not change search results.
- Empty history or no ranked match returns an honest cold-start result.
- Recommendation endpoint failures retain the existing unavailable state and
  keep the all-books catalog usable.
- Only submitted text searches are recorded: filter changes, empty searches,
  recommendation loads, and automatic page loads are not tracked.
- History is scoped to the session user ID; one borrower cannot retrieve or
  influence another borrower's profile.
- Responses expose book data and the personalization marker, never raw history,
  profile terms, scores, SQL, or internal errors.

## Verification

Backend tests will establish input validation, CSRF and role isolation, bounded
history use, weighted ranking order, fallback filling, current-loan exclusion,
keyword persistence, and parameterized repository behavior. Migration/schema
tests will assert the normalized title-keyword table and required indexes.

Frontend tests will establish that both role pages call their matching history
and recommendation routes, preserve search navigation after tracking failure,
render the personalized and cold-start copy truthfully, and preserve the
existing recommended-card, waitlist, cart, and all-books behavior.

The affected Node test suite, PHPUnit suite, PHPStan analysis, and the relevant
fresh CI-equivalent checks will run before completion. Remote GitHub Actions, if
available, will be checked separately after any push.
