<?php

declare(strict_types=1);

namespace App\Http\Responses;

final readonly class RedirectResponse implements ResponseInterface
{
    public function __construct(
        private int $status,
        private string $location,
    ) {
    }

    public function statusCode(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return ['Location' => $this->location];
    }

    public function toString(): string
    {
        return '';
    }
}
