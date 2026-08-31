<?php

declare(strict_types=1);

namespace Tests\Unit\Profile;

use App\Application\Services\LocalPhotoStorage;
use PHPUnit\Framework\TestCase;

final class LocalPhotoStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'scan2borrow-photo-' . bin2hex(random_bytes(6));
        mkdir($directory, 0750, true);
        $this->directory = $directory;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->directory);
    }

    public function testRejectsPhotosLargerThanFourMegabytes(): void
    {
        $data = 'data:image/jpeg;base64,' . base64_encode(str_repeat('x', 4 * 1024 * 1024 + 1));

        self::assertNull((new LocalPhotoStorage($this->directory))->store($data, 'profile-request-7'));
        self::assertSame([], glob($this->directory . DIRECTORY_SEPARATOR . '*'));
    }
}
