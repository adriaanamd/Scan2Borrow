<?php

declare(strict_types=1);

namespace App\Application\Services;

interface PhotoStorageInterface
{
    public function store(string $data, string $filenameSeed): ?string;
}
