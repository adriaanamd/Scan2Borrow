<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class ApiDocumentationMarkupTest extends TestCase
{
    public function testAdminNavigationLinksToTheProtectedApiDocsPage(): void
    {
        $source = $this->read('frontend/pages/admin-staff.html');

        self::assertStringContainsString('href="/scan2borrow/admin/api-docs"', $source);
        self::assertStringContainsString('API Docs', $source);
    }

    public function testApiDocsPageContainsSwaggerStyleHooks(): void
    {
        $source = $this->read('frontend/pages/admin-api-docs.html');

        foreach (['data-page="admin-api-docs"', 'api-docs-tags', 'api-docs-operations', 'OpenAPI 3.0.3', 'api-docs.js'] as $marker) {
            self::assertStringContainsString($marker, $source, $marker . ' is missing.');
        }
    }

    public function testApiDocsFrontendFetchesOnlyTheAdminDocumentationApi(): void
    {
        $source = $this->read('frontend/assets/js/pages/api-docs.js');

        self::assertStringContainsString('/scan2borrow/api/admin/api-docs', $source);
        self::assertStringContainsString('details', $source);
        self::assertStringContainsString('api-docs-method', $source);
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
