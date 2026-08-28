<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\AuthenticationResult;

interface AuthenticationServiceInterface
{
    public function loginBorrower(string $barcode): AuthenticationResult;

    public function loginStaff(string $barcode, string $password): AuthenticationResult;
}
