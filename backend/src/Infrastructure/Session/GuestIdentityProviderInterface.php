<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Domain\Guest\VisitorAccount;

interface GuestIdentityProviderInterface
{
    public function current(): ?VisitorAccount;
}
