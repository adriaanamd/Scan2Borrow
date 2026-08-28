<?php

declare(strict_types=1);

namespace App\Bootstrap;

final readonly class Application
{
    public function __construct(
        public string $environment,
    ) {
    }
}
