<?php

declare(strict_types=1);

namespace App\Application\Services;

use RuntimeException;

final class LocalPhotoStorage implements PhotoStorageInterface
{
    public function __construct(private readonly string $directory, private readonly string $publicPrefix = 'uploads')
    {
    }

    public function store(string $data, string $filenameSeed): ?string
    {
        if ($data === '') {
            return null;
        }

        if (preg_match('#^data:image/(jpeg|jpg|png);base64,(.+)$#s', $data, $matches) !== 1) {
            return null;
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false || $binary === '') {
            return null;
        }

        if (strlen($binary) > 4 * 1024 * 1024) {
            return null;
        }

        if (!is_dir($this->directory) && mkdir($this->directory, 0750, true) === false && !is_dir($this->directory)) {
            throw new RuntimeException('Photo storage directory could not be created.');
        }

        $safeSeedValue = preg_replace('/[^A-Za-z0-9_-]/', '_', $filenameSeed);
        $safeSeed = !is_string($safeSeedValue) || $safeSeedValue === '' ? 'photo' : $safeSeedValue;
        $filename = $safeSeed . '-' . bin2hex(random_bytes(8)) . '.jpg';
        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($path, $binary, LOCK_EX) === false) {
            throw new RuntimeException('Photo could not be stored.');
        }

        return rtrim($this->publicPrefix, '/') . '/' . $filename;
    }
}
