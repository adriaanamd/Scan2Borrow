<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class StudentSettingsMarkupTest extends TestCase
{
    public function testStudentSettingsKeepsAccountDetailsInStudentContext(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'student-settings.html';
        self::assertFileExists($path);
        $html = (string) file_get_contents($path);

        self::assertStringContainsString('<title>Settings | Scan2Borrow</title>', $html);
        self::assertStringContainsString('data-page="student-settings"', $html);
        self::assertStringContainsString('id="student-settings-form"', $html);
        self::assertStringContainsString('frontend/assets/js/pages/student-settings.js', $html);
        self::assertStringContainsString('/scan2borrow/student/settings', $html);
        self::assertStringNotContainsString('/scan2borrow/settings', $html);
        self::assertStringNotContainsString('/scan2borrow/guest/registration', $html);
    }

    public function testStudentSearchSettingsLinkStaysInStudentContext(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'student-search.html';
        self::assertFileExists($path);
        $html = (string) file_get_contents($path);

        self::assertStringContainsString('href="/scan2borrow/student/settings"', $html);
        self::assertStringNotContainsString('href="/scan2borrow/settings"', $html);
    }
}
