<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\Auth\Principal;
use App\Http\Requests\ServerRequest;

final readonly class RequestContext
{
    public function __construct(
        private ServerRequest $request,
        private ?Principal $principal,
    ) {
    }

    public function request(): ServerRequest
    {
        return $this->request;
    }

    public function principal(): ?Principal
    {
        return $this->principal;
    }
}
