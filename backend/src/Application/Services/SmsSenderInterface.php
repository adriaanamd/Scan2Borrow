<?php

declare(strict_types=1);

namespace App\Application\Services;

interface SmsSenderInterface
{
    public function send(string $phoneNumber, string $message): void;
}
