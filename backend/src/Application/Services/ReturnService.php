<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Persistence\ReturnRepositoryInterface;

final class ReturnService
{
    public function __construct(
        private readonly ReturnRepositoryInterface $repository,
    ) {
    }

    /**
     * Compatibility alias for callers that used the former direct-return API.
     * It now follows the safe request-for-review workflow.
     */
    public function return(int $userId, string $input): ReturnResult
    {
        return $this->request($userId, $input);
    }

    public function request(int $userId, string $input): ReturnResult
    {
        $input = trim($input);
        if ($input === '') {
            return ReturnResult::failure('Please enter a book barcode or transaction code.');
        }

        $transactionLoans = $this->repository->activeByTransaction($userId, $input);
        if ($transactionLoans !== []) {
            foreach ($transactionLoans as $loan) {
                if (!$this->repository->requestReturn($loan->id())) {
                    return ReturnResult::failure('This return is already awaiting librarian verification.');
                }
            }

            return ReturnResult::success('Return request submitted. Please hand the book to the librarian for verification.');
        }

        $book = $this->repository->findBookByBarcode($input);
        if ($book === null) {
            return ReturnResult::failure('No book or transaction found for: ' . $input);
        }

        $bookId = $this->bookId($book['id'] ?? null);
        $loan = $this->repository->activeByBook($userId, $bookId);
        if ($loan === null) {
            return ReturnResult::failure('You have no active borrowed for this book.');
        }

        if (!$this->repository->requestReturn($loan->id())) {
            return ReturnResult::failure('This return is already awaiting librarian verification.');
        }

        return ReturnResult::success('Return request submitted. Please hand the book to the librarian for verification.');
    }

    private function bookId(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : 0);
    }
}
