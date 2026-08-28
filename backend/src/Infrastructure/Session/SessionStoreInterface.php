<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

interface SessionStoreInterface
{
    public function start(): void;

    public function regenerate(): void;

    public function id(): string;

    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;
}
