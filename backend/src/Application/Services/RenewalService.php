<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\RenewalRequest;
use App\Application\DTO\RenewalResult;
use App\Infrastructure\Persistence\RenewalRepositoryInterface;

final readonly class RenewalService
{
    public function __construct(
        private RenewalEligibilityInterface $eligibility,
        private RenewalRepositoryInterface $repository,
    ) {
    }

    public function request(RenewalRequest $request): RenewalResult
    {
        $eligibility = $this->eligibility->check($request->userId, $request->loanId);
        if (!$eligibility->eligible() || $eligibility->loan() === null || $eligibility->requestedDueDate() === null) {
            return RenewalResult::failure($eligibility->message());
        }

        $record = $this->repository->create(
            $request->loanId,
            $request->userId,
            $eligibility->loan()->dueDate,
            $eligibility->requestedDueDate(),
            $request->reason,
        );

        return RenewalResult::success('Renewal request submitted for librarian approval.', $record);
    }

    /** @return list<\App\Domain\Renewal\RenewalRecord> */
    public function list(int $userId): array
    {
        return $this->repository->listForUser($userId);
    }
}
