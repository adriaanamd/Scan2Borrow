<?php

declare(strict_types=1);

namespace App\Application\Services;

interface RenewalEligibilityInterface
{
    public function check(int $userId, int $loanId): RenewalEligibilityResult;
}
