<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\HoldActionRequest;
use App\Application\DTO\JoinHoldRequest;
use App\Application\DTO\ReservationResult;
use App\Infrastructure\Persistence\HoldRepositoryInterface;
use Throwable;

final readonly class ReservationService
{
    public function __construct(private HoldRepositoryInterface $repository)
    {
    }

    public function join(JoinHoldRequest $request): ReservationResult
    {
        if ($this->repository->findActiveForUserTitle($request->userId, $request->titleId) !== null) {
            return ReservationResult::failure('You are already in the reservation queue for this title.');
        }

        try {
            $record = $this->repository->join($request->userId, $request->titleId);
        } catch (Throwable) {
            return ReservationResult::failure('This title could not be reserved right now. Please try again.');
        }

        return ReservationResult::success('You joined the queue for "' . $record->title() . '".', $record);
    }

    /** @return list<\App\Domain\Reservation\HoldRecord> */
    public function list(int $userId): array
    {
        return $this->repository->listForUser($userId);
    }

    /** @return list<\App\Domain\Reservation\HoldRecord> */
    public function staffList(string $status): array
    {
        return $this->repository->listStaff($status);
    }

    public function cancel(HoldActionRequest $request): ReservationResult
    {
        if (!$this->repository->cancel($request->holdId, $request->userId)) {
            return ReservationResult::failure('This reservation is no longer available for cancellation.');
        }

        return ReservationResult::success('Reservation cancelled.');
    }

    public function claim(HoldActionRequest $request): ReservationResult
    {
        $record = $this->repository->claim($request->holdId, $request->userId);
        if ($record === null) {
            return ReservationResult::failure('This hold has expired or is no longer available to claim.');
        }

        return ReservationResult::success(
            'Your hold for "' . $record->title() . '" is claimed. Please visit the library desk to borrow it.',
            $record,
        );
    }

    public function fulfil(int $holdId, int $staffId): ReservationResult
    {
        return $this->repository->fulfil($holdId, $staffId)
            ? ReservationResult::success('Reservation marked as fulfilled.')
            : ReservationResult::failure('This reservation is not ready to be fulfilled.');
    }
}
