<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\SearchProfile;
use PDO;

final readonly class PdoRecommendationRepository implements RecommendationRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function recommend(SearchProfile $profile, int $userId, int $limit): array
    {
        if ($profile->isEmpty()) {
            return [];
        }

        $terms = $profile->terms();
        $keywordPlaceholders = [];
        $parameters = [
            'user_id' => $userId,
            'limit' => max(1, min(5, $limit)),
        ];
        foreach ($terms as $index => $term) {
            $placeholder = ':keyword_' . $index;
            $keywordPlaceholders[] = $placeholder;
            $parameters['keyword_' . $index] = $term;
        }
        $profileQuery = $profile->fullTextQuery();
        foreach (['title', 'category', 'author', 'publisher_description'] as $field) {
            $parameters['profile_' . $field] = $profileQuery;
        }

        $statement = $this->pdo->prepare($this->rankingSql(implode(', ', $keywordPlaceholders)));
        $this->bindParameters($statement, $parameters);
        $statement->execute();
        /** @var list<array<string, mixed>> $books */
        $books = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $books;
    }

    /** @return list<array<string, mixed>> */
    public function newestEligible(int $userId, int $limit): array
    {
        $statement = $this->pdo->prepare($this->fallbackSql());
        $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue('limit', max(1, min(5, $limit)), PDO::PARAM_INT);
        $statement->execute();
        /** @var list<array<string, mixed>> $books */
        $books = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $books;
    }

    public function rankingSqlForTests(): string
    {
        return $this->rankingSql(':keyword_0');
    }

    private function rankingSql(string $keywordPlaceholders): string
    {
        return 'SELECT t.id, t.id AS title_id, NULL AS barcode, t.isbn, t.title, t.author, t.publisher, '
            . 't.category_name, t.cover_file, t.description, t.quantity, '
            . "(SELECT COUNT(*) FROM book_copies available_copy WHERE available_copy.title_id = t.id "
            . "AND available_copy.status = 'Available' AND available_copy.deleted_at IS NULL) AS available_quantity, "
            . '(SELECT COUNT(*) FROM book_copies borrowed_copy WHERE borrowed_copy.title_id = t.id '
            . "AND borrowed_copy.status = 'Borrowed' AND borrowed_copy.deleted_at IS NULL) AS borrowed_quantity, "
            . 'MIN(copy_location.floor_no) AS floor_no, MIN(copy_location.section_name) AS section_name, '
            . 'MIN(copy_location.shelf_no) AS shelf_no, MIN(copy_location.row_no) AS row_no, '
            . "(CASE WHEN EXISTS (SELECT 1 FROM book_title_keywords title_keyword "
            . 'JOIN keywords keyword ON keyword.id = title_keyword.keyword_id '
            . 'WHERE title_keyword.title_id = t.id AND keyword.name IN (' . $keywordPlaceholders . ')) THEN 16 ELSE 0 END '
            . '+ 12 * COALESCE(MATCH(t.category_name) AGAINST (:profile_category IN BOOLEAN MODE), 0) '
            . '+ 10 * COALESCE(MATCH(t.title) AGAINST (:profile_title IN BOOLEAN MODE), 0) '
            . '+ 7 * COALESCE(MATCH(t.author) AGAINST (:profile_author IN BOOLEAN MODE), 0) '
            . '+ 3 * COALESCE(MATCH(t.publisher, t.description) AGAINST (:profile_publisher_description IN BOOLEAN MODE), 0)) AS score '
            . 'FROM book_titles t LEFT JOIN book_copies copy_location ON copy_location.title_id = t.id '
            . "WHERE EXISTS (SELECT 1 FROM book_copies available_copy WHERE available_copy.title_id = t.id "
            . "AND available_copy.status = 'Available' AND available_copy.deleted_at IS NULL) "
            . 'AND NOT EXISTS (SELECT 1 FROM borrowing_transactions active_transaction '
            . 'JOIN borrowing_items active_item ON active_item.transaction_id = active_transaction.id '
            . 'JOIN book_copies borrowed_title_copy ON borrowed_title_copy.id = active_item.copy_id '
            . "WHERE active_transaction.user_id = :user_id AND active_transaction.return_date IS NULL "
            . "AND active_item.return_date IS NULL AND active_transaction.approval_status IN ('pending', 'approved') "
            . 'AND borrowed_title_copy.title_id = t.id) '
            . 'GROUP BY t.id ORDER BY score DESC, t.created_at DESC, t.id DESC LIMIT :limit';
    }

    private function fallbackSql(): string
    {
        return 'SELECT t.id, t.id AS title_id, NULL AS barcode, t.isbn, t.title, t.author, t.publisher, '
            . 't.category_name, t.cover_file, t.description, t.quantity, '
            . "(SELECT COUNT(*) FROM book_copies available_copy WHERE available_copy.title_id = t.id "
            . "AND available_copy.status = 'Available' AND available_copy.deleted_at IS NULL) AS available_quantity, "
            . '(SELECT COUNT(*) FROM book_copies borrowed_copy WHERE borrowed_copy.title_id = t.id '
            . "AND borrowed_copy.status = 'Borrowed' AND borrowed_copy.deleted_at IS NULL) AS borrowed_quantity, "
            . 'MIN(copy_location.floor_no) AS floor_no, MIN(copy_location.section_name) AS section_name, '
            . 'MIN(copy_location.shelf_no) AS shelf_no, MIN(copy_location.row_no) AS row_no '
            . 'FROM book_titles t LEFT JOIN book_copies copy_location ON copy_location.title_id = t.id '
            . "WHERE EXISTS (SELECT 1 FROM book_copies available_copy WHERE available_copy.title_id = t.id "
            . "AND available_copy.status = 'Available' AND available_copy.deleted_at IS NULL) "
            . 'AND NOT EXISTS (SELECT 1 FROM borrowing_transactions active_transaction '
            . 'JOIN borrowing_items active_item ON active_item.transaction_id = active_transaction.id '
            . 'JOIN book_copies borrowed_title_copy ON borrowed_title_copy.id = active_item.copy_id '
            . "WHERE active_transaction.user_id = :user_id AND active_transaction.return_date IS NULL "
            . "AND active_item.return_date IS NULL AND active_transaction.approval_status IN ('pending', 'approved') "
            . 'AND borrowed_title_copy.title_id = t.id) '
            . 'GROUP BY t.id ORDER BY t.created_at DESC, t.id DESC LIMIT :limit';
    }

    /** @param array<string, int|string> $parameters */
    private function bindParameters(\PDOStatement $statement, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
}
