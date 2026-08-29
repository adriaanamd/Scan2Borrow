<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class GuestMarkupParityTest extends TestCase
{
    /** @var array<string, list<string>> */
    private const PAGE_MARKERS = [
        'guest-registration.html' => ['Guest Registration', 'id="guest-reg-form"', 'id="photo_data"', 'id="btn-start"', 'name="id_barcode"', 'Send SMS OTP'],
        'guest-verify-otp.html' => ['Guest SMS Verification', 'name="otp"', 'maxlength="6"', 'Resend OTP'],
        'guest-dashboard.html' => ['Guest Dashboard', 'id="borrowModal"', 'id="returnModal"', 'guest_borrow_barcode', 'guest_return_barcode', 'Reading Activity', 'Security Log'],
        'guest-profile.html' => ['Settings', 'name="contact_no"', 'name="purpose_other"', 'Save Changes'],
        'guest-profile-verify-otp.html' => ['Verify New Mobile Number', 'name="otp"', 'Verify &amp; Save', 'Resend OTP'],
        'guest-browse-books.html' => ['Browse Books', 'name="q"', 'name="category"'],
        'guest-borrowed-books.html' => ['Borrowed Books', 'currently borrowed books'],
        'guest-borrowing-history.html' => ['Borrowing History', 'name="status"', 'name="from"', 'name="to"'],
        'guest-borrow-request.html' => ['Borrow Request', 'id="captureGuideModal"', 'id="government_id_barcode"', 'id="verification_photo"', 'id="cam"', 'id="snap"', 'Submit Request'],
        'guest-return-book.html' => ['Return a Book', 'name="book_barcode"', 'id="return_photo"', 'Submit Return'],
        'guest-pass.html' => ['Registered Government ID', 'id="id-barcode"', 'Verified Government ID', 'window.print()'],
        'guest-receipt.html' => ['Borrowing Receipt', 'Scan2Borrow Library', 'window.print()', 'class="no-print'],
    ];

    public function testAllGuestPagesPreserveLegacyMarkupContracts(): void
    {
        foreach (self::PAGE_MARKERS as $filename => $markers) {
            $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $filename;
            self::assertFileExists($path, "Missing guest page {$filename}");
            $html = file_get_contents($path);
            self::assertIsString($html);
            foreach ($markers as $marker) {
                self::assertStringContainsString($marker, $html, "Missing {$filename} marker: {$marker}");
            }
        }
    }

    public function testGuestCameraAndPageControllersAreClassBased(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'guest';
        foreach (['camera-capture.js', 'registration.js', 'borrow-request.js', 'return-book.js'] as $filename) {
            $path = $root . DIRECTORY_SEPARATOR . $filename;
            self::assertFileExists($path, "Missing guest controller {$filename}");
            $script = file_get_contents($path);
            self::assertIsString($script);
            self::assertStringContainsString('class ', $script);
        }
    }

    public function testGuestRegistrationSeparatesDetailsAndPhotoSteps(): void
    {
        $registration = file_get_contents(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'guest-registration.html',
        );
        self::assertIsString($registration);
        $controller = file_get_contents(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'guest' . DIRECTORY_SEPARATOR . 'registration.js',
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
        self::assertStringContainsString('showStep("photo")', $controller);
        self::assertStringContainsString('showStep("details")', $controller);
    }

    public function testCanonicalGuestRegistrationFeatureControllerPreservesBoundaries(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'guest-registration' . DIRECTORY_SEPARATOR . 'guest-registration.page.js';
        self::assertFileExists($path);
        $script = file_get_contents($path);
        self::assertIsString($script);
        foreach (['guest-reg-form', 'photo_data', 'otherPurposeWrap', 'guest-details-continue', 'guest-photo-back', 'registerGuest'] as $marker) {
            self::assertStringContainsString($marker, $script, "Missing canonical guest registration marker: {$marker}");
        }
    }

    public function testGuestBrowseControllerPreservesBookRequestAction(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'guest' . DIRECTORY_SEPARATOR . 'browse.js';
        self::assertFileExists($path);
        $script = file_get_contents($path);
        self::assertIsString($script);
        self::assertStringContainsString('Request to Borrow', $script);
    }

    public function testGuestHistoryControllerPreservesReturnVerificationState(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'guest' . DIRECTORY_SEPARATOR . 'history.js';
        self::assertFileExists($path);
        $script = file_get_contents($path);
        self::assertIsString($script);
        self::assertStringContainsString('Return Verification', $script);
    }
}
