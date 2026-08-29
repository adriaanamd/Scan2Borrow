<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tests\Support\FrontendPagePaths;

final class ApiDocumentationMarkupTest extends TestCase
{
    public function testAdminNavigationLinksToTheProtectedApiDocsPage(): void
    {
        $source = file_get_contents(FrontendPagePaths::path('admin-staff'));
        self::assertIsString($source);
        $navbar = $this->read('frontend/assets/js/core/app-navbar.js');

        self::assertStringContainsString('data-app-navbar', $source);
        self::assertStringContainsString('/scan2borrow/admin/api-docs', $navbar);
        self::assertStringContainsString('API Docs', $navbar);
    }

    public function testApiDocsPageContainsSwaggerStyleHooks(): void
    {
        $source = file_get_contents(FrontendPagePaths::path('admin-api-docs'));
        self::assertIsString($source);

        foreach (['data-page="admin-api-docs"', 'api-docs-tags', 'api-docs-operations', 'OpenAPI 3.0.3', 'api-docs.page.js'] as $marker) {
            self::assertStringContainsString($marker, $source, $marker . ' is missing.');
        }
    }

    public function testApiDocsFrontendFetchesOnlyTheAdminDocumentationApi(): void
    {
        $source = $this->read('frontend/features/staff/pages/api-docs/api-docs.page.js');

        self::assertStringContainsString('/scan2borrow/api/admin/api-docs', $source);
        self::assertStringContainsString('details', $source);
        self::assertStringContainsString('api-docs-method', $source);
    }

    public function testCanonicalApiDocsPageUsesFeatureOwnedModule(): void
    {
        $source = $this->read('frontend/features/staff/pages/api-docs/api-docs.html');
        foreach (['data-app-page="staff-api-docs"', 'frontend/features/staff/pages/api-docs/api-docs.page.js', 'api-docs-tags', 'api-docs-operations', 'OpenAPI 3.0.3'] as $marker) {
            self::assertStringContainsString($marker, $source, $marker . ' is missing from the canonical page.');
        }
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
