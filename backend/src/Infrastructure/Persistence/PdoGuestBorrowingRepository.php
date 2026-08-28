<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\GuestBorrowRequest;
use PDO;

final class PdoGuestBorrowingRepository implements GuestBorrowingRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function isBookAvailable(int $bookId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM books WHERE id = :id AND status = 'Available' AND deleted_at IS NULL LIMIT 1"
        );
        $statement->execute(['id' => $bookId]);

        return $statement->fetchColumn() !== false;
    }

    public function activeCount(int $visitorId): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM visitor_borrowing WHERE visitor_id = :visitor_id AND return_date IS NULL AND request_status IN ('Pending', 'Ready for Release', 'Released', 'Return Verification Pending')"
        );
        $statement->execute(['visitor_id' => $visitorId]);

        return (int) $statement->fetchColumn();
    }

    public function createPending(GuestBorrowRequest $request, int $visitorId): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO visitor_borrowing (visitor_id, book_id, borrow_date, due_date, request_status, verification_photo, requested_at) VALUES (:visitor_id, :book_id, :borrow_date, :due_date, 'Pending', :verification_photo, CURRENT_TIMESTAMP)"
        );
        $borrowDate = new \DateTimeImmutable('today');
        $statement->execute([
            'visitor_id' => $visitorId,
            'book_id' => $request->bookId(),
            'borrow_date' => $borrowDate->format('Y-m-d'),
            'due_date' => $borrowDate->modify('+7 days')->format('Y-m-d'),
            'verification_photo' => $request->verificationPhoto(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findReleasedByBarcode(int $visitorId, string $bookBarcode): ?int
    {
        $statement = $this->pdo->prepare(
            "SELECT vb.id FROM visitor_borrowing vb JOIN books b ON b.id = vb.book_id WHERE vb.visitor_id = :visitor_id AND b.barcode = :barcode AND vb.return_date IS NULL AND vb.request_status = 'Released' ORDER BY vb.id DESC LIMIT 1"
        );
        $statement->execute(['visitor_id' => $visitorId, 'barcode' => trim($bookBarcode)]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function markReturnVerification(int $borrowingId, string $photo): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE visitor_borrowing SET return_verification_photo = :photo, request_status = 'Return Verification Pending', return_requested_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        $statement->execute(['photo' => $photo, 'id' => $borrowingId]);
    }
}
