<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class FrontendCsrfContractTest extends TestCase
{
    public function testSharedApiClientSubmitsTheGatewayToken(): void
    {
        $source = $this->read('frontend/app/core/api/api-client.js');

        self::assertStringContainsString('meta[name="csrf"]', $this->read('frontend/app/bootstrap/page-context.js'));
        self::assertStringContainsString("body.append('csrf'", $source);
        self::assertStringContainsString('this.csrf', $source);
    }

    /** @dataProvider directPostModuleProvider */
    public function testDirectPostFeatureModulesPreserveTheGatewayToken(string $relativePath): void
    {
        $source = $this->read($relativePath);

        self::assertStringContainsString('meta[name="csrf"]', $source);
        self::assertStringContainsString('body.append("csrf"', $source);
    }

    /** @return list<array{string}> */
    public static function directPostModuleProvider(): array
    {
        return [
            ['frontend/features/guest/pages/return/guest-return.page.js'],
            ['frontend/features/guest/pages/borrow-request/guest-borrow-request.page.js'],
            ['frontend/features/guest/pages/profile/guest-profile.page.js'],
            ['frontend/features/student/pages/search/student-search.page.js'],
            ['frontend/features/student/pages/dashboard/student-dashboard.page.js'],
        ];
    }

    private function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
