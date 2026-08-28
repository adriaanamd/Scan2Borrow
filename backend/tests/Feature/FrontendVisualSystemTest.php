<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class FrontendVisualSystemTest extends TestCase
{
    public function testSharedStylesUseTheBccInspiredLibraryPalette(): void
    {
        $styles = $this->read('frontend/assets/css/style.css');

        foreach ([
            '--primary: #075985;',
            '--primary-dark: #0b3b60;',
            '--navy: #102f52;',
            '--accent: #d4a72c;',
            '--app-bg: #f4f8fb;',
            '--border: #d4e0e8;',
        ] as $token) {
            self::assertStringContainsString($token, $styles, $token . ' is missing from the shared visual system.');
        }

        foreach (['#6366F1', '#4F46E5', '#8B5CF6', '#06B6D4', '#22D3EE'] as $legacyToken) {
            self::assertStringNotContainsString($legacyToken, $styles, $legacyToken . ' is still present in the shared theme.');
        }
    }

    public function testAllApplicationPagesKeepTheSharedShellStylesheet(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages';
        $pages = glob($root . DIRECTORY_SEPARATOR . '*.html');
        self::assertIsArray($pages);

        foreach ($pages as $page) {
            $source = file_get_contents($page);
            self::assertIsString($source);
            self::assertStringContainsString(
                'frontend/assets/css/style.css',
                $source,
                basename($page) . ' must keep the shared application stylesheet.',
            );
        }
    }

    public function testSharedStylesDefineACompleteHighContrastApplicationShell(): void
    {
        $styles = $this->read('frontend/assets/css/style.css');

        foreach ([
            '.sidebar-nav a.active::before',
            '.topbar-title::before',
            '.page-head',
            '.stat-card',
            '.table-card',
            '.hero-card h2',
            '.hero-card .text-muted',
            '.auth-wrap',
            '.modal-header',
            '@media (max-width: 768px)',
        ] as $selector) {
            self::assertStringContainsString($selector, $styles, $selector . ' is missing from the application shell.');
        }

        self::assertStringNotContainsString('backdrop-filter', $styles, 'The shared shell should use a deliberate flat surface, not a glass effect.');
        self::assertStringNotContainsString('var(--grad-', $styles, 'The Swiss institutional system should not depend on gradients.');
    }

    private function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
