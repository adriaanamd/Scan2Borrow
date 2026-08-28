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
            '.ui-icon',
            '@media (max-width: 768px)',
        ] as $selector) {
            self::assertStringContainsString($selector, $styles, $selector . ' is missing from the application shell.');
        }

        self::assertStringNotContainsString('backdrop-filter', $styles, 'The shared shell should use a deliberate flat surface, not a glass effect.');
        self::assertStringNotContainsString('var(--grad-', $styles, 'The Swiss institutional system should not depend on gradients.');
    }

    public function testEveryApplicationPageLoadsTheSharedSvgIconSystem(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages';
        $pages = glob($root . DIRECTORY_SEPARATOR . '*.html');
        self::assertIsArray($pages);

        foreach ($pages as $page) {
            $source = file_get_contents($page);
            self::assertIsString($source);
            self::assertStringContainsString(
                'frontend/assets/js/core/icons.js',
                $source,
                basename($page) . ' must load the shared SVG icon system.',
            );
        }

        $icons = $this->read('frontend/assets/js/core/icons.js');
        foreach (['dashboard:', 'books:', 'users:', 'search:', 'settings:', 'logout:'] as $icon) {
            self::assertStringContainsString($icon, $icons, $icon . ' is missing from the shared icon system.');
        }
    }

    public function testRegistrationPresentsRoleSelectionInsideTheRegistrationSurface(): void
    {
        $registration = $this->read('frontend/pages/register.html');

        self::assertStringContainsString('registration-role-picker', $registration);
        self::assertStringContainsString('id="chooseStudent"', $registration);
        self::assertStringContainsString('id="chooseTeacher"', $registration);
        self::assertStringContainsString('id="chooseGuest"', $registration);
        self::assertStringContainsString('id="role_select"', $registration);
        self::assertStringNotContainsString('id="roleModal"', $registration);
    }

    public function testRegistrationSeparatesDetailsAndCameraIntoDistinctSteps(): void
    {
        $registration = $this->read('frontend/pages/register.html');
        $controller = $this->read('frontend/assets/js/pages/registration.js');

        foreach (['registration-progress', 'registration-details-step', 'registration-photo-step', 'registration-continue', 'registration-back'] as $marker) {
            self::assertStringContainsString($marker, $registration, $marker . ' is missing from registration flow.');
        }

        self::assertStringContainsString('showStep', $controller);
        self::assertStringContainsString('showStep("photo")', $controller);
    }

    public function testAuthScreensUseTheExistingBrandAssetsAndSplitLayout(): void
    {
        foreach ([
            'frontend/pages/login.html',
            'frontend/pages/register.html',
            'frontend/pages/staff-login.html',
            'frontend/pages/guest-registration.html',
            'frontend/pages/verify-otp.html',
            'frontend/pages/guest-verify-otp.html',
            'frontend/pages/guest-profile-verify-otp.html',
        ] as $relativePath) {
            $source = $this->read($relativePath);
            self::assertStringContainsString('auth-split', $source, $relativePath . ' must use the split auth layout.');
            self::assertStringContainsString('/scan2borrow/public/logo.png', $source, $relativePath . ' must use the existing BCC logo.');
            self::assertStringContainsString('/scan2borrow/public/favicon.png', $source, $relativePath . ' must use the existing favicon.');
        }

        $styles = $this->read('frontend/assets/css/style.css');
        self::assertStringContainsString(
            ".auth-brand-panel {\n    align-items: center;",
            $styles,
            'The auth brand must be centered within its split column.',
        );
        self::assertStringContainsString(
            ".auth-brand-panel {\n    align-items: center;\n    background: var(--navy);",
            $styles,
            'The auth brand panel must center its content without changing its surface.',
        );
        self::assertStringContainsString(
            ".auth-brand-panel {\n    align-items: center;\n    background: var(--navy);\n    border-right: 8px solid var(--accent);\n    color: #fff;\n    display: flex;\n    flex-direction: column;\n    justify-content: center;\n    min-width: 0;\n    padding: clamp(30px, 5vw, 72px);\n    position: relative;\n    text-align: center;",
            $styles,
            'The auth brand text must share the centered brand alignment.',
        );
    }

    public function testEveryApplicationPageUsesTheExistingFavicon(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages';
        $pages = glob($root . DIRECTORY_SEPARATOR . '*.html');
        self::assertIsArray($pages);

        foreach ($pages as $page) {
            $source = file_get_contents($page);
            self::assertIsString($source);
            self::assertStringContainsString(
                'rel="icon"',
                $source,
                basename($page) . ' must declare a favicon.',
            );
            self::assertStringContainsString('/scan2borrow/public/favicon.png', $source, basename($page) . ' must use the existing favicon.');
        }
    }

    private function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
