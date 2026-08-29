<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use App\Http\Documentation\ApiEndpointCatalog;
use PHPUnit\Framework\TestCase;

final class ApiEndpointCatalogTest extends TestCase
{
    public function testCatalogDocumentsEveryApplicationApiRoute(): void
    {
        $endpoints = (new ApiEndpointCatalog())->all();
        $paths = array_map(static fn (array $endpoint): string => $endpoint['method'] . ' ' . $endpoint['path'], $endpoints);

        self::assertCount(51, $endpoints);
        self::assertCount(count(array_unique($paths)), $paths);
        self::assertContains('GET /api/admin/api-docs', $paths);
        self::assertContains('POST /api/student/borrow', $paths);
        self::assertContains('POST /api/guest/return', $paths);
        self::assertContains('POST /api/admin/staff-action', $paths);
        self::assertContains('GET /api/book-copies', $paths);
        self::assertContains('POST /api/book-copies', $paths);
    }

    public function testCatalogEntriesContainSwaggerOperationFields(): void
    {
        $endpoint = (new ApiEndpointCatalog())->all()[0];

        self::assertSame(['method', 'path', 'tag', 'summary', 'description', 'auth', 'parameters', 'response'], array_keys($endpoint));
        self::assertNotEmpty($endpoint['method']);
        self::assertNotEmpty($endpoint['path']);
        self::assertNotEmpty($endpoint['tag']);
        self::assertNotEmpty($endpoint['summary']);
        self::assertNotEmpty($endpoint['response']);
    }
}
