<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Session\SessionStoreInterface;
use InvalidArgumentException;

final class CsrfService
{
    private const TOKEN_KEY = 'scan2borrow.csrf';

    public function __construct(
        private readonly SessionStoreInterface $store,
    ) {
    }

    public function token(): string
    {
        $this->store->start();
        $existing = $this->store->get(self::TOKEN_KEY);
        if (is_string($existing) && preg_match('/^[a-f0-9]{64}$/', $existing) === 1) {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        $this->store->set(self::TOKEN_KEY, $token);

        return $token;
    }

    public function assertValid(string $submitted): void
    {
        if (!hash_equals($this->token(), $submitted)) {
            throw new InvalidArgumentException('Invalid CSRF token.');
        }
    }
}
