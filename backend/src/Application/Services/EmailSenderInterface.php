<?php

declare(strict_types=1);

namespace App\Application\Services;

interface EmailSenderInterface
{
    public function isConfigured(): bool;

    public function send(string $to, string $name, string $subject, string $html): bool;
}
