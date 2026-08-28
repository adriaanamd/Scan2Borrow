<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Guest\VisitorAccount;

interface GuestIdentityRepositoryInterface
{
    public function findByGovernmentId(string $barcode): ?VisitorAccount;
}
