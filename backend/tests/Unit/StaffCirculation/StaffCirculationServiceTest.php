<?php

declare(strict_types=1);

namespace Tests\Unit\StaffCirculation;

use App\Application\Services\ClockInterface;
use App\Application\StaffCirculation\StaffCirculationBorrowRequest;
use App\Application\StaffCirculation\StaffCirculationRepositoryInterface;
use App\Application\StaffCirculation\StaffCirculationReturnRequest;
use App\Application\StaffCirculation\StaffCirculationService;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StaffCirculationServiceTest extends TestCase
{
    public function testLookupTrimsBarcodeAndReturnsBorrowerAndActiveLoans(): void
    {
        $repository = new FakeStaffCirculationRepository();
        $service = $this->service($repository);

        $result = $service->lookup('  STU-001  ');

        self::assertIsArray($result['borrower'] ?? null);
        self::assertSame('Ada Lovelace', $result['borrower']['name'] ?? null);
        self::assertSame('STU-001', $repository->lookedUpBarcode);
        self::assertIsArray($result['active_loans'] ?? null);
        self::assertCount(1, $result['active_loans']);
    }

    public function testLookupRejectsMissingOrUnknownBorrower(): void
    {
        $repository = new FakeStaffCirculationRepository();
        $service = $this->service($repository);

        self::assertSame('Please scan or enter a student ID barcode.', $service->lookup('')['message']);

        $repository->borrower = null;
        $result = $service->lookup('UNKNOWN');
        self::assertSame('Student account not found for that ID.', $result['message']);
    }

    public function testBorrowRequiresActiveBorrowingAccount(): void
    {
        $repository = new FakeStaffCirculationRepository();
        $repository->borrower['borrowing_status'] = 'inactive';
        $service = $this->service($repository);

        $result = $service->borrow(19, new StaffCirculationBorrowRequest('STU-001', ['COPY-001']));

        self::assertFalse($result->successful());
        self::assertSame('This account is not currently allowed to borrow books.', $result->message());
        self::assertNull($repository->borrowed);
    }

    public function testBorrowRejectsDuplicateCopiesAndCapacityOverflow(): void
    {
        $repository = new FakeStaffCirculationRepository();
        $service = $this->service($repository);

        $duplicate = $service->borrow(19, new StaffCirculationBorrowRequest('STU-001', ['COPY-001', ' COPY-001 ']));
        self::assertSame('Please scan each book copy only once.', $duplicate->message());

        $repository->borrower['active_loans'] = 3;
        $capacity = $service->borrow(19, new StaffCirculationBorrowRequest('STU-001', ['COPY-002']));
        self::assertSame('This borrower has reached the 3-book limit.', $capacity->message());
    }

    public function testBorrowValidatesTeacherDueDateAndReturnsApprovedTransaction(): void
    {
        $repository = new FakeStaffCirculationRepository();
        $repository->borrower['role'] = 'teacher';
        $service = $this->service($repository);

        $result = $service->borrow(19, new StaffCirculationBorrowRequest(
            'STU-001',
            ['COPY-001', 'COPY-002'],
            '2026-09-10',
        ));

        self::assertTrue($result->successful());
        $transactionCode = $result->data()['transaction_code'] ?? null;
        self::assertIsString($transactionCode);
        self::assertMatchesRegularExpression('/^S2B-20260828-[A-F0-9]{6}$/', $transactionCode);
        self::assertIsArray($repository->borrowed);
        self::assertSame('2026-09-10', $repository->borrowed['due_date'] ?? null);
        self::assertSame(19, $repository->borrowed['staff_id'] ?? null);
    }

    public function testBorrowReturnsRepositoryValidationErrors(): void
    {
        $repository = new FakeStaffCirculationRepository();
        $repository->borrowFailure = new RuntimeException('Book copy COPY-001 is not available.');
        $service = $this->service($repository);

        $result = $service->borrow(19, new StaffCirculationBorrowRequest('STU-001', ['COPY-001']));

        self::assertFalse($result->successful());
        self::assertSame('Book copy COPY-001 is not available.', $result->message());
    }

    public function testReturnRequiresSelectionAndRejectsBorrowerMismatch(): void
    {
        $repository = new FakeStaffCirculationRepository();
        $service = $this->service($repository);

        $empty = $service->returnBooks(19, new StaffCirculationReturnRequest(7));
        self::assertSame('Select at least one active book to return.', $empty->message());

        $repository->returnFailure = new InvalidArgumentException('The selected book does not belong to this borrower.');
        $mismatch = $service->returnBooks(19, new StaffCirculationReturnRequest(7, [55], ['COPY-999']));
        self::assertFalse($mismatch->successful());
        self::assertSame('The selected book does not belong to this borrower.', $mismatch->message());
    }

    public function testReturnProcessesSelectedLoansAndReportsFine(): void
    {
        $repository = new FakeStaffCirculationRepository();
        $service = $this->service($repository);

        $result = $service->returnBooks(19, new StaffCirculationReturnRequest(7, [55], ['COPY-001']));

        self::assertTrue($result->successful());
        self::assertSame(20.0, $result->data()['total_fine']);
        self::assertIsArray($repository->returned);
        self::assertSame([55], $repository->returned['loan_ids'] ?? null);
        self::assertSame(['COPY-001'], $repository->returned['copy_barcodes'] ?? null);
    }

    private function service(FakeStaffCirculationRepository $repository): StaffCirculationService
    {
        return new StaffCirculationService(
            $repository,
            new \App\Domain\Borrowing\BorrowingPolicy(3, 7, 30, false),
            new StaffCirculationFixedClock(),
            20.0,
        );
    }
}

final class StaffCirculationFixedClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-28 10:00:00');
    }
}

final class FakeStaffCirculationRepository implements StaffCirculationRepositoryInterface
{
    /** @var array<string, mixed>|null */
    public ?array $borrower = [
        'id' => 7,
        'barcode' => 'STU-001',
        'firstname' => 'Ada',
        'lastname' => 'Lovelace',
        'name' => 'Ada Lovelace',
        'role' => 'student',
        'status' => 'active',
        'borrowing_status' => 'active',
        'active_loans' => 0,
    ];

    public string $lookedUpBarcode = '';

    /** @var array<string, mixed>|null */
    public ?array $borrowed = null;

    public ?RuntimeException $borrowFailure = null;

    /** @var array<string, mixed>|null */
    public ?array $returned = null;

    public ?InvalidArgumentException $returnFailure = null;

    /** @return array{ok: true, borrower: array<string, mixed>, active_loans: list<array<string, mixed>>}|null */
    public function findBorrowerByBarcode(string $barcode): ?array
    {
        $this->lookedUpBarcode = $barcode;
        if ($this->borrower === null) {
            return null;
        }

        return [
            'ok' => true,
            'borrower' => $this->borrower,
            'active_loans' => [[
                'id' => 55,
                'copy_id' => 11,
                'copy_barcode' => 'COPY-001',
                'title' => 'Clean Code',
                'due_date' => '2026-08-27',
            ]],
        ];
    }

    /** @param list<string> $bookBarcodes @return array<string, mixed> */
    public function createDirectBorrow(int $borrowerId, int $staffId, array $bookBarcodes, DateTimeImmutable $dueDate, string $transactionCode): array
    {
        if ($this->borrowFailure !== null) {
            throw $this->borrowFailure;
        }
        $this->borrowed = [
            'borrower_id' => $borrowerId,
            'staff_id' => $staffId,
            'book_barcodes' => $bookBarcodes,
            'due_date' => $dueDate->format('Y-m-d'),
            'transaction_code' => $transactionCode,
        ];

        return [
            'transaction_code' => $transactionCode,
            'borrowed' => count($bookBarcodes),
            'due_date' => $dueDate->format('Y-m-d'),
        ];
    }

    /**
     * @param list<int> $loanIds
     * @param list<string> $copyBarcodes
     * @return array<string, mixed>
     */
    public function processDirectReturns(int $borrowerId, int $staffId, array $loanIds, array $copyBarcodes, DateTimeImmutable $now, float $finePerDay): array
    {
        if ($this->returnFailure !== null) {
            throw $this->returnFailure;
        }
        $this->returned = [
            'borrower_id' => $borrowerId,
            'staff_id' => $staffId,
            'loan_ids' => $loanIds,
            'copy_barcodes' => $copyBarcodes,
            'returned' => count($loanIds),
            'total_fine' => 20.0,
        ];

        return $this->returned;
    }
}
