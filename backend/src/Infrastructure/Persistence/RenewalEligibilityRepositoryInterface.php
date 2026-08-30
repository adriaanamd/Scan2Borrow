<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Renewal\RenewalLoanSnapshot;

interface RenewalEligibilityRepositoryInterface
{
    public function loanForRenewal(int $loanId, int $userId): ?RenewalLoanSnapshot;

    public function activeHoldCountForTitle(int $titleId): int;

    public function accountInGoodStanding(int $userId): bool;
}
