# Gmail SMTP Mailer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver Gmail SMTP email OTPs for registration and reuse the same safe mail transport for staff borrower notifications.

**Architecture:** Add a PHPMailer-backed `SmtpEmailSender` implementing the existing `EmailSenderInterface`. Extend `OtpService` with email delivery selection based on the existing OTP payload, preserving the SMS path when no email is present. Map delivery failures to safe JSON errors in registration and guest-auth controllers.

**Tech Stack:** PHP 8.2, framework-free modular monolith, PHPMailer 6 from the existing `PHPMailer/` directory, PHPUnit 11, PHPStan 2.

## Global Constraints

- Gmail SMTP uses authenticated STARTTLS on `smtp.gmail.com:587`.
- `MAIL_PASSWORD` is a Gmail App Password and must never be exposed in source, tests, logs, or responses.
- Email OTPs include the six-digit code and five-minute expiry.
- Email is used when the OTP payload contains an email; the existing SMS interface remains the fallback when it does not.
- No registration field becomes required, and no database schema or frontend layout changes are included.
- Preserve existing staged image changes; stage only files belonging to each implementation commit.

---

### Task 1: Add the Gmail SMTP sender

**Files:**
- Create: `backend/src/Application/Services/SmtpEmailSender.php`
- Create: `backend/tests/Unit/Mail/SmtpEmailSenderTest.php`
- Modify: `backend/src/Bootstrap/ApplicationFactory.php:19,83,156`

**Interfaces:**
- Consumes: `EmailSenderInterface::isConfigured(): bool` and `EmailSenderInterface::send(string $to, string $name, string $subject, string $html): bool`.
- Produces: `SmtpEmailSender` with the same public interface, configured from `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM`, and `MAIL_FROM_NAME`.

- [ ] **Step 1: Write failing configuration tests**

Add tests that temporarily set `MAIL_USERNAME` and `MAIL_PASSWORD`, assert `isConfigured()` is true, then clear them and assert it is false. Use `putenv()` in `try/finally` so the process environment is restored after each test. Add a send test with an invalid recipient and missing credentials that asserts `send()` returns false without throwing.

```php
public function testConfiguredRequiresGmailCredentials(): void
{
    $this->withEnvironment(['MAIL_USERNAME' => 'mailer@example.test', 'MAIL_PASSWORD' => 'app-password'], function (): void {
        self::assertTrue((new SmtpEmailSender())->isConfigured());
    });
}

public function testMissingCredentialsAreNotConfigured(): void
{
    $this->withEnvironment(['MAIL_USERNAME' => '', 'MAIL_PASSWORD' => ''], function (): void {
        self::assertFalse((new SmtpEmailSender())->isConfigured());
        self::assertFalse((new SmtpEmailSender())->send('recipient@example.test', 'Recipient', 'Subject', '<p>Body</p>'));
    });
}
```

- [ ] **Step 2: Run the focused tests and verify the expected failure**

Run:

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml backend\tests\Unit\Mail\SmtpEmailSenderTest.php
```

Expected: FAIL because `SmtpEmailSender` does not exist yet.

- [ ] **Step 3: Implement the PHPMailer adapter**

Load the existing bundled PHPMailer classes from the repository root and implement the following behavior:

```php
final class SmtpEmailSender implements EmailSenderInterface
{
    public function isConfigured(): bool
    {
        return $this->environment('MAIL_USERNAME') !== ''
            && $this->environment('MAIL_PASSWORD') !== '';
    }

    public function send(string $to, string $name, string $subject, string $html): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->environment('MAIL_HOST', 'smtp.gmail.com');
            $mail->Port = (int) $this->environment('MAIL_PORT', '587');
            $mail->SMTPAuth = true;
            $mail->Username = $this->environment('MAIL_USERNAME');
            $mail->Password = $this->environment('MAIL_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($this->environment('MAIL_FROM', $mail->Username), $this->environment('MAIL_FROM_NAME', 'Scan2Borrow Library Management'));
            $logo = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'logo.png';
            if (is_file($logo)) {
                $mail->addEmbeddedImage($logo, 'scan2borrow-school-seal', 'scan2borrow-school-seal.png', PHPMailer::ENCODING_BASE64, 'image/png');
            }
            $mail->addAddress($to, $name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $mail->send();
        } catch (Throwable) {
            return false;
        }
    }
}
```

Use `getenv()` with defaults and do not include the password or PHPMailer exception text in any returned value.

- [ ] **Step 4: Run the focused tests and verify they pass**

Run the same PHPUnit command. Expected: PASS with no warnings.

- [ ] **Step 5: Wire the adapter into production services**

Replace the `NativeEmailSender` import with `SmtpEmailSender`. Create one `SmtpEmailSender` instance for the `OtpService` constructor at line 83 and another for `BorrowerNotificationService` at line 156. Keep constructor injection through `EmailSenderInterface` in `BorrowerNotificationService`.

- [ ] **Step 6: Commit the transport**

```powershell
git add backend/src/Application/Services/SmtpEmailSender.php backend/tests/Unit/Mail/SmtpEmailSenderTest.php backend/src/Bootstrap/ApplicationFactory.php
git commit -m "feat: add Gmail SMTP email sender"
```

### Task 2: Route registration OTPs through email

**Files:**
- Create: `backend/src/Application/Services/OtpDeliveryException.php`
- Modify: `backend/src/Application/Services/OtpService.php`
- Modify: `backend/tests/Unit/Registration/OtpServiceTest.php`

**Interfaces:**
- Consumes: `EmailSenderInterface`, `SmsSenderInterface`, and OTP payloads containing an optional `email`, `firstname`, and `lastname`.
- Produces: `OtpDeliveryException` with safe user-facing messages; `OtpService` continues returning `string` from `start()` and `?string` from `resend()`.

- [ ] **Step 1: Add failing OTP email-routing tests**

Extend the existing OTP test fixture with a fake email sender that records calls and can return configured success/failure. Add tests for email on initial send, email on resend, unconfigured email delivery, failed email delivery, and SMS fallback when payload email is empty.

```php
public function testStartSendsOtpToPayloadEmail(): void
{
    $email = new FakeEmailSender(true);
    $sms = new FakeSmsSender();
    $service = new OtpService(new FakeOtpRepository(), new FixedClock(new DateTimeImmutable('2026-08-28 10:00:00')), $sms, $email);

    $service->start('2024004', ['firstname' => 'Lia', 'lastname' => 'Santos', 'email' => 'lia@example.test'], '09170000004');

    self::assertSame('lia@example.test', $email->to);
    self::assertSame('', $sms->phoneNumber);
    self::assertStringContainsString('Your OTP code is:', $email->html);
}

public function testStartReportsEmailDeliveryFailure(): void
{
    $this->expectException(OtpDeliveryException::class);

    (new OtpService(new FakeOtpRepository(), new FixedClock(new DateTimeImmutable('2026-08-28 10:00:00')), new FakeSmsSender(), new FakeEmailSender(false)))
        ->start('2024004', ['email' => 'lia@example.test'], '09170000004');
}
```

- [ ] **Step 2: Run the focused tests and verify the expected failure**

Run:

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml backend\tests\Unit\Registration\OtpServiceTest.php
```

Expected: FAIL because `OtpService` does not accept or use an email sender.

- [ ] **Step 3: Implement one delivery selector in `OtpService`**

Add `EmailSenderInterface` as a constructor dependency. After storing or updating the OTP record, call a private delivery method that:

```php
private function deliver(array $payload, string $phoneNumber, string $code, bool $resend): void
{
    $email = trim($payload['email'] ?? '');
    if ($email !== '') {
        if (!$this->email->isConfigured() || !$this->email->send($email, $this->recipientName($payload), 'Scan2Borrow Registration Verification Code', $this->messageHtml($code, $resend))) {
            throw new OtpDeliveryException('Unable to send the verification email. Please check the mail configuration and try again.');
        }

        return;
    }

    $this->sms->send($phoneNumber, $this->message($code, $resend));
}
```

Build `recipientName()` from escaped `firstname` and `lastname`, defaulting to `Scan2Borrow User`. Build a shared `emailShell()` with inline, table-safe CSS, the embedded `cid:scan2borrow-school-seal` logo reference, Binalbagan Catholic College / Scan2Borrow Library Services branding, a prominent blue OTP card, five-minute expiry, no-share warning, and Library Management Office footer. Use that shell for registration OTP mail and the existing staff borrower-record table. Keep the plain-text SMS message unchanged. Call the delivery method from both `start()` and `resend()`.

- [ ] **Step 4: Update all existing OTP test constructors and run the focused suite**

Pass `new FakeEmailSender(true)` to every existing `OtpService` test. Run the focused suite again and expect PASS. Confirm the pre-existing expiry, verification, resend throttling, and SMS tests remain green.

- [ ] **Step 5: Commit the OTP delivery behavior**

```powershell
git add backend/src/Application/Services/OtpDeliveryException.php backend/src/Application/Services/OtpService.php backend/tests/Unit/Registration/OtpServiceTest.php
git commit -m "feat: deliver registration OTPs by email"
```

### Task 3: Return safe delivery errors from registration endpoints

**Files:**
- Modify: `backend/src/Http/Controllers/RegistrationController.php`
- Modify: `backend/src/Http/Controllers/GuestAuthController.php`
- Create: `backend/tests/Unit/Registration/OtpDeliveryErrorMappingTest.php`

**Interfaces:**
- Consumes: `OtpDeliveryException` from registration, guest registration, and resend services.
- Produces: HTTP 503 JSON with `ok: false` and the safe delivery message; raw PHPMailer errors remain inaccessible to the response.

- [ ] **Step 1: Write failing controller error-mapping tests**

Construct each controller with the real concrete service types it requires, using in-memory repositories, a fixed clock, and a fake email sender that throws the delivery exception through `OtpService`. Invoke the relevant endpoint with a valid CSRF setup and assert status 503 plus the safe message. Cover initial registration and resend for the account flow; guest uses the same pattern in its controller. Do not subclass the final service classes.

```php
self::assertSame(503, $response->statusCode());
self::assertSame(
    ['ok' => false, 'errors' => ['Unable to send the verification email. Please check the mail configuration and try again.']],
    json_decode($response->toString(), true, 512, JSON_THROW_ON_ERROR),
);
```

- [ ] **Step 2: Run the focused test and verify the expected failure**

Run:

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml backend\tests\Unit\Registration\OtpDeliveryErrorMappingTest.php
```

Expected: FAIL because the controllers currently allow the exception to reach the application-level 500 handler.

- [ ] **Step 3: Catch only the delivery exception at controller boundaries**

Wrap registration begin and OTP resend calls in `try/catch (OtpDeliveryException $exception)` and return:

```php
return new JsonResponse(503, ['ok' => false, 'errors' => [$exception->getMessage()]]);
```

Do not catch `Throwable` here; preserve the existing application-level handling for unrelated failures.

- [ ] **Step 4: Run focused endpoint tests and the existing auth tests**

Run the new test plus:

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml backend\tests\Feature\AuthControllerTest.php backend\tests\Feature\GuestInteractionParityTest.php
```

Expected: PASS with no warnings.

- [ ] **Step 5: Commit safe error mapping**

```powershell
git add backend/src/Http/Controllers/RegistrationController.php backend/src/Http/Controllers/GuestAuthController.php backend/tests/Unit/Registration/OtpDeliveryErrorMappingTest.php
git commit -m "fix: report email delivery failures safely"
```

### Task 4: Document Gmail setup and run complete verification

**Files:**
- Create: `config/.env.example`
- Modify: `README.md`
- Create: `backend/tests/Unit/Mail/GmailConfigurationDocumentationTest.php`

**Interfaces:**
- Consumes: the existing `config/.env` environment loader.
- Produces: safe setup instructions for a Gmail App Password and a repeatable local test checklist.

- [ ] **Step 1: Write the documentation contract test**

Assert that `config/.env.example` contains the six `MAIL_*` variable names, Gmail host, port 587, and no password value. Assert that the README names App Passwords and the registration verification flow.

- [ ] **Step 2: Run the documentation test and verify it fails**

Run:

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml backend\tests\Unit\Mail\GmailConfigurationDocumentationTest.php
```

Expected: FAIL because the example file and README instructions do not exist.

- [ ] **Step 3: Add safe setup documentation**

Create `config/.env.example` with:

```dotenv
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-purpose-built-account@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_FROM=your-purpose-built-account@gmail.com
MAIL_FROM_NAME=Scan2Borrow Library Management
```

Add a README section explaining that the Gmail account must have 2-Step Verification enabled, the App Password must be used instead of the normal password, and registration should use an email recipient that can access the mailbox. Do not alter `config/.env` values.

- [ ] **Step 4: Run documentation, full PHPUnit, and PHPStan checks**

Run:

```powershell
C:\xampp\php\php.exe backend\vendor\bin\phpunit --configuration=backend\phpunit.xml
C:\xampp\php\php.exe backend\vendor\bin\phpstan analyse --configuration=backend/phpstan.neon
```

Expected: all PHPUnit tests pass and PHPStan reports no errors.

- [ ] **Step 5: Commit setup documentation**

```powershell
git add config/.env.example README.md backend/tests/Unit/Mail/GmailConfigurationDocumentationTest.php
git commit -m "docs: add Gmail mailer setup instructions"
```

- [ ] **Step 6: Perform the live registration check**

Confirm `config/.env` contains the purpose-built Gmail username and App Password without printing either value. Open `http://localhost/scan2borrow/register`, complete a student or teacher registration with an accessible email address, submit the form, retrieve the six-digit OTP from Gmail, enter it at `/verify-otp`, and confirm the account is created. If delivery fails, capture only the safe API error and SMTP host/port—not credentials—for diagnosis.

- [ ] **Step 7: Confirm the final commit set and worktree safety**

Run:

```powershell
git log -6 --oneline
git status --short
```

Expected: one design commit plus four logical implementation commits, with the pre-existing staged image changes still present and no secret file staged.
