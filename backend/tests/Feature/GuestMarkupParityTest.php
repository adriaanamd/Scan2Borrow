<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tests\Support\FrontendPagePaths;

final class GuestMarkupParityTest extends TestCase
{
    /** @var array<string, list<string>> */
    private const PAGE_MARKERS = [
        'guest-registration' => ['Guest Registration', 'id="guest-reg-form"', 'id="photo_data"', 'id="btn-start"', 'name="id_barcode"', 'Send SMS OTP'],
        'guest-verify-otp' => ['Guest SMS Verification', 'name="otp"', 'maxlength="6"', 'Resend OTP'],
        'guest-dashboard' => ['Guest Dashboard', 'id="borrowModal"', 'id="returnModal"', 'guest_borrow_barcode', 'guest_return_barcode', 'Reading Activity', 'Security Log'],
        'guest-profile' => ['Settings', 'name="contact_no"', 'name="purpose_other"', 'Save Changes'],
        'guest-profile-verify-otp' => ['Verify New Mobile Number', 'name="otp"', 'Verify &amp; Save', 'Resend OTP'],
        'guest-browse' => ['Browse Books', 'name="q"', 'name="category"'],
        'guest-borrowed' => ['Borrowed Books', 'currently borrowed books'],
        'guest-history' => ['Borrowing History', 'name="status"', 'name="from"', 'name="to"'],
        'guest-borrow-request' => ['Borrow Request', 'id="captureGuideModal"', 'id="government_id_barcode"', 'id="verification_photo"', 'id="cam"', 'id="snap"', 'Submit Request'],
        'guest-return' => ['Return a Book', 'name="book_barcode"', 'id="return_photo"', 'Submit Return'],
        'guest-pass' => ['Registered Government ID', 'id="id-barcode"', 'Verified Government ID', 'window.print()'],
        'guest-receipt' => ['Borrowing Receipt', 'Scan2Borrow Library', 'window.print()', 'class="no-print'],
    ];

    public function testAllGuestPagesPreserveLegacyMarkupContracts(): void
    {
        foreach (self::PAGE_MARKERS as $pageName => $markers) {
            $path = FrontendPagePaths::path($pageName);
            self::assertFileExists($path, "Missing guest page {$pageName}");
            $html = file_get_contents($path);
            self::assertIsString($html);
            foreach ($markers as $marker) {
                self::assertStringContainsString($marker, $html, "Missing {$pageName} marker: {$marker}");
            }
        }
    }

    public function testGuestCameraAndPageControllersAreClassBased(): void
    {
        foreach ([
            'app/shared/components/camera-capture/camera-capture.component.js',
            'features/auth/pages/guest-registration/guest-registration.page.js',
            'features/guest/pages/borrow-request/guest-borrow-request.page.js',
            'features/guest/pages/return/guest-return.page.js',
        ] as $relativePath) {
            $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            self::assertFileExists($path, "Missing guest controller {$relativePath}");
            $script = file_get_contents($path);
            self::assertIsString($script);
            self::assertStringContainsString('class ', $script);
        }
    }

    public function testGuestRegistrationSeparatesDetailsAndPhotoSteps(): void
    {
        $registration = file_get_contents(FrontendPagePaths::path('guest-registration'));
        self::assertIsString($registration);
        $controller = file_get_contents(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'guest-registration' . DIRECTORY_SEPARATOR . 'guest-registration.page.js',
        );
        self::assertIsString($controller);

        foreach ([
            'guest-registration-progress',
            'data-guest-registration-step="details"',
            'data-guest-registration-step="photo"',
            'id="guest-details-continue"',
            'id="guest-photo-back"',
        ] as $marker) {
            self::assertStringContainsString($marker, $registration, "Missing guest registration step marker: {$marker}");
        }

        self::assertStringContainsString('showStep', $controller);
        self::assertStringContainsString("showStep('photo')", $controller);
        self::assertStringContainsString("showStep('details')", $controller);
    }

    public function testCanonicalGuestRegistrationFeatureControllerPreservesBoundaries(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'guest-registration' . DIRECTORY_SEPARATOR . 'guest-registration.page.js';
        self::assertFileExists($path);
        $script = file_get_contents($path);
        self::assertIsString($script);
        foreach (['guest-reg-form', 'otherPurposeWrap', 'guest-details-continue', 'guest-photo-back', 'registerGuest'] as $marker) {
            self::assertStringContainsString($marker, $script, "Missing canonical guest registration marker: {$marker}");
        }
    }

    public function testGuestBrowseControllerPreservesBookRequestAction(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'guest' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'browse' . DIRECTORY_SEPARATOR . 'guest-browse.page.js';
        self::assertFileExists($path);
        $script = file_get_contents($path);
        self::assertIsString($script);
        self::assertStringContainsString('Request to Borrow', $script);
    }

    public function testGuestHistoryControllerPreservesReturnVerificationState(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'guest' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'history' . DIRECTORY_SEPARATOR . 'guest-history.page.js';
        self::assertFileExists($path);
        $script = file_get_contents($path);
        self::assertIsString($script);
        self::assertStringContainsString('Return Verification', $script);
    }

    public function testCanonicalGuestRegistrationAndOtpTemplatesUseFeatureEntries(): void
    {
        $pages = [
            ['auth/pages/guest-registration/guest-registration.html', 'guest-registration', 'auth/pages/guest-registration/entry.js', ['id="guest-reg-form"', 'id="photo_data"', 'id="btn-start"', 'Send SMS OTP']],
            ['auth/pages/guest-otp/guest-otp.html', 'guest-otp', 'auth/pages/guest-otp/entry.js', ['Guest SMS Verification', 'id="guest-otp-form"', 'Resend OTP']],
            ['auth/pages/profile-otp/profile-otp.html', 'profile-otp', 'auth/pages/profile-otp/entry.js', ['Verify New Mobile Number', 'id="profile-otp-form"', 'Verify &amp; Save']],
        ];
        foreach ($pages as [$relativePath, $pageName, $entry, $markers]) {
            $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            self::assertFileExists($path);
            $html = file_get_contents($path);
            self::assertIsString($html);
            self::assertStringContainsString('data-app-page="' . $pageName . '"', $html);
            self::assertStringContainsString('frontend/features/' . $entry, $html);
            foreach ($markers as $marker) {
                self::assertStringContainsString($marker, $html);
            }
        }
    }

    public function testCanonicalGuestCatalogTemplatesUseFeatureModules(): void
    {
        foreach ([
            ['browse/browse.html', 'guest-browse', 'guest-browse.page.js', ['Browse Books', 'name="q"', 'name="category"']],
            ['borrowed/borrowed.html', 'guest-borrowed', 'guest-borrowed.page.js', ['Borrowed Books', 'currently borrowed books']],
        ] as [$relativePath, $pageName, $module, $markers]) {
            $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'guest' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            self::assertFileExists($path);
            $html = file_get_contents($path);
            self::assertIsString($html);
            self::assertStringContainsString('data-app-page="' . $pageName . '"', $html);
            self::assertStringContainsString('frontend/features/guest/pages/' . str_replace('/', '/', dirname($relativePath)) . '/' . $module, $html);
            foreach ($markers as $marker) {
                self::assertStringContainsString($marker, $html);
            }
        }
    }

    public function testCanonicalGuestHistoryAndBorrowRequestTemplatesUseFeatureModules(): void
    {
        foreach ([
            ['history/history.html', 'guest-history', 'guest-history.page.js', ['Borrowing History', 'name="status"', 'name="from"', 'name="to"']],
            ['borrow-request/borrow-request.html', 'guest-borrow-request', 'guest-borrow-request.page.js', ['Borrow Request', 'id="captureGuideModal"', 'id="government_id_barcode"', 'id="verification_photo"', 'Submit Request']],
        ] as [$relativePath, $pageName, $module, $markers]) {
            $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'guest' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            self::assertFileExists($path);
            $html = file_get_contents($path);
            self::assertIsString($html);
            self::assertStringContainsString('data-app-page="' . $pageName . '"', $html);
            self::assertStringContainsString('frontend/features/guest/pages/' . str_replace('/', '/', dirname($relativePath)) . '/' . $module, $html);
            foreach ($markers as $marker) {
                self::assertStringContainsString($marker, $html);
            }
        }
    }

    public function testCanonicalGuestReturnAndPassTemplatesUseFeatureModules(): void
    {
        foreach ([
            ['return/return.html', 'guest-return', 'guest-return.page.js', ['Return a Book', 'name="book_barcode"', 'id="return_photo"', 'Submit Return']],
            ['pass/pass.html', 'guest-pass', 'guest-pass.page.js', ['Registered Government ID', 'id="id-barcode"', 'Verified Government ID', 'window.print()']],
        ] as [$relativePath, $pageName, $module, $markers]) {
            $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'guest' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            self::assertFileExists($path);
            $html = file_get_contents($path);
            self::assertIsString($html);
            self::assertStringContainsString('data-app-page="' . $pageName . '"', $html);
            self::assertStringContainsString('frontend/features/guest/pages/' . str_replace('/', '/', dirname($relativePath)) . '/' . $module, $html);
            foreach ($markers as $marker) {
                self::assertStringContainsString($marker, $html);
            }
        }
    }
}
