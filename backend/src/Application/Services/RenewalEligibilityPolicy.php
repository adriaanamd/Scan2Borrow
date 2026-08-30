<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Persistence\RenewalEligibilityRepositoryInterface;
use App\Infrastructure\Persistence\RenewalRepositoryInterface;

final readonly class RenewalEligibilityPolicy
{
    public function __construct(
        private RenewalEligibilityRepositoryInterface $source,
        private RenewalRepositoryInterface $renewals,
        private int $standardDays,
    ) {
    }

    public function check(int $userId, int $loanId): RenewalEligibilityResult
    {
        $loan = $this->source->loanForRenewal($loanId, $userId);
        if ($loan === null) return RenewalEligibilityResult::denied('This loan is no longer active.');
        if ($this->source->activeHoldCountForTitle($loan->titleId) > 0) return RenewalEligibilityResult::denied('This title has an active hold and cannot be renewed.');
        if (!$this->source->accountInGoodStanding($userId)) return RenewalEligibilityResult::denied('Your account must be in good standing before requesting a renewal.');
        if ($this->renewals->hasApprovedForLoan($loanId)) return RenewalEligibilityResult::denied('This loan has already used its renewal limit.');
        if ($this->renewals->hasPendingForLoan($loanId, $userId)) return RenewalEligibilityResult::denied('A renewal request for this loan is already awaiting approval.');

        return RenewalEligibilityResult::allowed($loan, $loan->dueDate->modify('+' . max(1, $this->standardDays) . ' days'));
    }
}
