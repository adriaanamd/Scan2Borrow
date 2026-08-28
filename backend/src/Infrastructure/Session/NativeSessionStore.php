<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

final class NativeSessionStore implements SessionStoreInterface
{
    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function id(): string
    {
        $this->start();

        $id = session_id();

        return is_string($id) ? $id : '';
    }

    public function get(string $key): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }
}
