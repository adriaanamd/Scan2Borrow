<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\BorrowRequest;
use App\Application\DTO\BulkBorrowRequest;
use App\Domain\Auth\Role;
use App\Domain\Borrowing\BorrowingPolicy;
use App\Domain\Borrowing\BorrowingResult;
use App\Domain\Borrowing\BulkBorrowingResult;
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

    public function bulkBorrow(BulkBorrowRequest $request): BulkBorrowingResult
    {
        if ($request->items === []) {
            return BulkBorrowingResult::failure('Please add at least one book to your borrowing cart.');
        }

        $seenTitles = [];
        $totalRequested = 0;
        foreach ($request->items as $item) {
            if (!$item instanceof \App\Application\DTO\BulkBorrowItem || $item->titleId <= 0 || $item->quantity <= 0) {
                return BulkBorrowingResult::failure('Each cart item must have a positive quantity.');
            }
            if (in_array($item->titleId, $seenTitles, true)) {
                return BulkBorrowingResult::failure('A title can appear only once in the borrowing cart.');
            }
            if (count($item->barcodes) > $item->quantity) {
                return BulkBorrowingResult::failure('The scanned copies do not match the requested quantity.');
            }
            $seenTitles[] = $item->titleId;
            $totalRequested += $item->quantity;
        }

        $remaining = $this->policy->maxBooks() - $this->repository->activeApprovedCount($request->userId);
        if ($totalRequested > $remaining) {
            return BulkBorrowingResult::failure(
                'You can borrow only ' . max(0, $remaining) . ' more book(s) right now.',
            );
        }

        $dueDate = $this->dueDate(new BorrowRequest(
            $request->userId,
            $request->role,
            '',
            $request->requestedDueDate,
        ));
        if ($dueDate instanceof BorrowingResult) {
            return BulkBorrowingResult::failure($dueDate->message());
        }

        $transactionCode = $this->transactionCode();
        $status = $this->policy->requiresApproval() ? 'Pending' : 'Borrowed';
        $approvalStatus = $this->policy->requiresApproval() ? 'pending' : 'approved';
        $created = $this->repository->createBulkTransaction(
            $request,
            $dueDate,
            $transactionCode,
            $status,
            $approvalStatus,
        );

        $message = $this->policy->requiresApproval()
            ? $created['copy_count'] . ' book(s) requested. Awaiting staff approval.'
            : $created['copy_count'] . ' book(s) borrowed. Due ' . $dueDate->format('M d, Y') . '.';

        return BulkBorrowingResult::success(
            $message,
            $created['transaction_code'],
            $created['copy_count'],
            $created['title_count'],
        );
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
