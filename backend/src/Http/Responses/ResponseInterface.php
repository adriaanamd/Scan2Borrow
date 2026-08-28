<?php

declare(strict_types=1);

namespace App\Http\Responses;

interface ResponseInterface
{
    public function statusCode(): int;

    /**
     * @return array<string, string>
     */
    public function headers(): array;

    public function toString(): string;
}
