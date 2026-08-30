# Gmail SMTP Mailer for Registration OTPs

## Goal

Enable real Gmail delivery for Scan2Borrow registration OTPs so a borrower can complete registration by entering a code received by email. Reuse the same transport for existing staff borrower notifications while keeping the current SMS abstraction available as a fallback when no email address is supplied.

## Context

The PHP backend already defines `EmailSenderInterface` and wires `NativeEmailSender` for staff notifications. That sender currently calls PHP's native `mail()` function, even though the local `config/.env` contains Gmail SMTP settings. Registration and guest-registration payloads already include an optional `email` field, but `OtpService` currently injects only `SmsSenderInterface`, whose local implementation is a no-op.

PHPMailer 6 source is available in the local workspace. The implementation will use PHPMailer as the SMTP client and the existing environment loader for configuration. No credential values belong in source, tests, or committed documentation.

## Design

### Mail transport

Replace the native mail implementation with a PHPMailer-backed `EmailSenderInterface` adapter. The adapter will read:

- `MAIL_HOST` (Gmail default: `smtp.gmail.com`)
- `MAIL_PORT` (Gmail default: `587`)
- `MAIL_USERNAME`
- `MAIL_PASSWORD` (a Gmail App Password, never the normal account password)
- `MAIL_FROM` (defaults to `MAIL_USERNAME`)
- `MAIL_FROM_NAME` (defaults to `Scan2Borrow Library Management`)

The Gmail connection will use SMTP authentication and STARTTLS. `isConfigured()` will report whether the minimum credentials are present. `send()` will return `false` for missing configuration or delivery failure and will not expose SMTP diagnostics to callers.

### OTP delivery flow

`OtpService` will accept both the existing SMS sender and the email sender. When the OTP payload contains a non-empty email address, it will send the OTP through Gmail. When no email address is present, it will retain the existing SMS path. Initial sends and resends will use the same selection rule.

Registration, guest registration, and guest profile update will continue using the existing OTP routes and session state. No frontend route or form change is needed because those forms already submit email values and redirect to the verification pages.

If email is supplied but the mailer is not configured or delivery fails, the OTP request will fail with a safe, actionable message rather than redirecting the user to enter an undeliverable code. SMTP credentials and low-level PHPMailer error text will not be returned in JSON responses.

### Email content

Registration OTP email will include:

- Scan2Borrow sender branding;
- the six-digit OTP;
- the five-minute expiration period; and
- a reminder not to share the code.

The message will be sent as both HTML and plain text for compatibility. Existing staff notification content will continue to use its current subject and HTML body through the same adapter.

### Configuration and safety

The app will continue loading `config/.env` through `Environment::load()`. The local file remains ignored by Git. Documentation will describe variable names and Gmail App Password setup without recording any actual secret. Error handling will avoid logging or returning passwords, authorization headers, or raw SMTP responses.

## Testing and verification

Focused PHPUnit tests will cover:

1. OTP delivery uses email when the payload contains an email address.
2. OTP resend uses email under the same condition.
3. Missing mail configuration produces a safe failure.
4. Email delivery failure produces a safe failure and does not report success.
5. Existing SMS fallback behavior remains available when email is absent.

The full backend PHPUnit suite and PHPStan analysis will run after implementation. A final manual check will submit a real registration using the purpose-built Gmail recipient, retrieve the OTP from Gmail, and complete the verification step.

## Out of scope

- OAuth-based Gmail authentication;
- SMS provider implementation;
- background queues or retries;
- changing registration fields from optional to required; and
- changing database schema or frontend layout.
