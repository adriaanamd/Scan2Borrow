<?php

declare(strict_types=1);

namespace App\Http\Responses;

final readonly class HtmlResponse implements ResponseInterface
{
    public function __construct(
        private int $status,
        private string $html,
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
        return ['Content-Type' => 'text/html; charset=utf-8'];
    }

    public function toString(): string
    {
        return $this->html;
    }
}
