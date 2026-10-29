# TextBee OTP Sender Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the production SMS no-op with a real TextBee REST API sender for phone OTPs.

**Architecture:** Add a small `TextBeeSmsSender` implementing the existing `SmsSenderInterface`, using PHP cURL and environment configuration. Add a provider factory so production selects TextBee only when `SMS_ENABLED=true` and `SMS_PROVIDER=textbee`; unavailable providers must fail loudly instead of reporting a successful OTP send.

**Tech Stack:** PHP 8.5-compatible backend, PHPUnit 11, PHP cURL, TextBee REST API.

## Global Constraints

- TextBee endpoint: `https://api.textbee.dev/api/v1/gateway/send-sms`.
- Authenticate with the `x-api-key` request header.
- Send recipients in E.164 format; local Philippine numbers default to country code `63`.
- Never expose API keys, OTP values, or full phone numbers in exceptions or logs.
- Preserve the existing `SmsSenderInterface`, OTP message content, resend behavior, and email delivery.

### Task 1: Add failing TextBee sender and provider-selection tests

**Files:**
- Create: `backend/tests/Unit/Registration/TextBeeSmsSenderTest.php`
- Create: `backend/tests/Unit/Registration/SmsSenderFactoryTest.php`

- [x] Add tests that capture the outgoing URL, API key, JSON payload, and E.164 conversion without making a real network call.
- [x] Add tests for provider acceptance, HTTP/provider failure mapping to `OtpDeliveryException`, missing API key, and `SMS_ENABLED`/`SMS_PROVIDER` selection.
- [x] Run the focused tests with the PHP 8.5 executable and confirm they fail because the production classes do not exist.

### Task 2: Implement TextBee delivery and production wiring

**Files:**
- Create: `backend/src/Application/Services/TextBeeSmsSender.php`
- Create: `backend/src/Application/Services/SmsSenderFactory.php`
- Modify: `backend/src/Application/Services/NullSmsSender.php`
- Modify: `backend/src/Bootstrap/ApplicationFactory.php`

- [x] Implement the documented POST request with cURL, a bounded timeout, E.164 normalization, optional `SMS_DEVICE_ID`, and safe error mapping.
- [x] Treat an accepted batch or immediate successful send as success; reject non-2xx responses, malformed responses, and explicit provider failure.
- [x] Select TextBee from production configuration and make the fallback sender throw a delivery exception instead of silently discarding phone OTPs.
- [x] Run the focused tests and confirm they pass.

### Task 3: Document configuration and run the complete quality gate

**Files:**
- Modify: `config/.env.example`
- Modify: `README.md`

- [x] Document `SMS_ENABLED`, `SMS_PROVIDER`, `SMS_API_KEY`, `SMS_DEVICE_ID`, and the Philippine country-code default.
- [x] Run PHP syntax checks, focused PHPUnit tests, full PHPUnit, PHPStan, and frontend tests with the correct available runtime.
- [x] Review the diff and confirm no credentials or unrelated files changed.
