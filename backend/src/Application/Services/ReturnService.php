<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Borrowing\LoanRecord;
use App\Infrastructure\Persistence\ReturnRepositoryInterface;

final class ReturnService
{
    public function __construct(
        private readonly ReturnRepositoryInterface $repository,
        private readonly ClockInterface $clock,
        private readonly float $finePerDay,
    ) {
    }

    public function return(int $userId, string $input): ReturnResult
    {
        $input = trim($input);
        if ($input === '') {
            return ReturnResult::failure('Please enter a book barcode or transaction code.');
        }

        $transactionLoans = $this->repository->activeByTransaction($userId, $input);
        if ($transactionLoans !== []) {
            return $this->completeTransaction($transactionLoans);
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

        $fine = $this->fine($loan);
        $this->repository->completeReturn($loan->id(), $bookId, $fine);
        $title = is_string($book['title'] ?? null) ? $book['title'] : '';
        $message = 'You returned "' . $title . '".';
        if ($fine > 0) {
            $message .= ' It was ' . $this->overdueDays($loan) . ' day(s) overdue. Fine: ₱' . number_format($fine, 2) . '.';
        }

        return ReturnResult::success($message);
    }

    /**
     * @param list<LoanRecord> $loans
     */
    private function completeTransaction(array $loans): ReturnResult
    {
        $totalFine = 0.0;
        foreach ($loans as $loan) {
            $fine = $this->fine($loan);
            $totalFine += $fine;
            $this->repository->completeReturn($loan->id(), $loan->bookId(), $fine);
        }

        $message = 'Successfully returned ' . count($loans) . ' book(s) using transaction code.';
        if ($totalFine > 0) {
            $message .= ' Total fine: ₱' . number_format($totalFine, 2) . '.';
        }

        return ReturnResult::success($message);
    }

    private function fine(LoanRecord $loan): float
    {
        return round($this->overdueDays($loan) * $this->finePerDay, 2);
    }

    private function overdueDays(LoanRecord $loan): int
    {
        $due = $loan->dueDate()->setTime(23, 59, 59);
        $delta = $this->clock->now()->getTimestamp() - $due->getTimestamp();

        return $delta > 0 ? (int) floor($delta / 86400) : 0;
    }

    private function bookId(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : 0);
    }
}
