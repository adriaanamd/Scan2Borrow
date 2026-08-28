<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

use RuntimeException;

final class HttpException extends RuntimeException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        private readonly int $status,
        private readonly array $errors,
    ) {
        parent::__construct(implode(' ', $errors));
    }

    public function statusCode(): int
    {
        return $this->status;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
