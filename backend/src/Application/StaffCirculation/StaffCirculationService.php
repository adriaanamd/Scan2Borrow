<?php

declare(strict_types=1);

namespace App\Application\StaffCirculation;

use App\Application\Services\ClockInterface;
use App\Domain\Auth\Role;
use App\Domain\Borrowing\BorrowingPolicy;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

final class StaffCirculationService
{
    public function __construct(
        private readonly StaffCirculationRepositoryInterface $repository,
        private readonly BorrowingPolicy $policy,
        private readonly ClockInterface $clock,
        private readonly float $finePerDay = 20.0,
    ) {
    }

    /** @return array<string, mixed> */
    public function lookup(string $barcode): array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return ['ok' => false, 'message' => 'Please scan or enter a student ID barcode.'];
        }

        $result = $this->repository->findBorrowerByBarcode($barcode);
        if ($result === null) {
            return ['ok' => false, 'message' => 'Student account not found for that ID.'];
        }

        return $result;
    }

    public function borrow(int $staffId, StaffCirculationBorrowRequest $request): StaffCirculationResult
    {
        if (!$this->repository->staffCanCirculate($staffId)) {
            return StaffCirculationResult::failure('Only active librarians or administrators can process circulation transactions.');
        }

        $lookup = $this->lookup($request->borrowerBarcode);
        if (($lookup['ok'] ?? false) !== true) {
            return StaffCirculationResult::failure($this->message($lookup));
        }

        $borrower = is_array($lookup['borrower'] ?? null) ? $lookup['borrower'] : [];
        $borrowerId = $this->positiveInt($borrower['id'] ?? null);
        $role = strtolower($this->string($borrower['role'] ?? null));
        if ($borrowerId < 1 || !in_array($role, [Role::STUDENT->value, Role::TEACHER->value], true)) {
            return StaffCirculationResult::failure('Only student or teacher accounts can borrow books.');
        }
        if ($this->string($borrower['status'] ?? 'active') !== '' && strtolower($this->string($borrower['status'])) !== 'active') {
            return StaffCirculationResult::failure('This account is not currently allowed to borrow books.');
        }
        if ($this->string($borrower['borrowing_status'] ?? 'active') !== '' && strtolower($this->string($borrower['borrowing_status'])) !== 'active') {
            return StaffCirculationResult::failure('This account is not currently allowed to borrow books.');
        }

        $bookBarcodes = $this->normalizedBarcodes($request->bookBarcodes);
        if ($bookBarcodes === []) {
            return StaffCirculationResult::failure('Scan or enter at least one book copy barcode.');
        }
        if (count($bookBarcodes) !== count($request->bookBarcodes)) {
            return StaffCirculationResult::failure('Please scan each book copy only once.');
        }

        $activeLoans = 0;
        if (is_numeric($borrower['borrow_limit_count'] ?? null)) {
            $activeLoans = (int) $borrower['borrow_limit_count'];
        } elseif (is_numeric($borrower['active_loans'] ?? null)) {
            $activeLoans = (int) $borrower['active_loans'];
        } elseif (is_array($lookup['active_loans'] ?? null)) {
            $activeLoans = count($lookup['active_loans']);
        }
        $remaining = $this->policy->maxBooks() - $activeLoans;
        if (count($bookBarcodes) > $remaining) {
            return StaffCirculationResult::failure(
                $remaining > 0
                    ? 'This borrower can borrow only ' . $remaining . ' more book(s).'
                    : 'This borrower has reached the ' . $this->policy->maxBooks() . '-book limit.',
            );
        }

        $dueDate = $this->dueDate($role, $request->requestedDueDate);
        if ($dueDate instanceof StaffCirculationResult) {
            return $dueDate;
        }

        try {
            $transactionCode = $this->transactionCode();
            $data = $this->repository->createDirectBorrow($borrowerId, $staffId, $bookBarcodes, $dueDate, $transactionCode);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return StaffCirculationResult::failure($exception->getMessage());
        }

        $data['transaction_code'] = $data['transaction_code'] ?? $transactionCode;
        $data['due_date'] = $data['due_date'] ?? $dueDate->format('Y-m-d');

        return StaffCirculationResult::success('Borrowing transaction approved.', $data);
    }

    public function returnBooks(int $staffId, StaffCirculationReturnRequest $request): StaffCirculationResult
    {
        if (!$this->repository->staffCanCirculate($staffId)) {
            return StaffCirculationResult::failure('Only active librarians or administrators can process circulation transactions.');
        }

        if ($request->borrowerId < 1) {
            return StaffCirculationResult::failure('Please scan a valid student ID before processing a return.');
        }

        $loanIds = $this->normalizedLoanIds($request->loanIds);
        $copyBarcodes = $this->normalizedBarcodes($request->copyBarcodes);
        if ($loanIds === [] && $copyBarcodes === []) {
            return StaffCirculationResult::failure('Select at least one active book to return.');
        }
        if (count($loanIds) !== count($request->loanIds)) {
            return StaffCirculationResult::failure('Please select each loan only once.');
        }
        if (count($copyBarcodes) !== count($request->copyBarcodes)) {
            return StaffCirculationResult::failure('Please scan each returned copy only once.');
        }

        try {
            $data = $this->repository->processDirectReturns(
                $request->borrowerId,
                $staffId,
                $loanIds,
                $copyBarcodes,
                $this->clock->now(),
                $this->finePerDay,
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return StaffCirculationResult::failure($exception->getMessage());
        }

        return StaffCirculationResult::success('Return processed successfully.', $data);
    }

    private function dueDate(string $role, ?string $requestedDueDate): DateTimeImmutable|StaffCirculationResult
    {
        $today = $this->clock->now()->setTime(0, 0);
        if ($role !== Role::TEACHER->value || trim((string) $requestedDueDate) === '') {
            return $today->modify('+' . $this->policy->loanDays() . ' days');
        }

        $value = trim((string) $requestedDueDate);
        $requested = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($requested === false || $requested->format('Y-m-d') !== $value || $requested < $today) {
            return StaffCirculationResult::failure('Preferred return date cannot be in the past.');
        }

        $days = (int) $today->diff($requested)->format('%r%a');
        if ($days > $this->policy->teacherMaxDays()) {
            return StaffCirculationResult::failure(
                'Preferred return date cannot exceed ' . $this->policy->teacherMaxDays() . ' days.',
            );
        }

        return $requested;
    }

    private function transactionCode(): string
    {
        return 'S2B-' . $this->clock->now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function normalizedBarcodes(array $values): array
    {
        $normalized = [];
        foreach ($values as $value) {
            $value = trim($value);
            if ($value === '' || in_array($value, $normalized, true)) {
                continue;
            }
            $normalized[] = $value;
        }

        return $normalized;
    }

    /**
     * @param list<int|string> $values
     * @return list<int>
     */
    private function normalizedLoanIds(array $values): array
    {
        $normalized = [];
        foreach ($values as $value) {
            $id = $this->positiveInt($value);
            if ($id > 0) {
                $normalized[] = $id;
            }
        }

        return $normalized;
    }

    /** @param array<string, mixed> $value */
    private function message(array $value): string
    {
        $message = $this->string($value['message'] ?? null);

        return $message !== '' ? $message : 'Unable to load the borrower account.';
    }

    private function positiveInt(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
