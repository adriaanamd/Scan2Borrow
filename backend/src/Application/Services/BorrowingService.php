<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\BorrowRequest;
use App\Domain\Auth\Role;
use App\Domain\Borrowing\BorrowingPolicy;
use App\Domain\Borrowing\BorrowingResult;
use App\Domain\Book\BookStatus;
use App\Infrastructure\Persistence\BorrowingRepositoryInterface;
use DateTimeImmutable;

final class BorrowingService
{
    public function __construct(
        private readonly BorrowingRepositoryInterface $repository,
        private readonly BorrowingPolicy $policy,
        private readonly ClockInterface $clock,
    ) {
    }

    public function borrow(BorrowRequest $request): BorrowingResult
    {
        $barcode = trim($request->bookBarcode);
        if ($barcode === '') {
            return BorrowingResult::failure('Please scan or enter a book barcode.');
        }

        $book = $this->repository->findBook($barcode);
        if ($book === null) {
            return BorrowingResult::failure('Book not found. Please check the barcode.');
        }

        $status = is_string($book['status'] ?? null) ? BookStatus::tryFrom($book['status']) : null;
        if ($status !== BookStatus::AVAILABLE) {
            $title = is_string($book['title'] ?? null) ? $book['title'] : '';
            $displayStatus = $status === null ? 'unavailable' : $status->value;

            return BorrowingResult::failure('Sorry, "' . $title . '" is currently ' . strtolower($displayStatus) . '.');
        }

        if ($this->repository->activeApprovedCount($request->userId) >= $this->policy->maxBooks()) {
            return BorrowingResult::failure(
                'You already have the maximum of ' . $this->policy->maxBooks() . ' borrowed books.',
            );
        }

        $dueDate = $this->dueDate($request);
        if ($dueDate instanceof BorrowingResult) {
            return $dueDate;
        }

        $transactionCode = $this->transactionCode();
        $statusValue = $this->policy->requiresApproval() ? 'Pending' : 'Borrowed';
        $approvalValue = $this->policy->requiresApproval() ? 'pending' : 'approved';
        $rawBookId = $book['id'] ?? 0;
        $bookId = is_int($rawBookId)
            ? $rawBookId
            : (is_string($rawBookId) && ctype_digit($rawBookId) ? (int) $rawBookId : 0);
        $this->repository->createLoan(
            $request->userId,
            $bookId,
            $transactionCode,
            $dueDate,
            $statusValue,
            $approvalValue,
        );

        $title = is_string($book['title'] ?? null) ? $book['title'] : '';
        $message = $this->policy->requiresApproval()
            ? 'Request submitted for "' . $title . '". Awaiting staff approval.'
            : 'You borrowed "' . $title . '". Due ' . $dueDate->format('M d, Y') . '.';

        return BorrowingResult::success($message, $transactionCode);
    }

    private function dueDate(BorrowRequest $request): DateTimeImmutable|BorrowingResult
    {
        $today = $this->clock->now()->setTime(0, 0);
        if ($request->role !== Role::TEACHER || $request->requestedDueDate === null || $request->requestedDueDate === '') {
            return $today->modify('+' . $this->policy->loanDays() . ' days');
        }

        $requested = DateTimeImmutable::createFromFormat('!Y-m-d', $request->requestedDueDate);
        if ($requested === false || $requested->format('Y-m-d') !== $request->requestedDueDate || $requested < $today) {
            return BorrowingResult::failure('Preferred return date cannot be in the past.');
        }

        $days = (int) $today->diff($requested)->format('%r%a');
        if ($days > $this->policy->teacherMaxDays()) {
            return BorrowingResult::failure(
                'Preferred return date cannot exceed ' . $this->policy->teacherMaxDays() . ' days.',
            );
        }

        return $requested;
    }

    private function transactionCode(): string
    {
        return 'S2B-' . $this->clock->now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
