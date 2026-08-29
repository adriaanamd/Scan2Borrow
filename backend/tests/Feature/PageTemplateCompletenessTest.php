<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tests\Support\FrontendPagePaths;

final class PageTemplateCompletenessTest extends TestCase
{
    public function testEveryCleanRouteTemplateExistsAsStaticHtml(): void
    {
        foreach (FrontendPagePaths::all() as $pageName => $relativePath) {
            $path = FrontendPagePaths::path($pageName);
            self::assertFileExists($path, $relativePath . ' is missing from the feature frontend.');
            $source = (string) file_get_contents($path);
            self::assertStringNotContainsString('<?php', $source, $relativePath . ' still contains PHP.');
            self::assertStringContainsString('<html', strtolower($source), $relativePath . ' is not a complete document.');
        }
    }
}
