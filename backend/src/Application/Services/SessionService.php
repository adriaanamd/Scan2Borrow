<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Auth\Principal;
use App\Domain\Auth\SessionIdentity;
use App\Infrastructure\Session\SessionStoreInterface;

final class SessionService
{
    private const IDENTITY_KEY = 'scan2borrow.identity';

    public function __construct(
        private readonly SessionStoreInterface $store,
    ) {
    }

    public function start(): void
    {
        $this->store->start();
    }

    public function current(): ?SessionIdentity
    {
        $this->start();
        $identity = $this->store->get(self::IDENTITY_KEY);

        return $identity instanceof SessionIdentity ? $identity : null;
    }

    public function login(Principal $principal): void
    {
        $this->start();
        $this->store->regenerate();
        $this->store->set(
            self::IDENTITY_KEY,
            new SessionIdentity($principal->id(), $principal->role(), $this->store->id()),
        );
    }

    public function logout(): void
    {
        $this->start();
        $this->store->remove(self::IDENTITY_KEY);
        $this->store->regenerate();
    }
}
