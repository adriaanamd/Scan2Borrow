# OTP Delivery Channel Selection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let registrants explicitly choose email or cellphone OTP delivery, validate the selected destination on the server, and show the selected channel during verification.

**Architecture:** Add `otpChannel` to `RegistrationRequest`, validate it with the selected destination, and include `otp_channel` in the persisted OTP payload. Registration OTP delivery will honor that explicit payload value while preserving the existing inference fallback for guest/profile OTP flows. The registration response will include the channel for a verification-page query parameter, which is used only for channel-specific explanatory copy; resend and delivery authority remain server-side.

**Tech Stack:** PHP 8.2+, PHPUnit 11, framework-free PHP backend, browser ES modules, Node.js built-in test runner, Bootstrap 5 markup.

## Global Constraints

- Valid OTP channels are exactly `email` and `phone`.
- The selected contact method must be non-empty and valid.
- OTP lifetime remains five minutes.
- Resend cooldown remains 60 seconds.
- OTP values, SMTP credentials, and full contact values must not be exposed in API responses or logs.
- Preserve guest and profile OTP behavior unless the change is explicitly required for shared delivery code.
- Do not stage or modify the unrelated untracked file `uploads/112299-81e92dfebf90d638.jpg`.

---

### Task 1: Add explicit channel validation and backend delivery routing

**Files:**
- Modify: `backend/src/Application/DTO/RegistrationRequest.php`
- Modify: `backend/src/Application/Validators/RegistrationValidator.php`
- Modify: `backend/src/Application/Services/RegistrationService.php`
- Modify: `backend/src/Application/Services/OtpService.php`
- Modify: `backend/src/Http/Controllers/RegistrationController.php`
- Test: `backend/tests/Unit/Registration/RegistrationValidatorTest.php`
- Test: `backend/tests/Unit/Registration/RegistrationServiceTest.php`
- Test: `backend/tests/Unit/Registration/OtpServiceTest.php`

**Interfaces:**
- `RegistrationRequest::$otpChannel` stores `email` or `phone`, defaulting to an empty string so omitted HTTP input is distinguishable and rejected.
- `RegistrationService::payload()` produces `otp_channel` in the OTP payload.
- `OtpService::deliver()` uses explicit `otp_channel` for registration payloads and retains existing email-present/phone fallback when the key is absent for guest/profile flows.
- `RegistrationController::begin()` reads the HTTP `otp_channel` field and returns it as `data.channel` after successful registration.

- [ ] **Step 1: Write failing validator and routing tests**

Add validator cases asserting:
```php
self::assertSame(
    'Please choose how to receive your verification code.',
    $validator->firstError(new RegistrationRequest(
        '2024', 'Juan', '', 'Cruz', 'student',
        otpChannel: '', course: 'BSIT', yearLevel: '3', contactNo: '09170000001',
    )),
);
self::assertSame(
    'Please enter an email address to receive your verification code.',
    $validator->firstError(new RegistrationRequest(
        '2024', 'Juan', '', 'Cruz', 'student',
        otpChannel: 'email', course: 'BSIT', yearLevel: '3', contactNo: '09170000001',
    )),
);
self::assertSame(
    'Please enter a cellphone number to receive your verification code.',
    $validator->firstError(new RegistrationRequest(
        '2024', 'Juan', '', 'Cruz', 'student',
        otpChannel: 'phone', course: 'BSIT', yearLevel: '3', email: 'juan@example.test',
    )),
);
```

Add an `OtpServiceTest` case with payload `['otp_channel' => 'phone', 'email' => 'lia@example.test']` and assert the SMS sender is used, plus a case with `['otp_channel' => 'email', 'contact_no' => '09170000004', 'email' => 'lia@example.test']` and assert the email sender is used. Update existing valid registration fixtures to set `otpChannel: 'phone'` and assert the registration OTP payload contains `otp_channel => 'phone'`.

- [ ] **Step 2: Run the focused PHP tests and verify they fail for the missing behavior**

Run:
```powershell
Set-Location backend
composer test -- --filter "RegistrationValidatorTest|RegistrationServiceTest|OtpServiceTest"
```

Expected: FAIL because `RegistrationRequest` does not yet expose `otpChannel`, the validator does not require it, and delivery does not honor explicit channel values.

- [ ] **Step 3: Implement the DTO, validator, payload, controller, and delivery changes**

Use these validation branches before destination-format checks:
```php
if (!in_array($request->otpChannel, ['email', 'phone'], true)) {
    return 'Please choose how to receive your verification code.';
}
if ($request->otpChannel === 'email' && $request->email === '') {
    return 'Please enter an email address to receive your verification code.';
}
if ($request->otpChannel === 'phone' && $request->contactNo === '') {
    return 'Please enter a cellphone number to receive your verification code.';
}
```

Add `otp_channel => $request->otpChannel` to the registration payload. In `RegistrationController::begin()`, pass `otpChannel: $this->string($body, 'otp_channel')` into the request and return `data.channel` with the selected value.

In `OtpService::deliver()`, branch as follows:
```php
$channel = trim($payload['otp_channel'] ?? '');
if ($channel === 'email' || ($channel === '' && trim($payload['email'] ?? '') !== '')) {
    // existing configured email send and safe exception behavior
    return;
}
if ($channel === 'phone' || $channel === '') {
    $this->sms->send($phoneNumber, $this->message($code, $resend));
    return;
}
throw new OtpDeliveryException('Unable to send the verification code using the selected method.');
```

Keep the existing email and SMS message formats, five-minute expiry, and 60-second resend logic unchanged.

- [ ] **Step 4: Run the focused PHP tests and verify they pass**

Run:
```powershell
Set-Location backend
composer test -- --filter "RegistrationValidatorTest|RegistrationServiceTest|OtpServiceTest"
```

Expected: all selected tests pass with zero failures.

- [ ] **Step 5: Commit the backend slice**

```powershell
git add backend/src/Application/DTO/RegistrationRequest.php backend/src/Application/Validators/RegistrationValidator.php backend/src/Application/Services/RegistrationService.php backend/src/Application/Services/OtpService.php backend/src/Http/Controllers/RegistrationController.php backend/tests/Unit/Registration/RegistrationValidatorTest.php backend/tests/Unit/Registration/RegistrationServiceTest.php backend/tests/Unit/Registration/OtpServiceTest.php
git commit -m "feat: route registration OTP by selected channel"
```

---

### Task 2: Add the registration channel selector

**Files:**
- Modify: `frontend/features/auth/pages/register/register.html`
- Modify: `frontend/features/auth/pages/register/register.page.js`
- Test: `frontend/tests/auth-pages.test.js`

**Interfaces:**
- The form contains radio inputs named `otp_channel` with values `email` and `phone`.
- `RegistrationPage.syncOtpChannel()` selects the only available contact automatically, preserves an existing choice when both are available, and sets `required` only on the selected contact field.
- The existing `formDataFactory(this.form)` submission includes the selected radio value.

- [ ] **Step 1: Write a failing frontend behavior test**

Add a `RegistrationPage` test with fake email, phone, and two channel radio controls. Assert that a phone-only form automatically checks `phone`, marks the phone field required, leaves email optional, and includes `otp_channel: 'phone'` in the form submission fixture. Add a second assertion that changing the channel to email marks email required and phone optional.

- [ ] **Step 2: Run the frontend test and verify it fails**

Run:
```powershell
npm test -- --test-name-pattern="RegistrationPage"
```

Expected: FAIL because the registration page has no channel controls or synchronization method.

- [ ] **Step 3: Implement the selector and synchronization**

Add an accessible fieldset before the email/contact inputs:
```html
<fieldset class="col-12">
  <legend class="form-label fw-semibold mb-1">Send verification code via</legend>
  <div class="d-flex flex-wrap gap-3" id="otp-channel-options">
    <label class="form-check">
      <input class="form-check-input" type="radio" name="otp_channel" value="email" />
      <span class="form-check-label">Email</span>
    </label>
    <label class="form-check">
      <input class="form-check-input" type="radio" name="otp_channel" value="phone" />
      <span class="form-check-label">Cellphone number</span>
    </label>
  </div>
  <div class="form-text">Choose where we should send your one-time verification code.</div>
</fieldset>
```

In the constructor, cache `emailInput`, `phoneInput`, and `otpChannelInputs`, bind their `input` and `change` events, call `syncOtpChannel()` after role setup, and implement:
```js
syncOtpChannel() {
  const email = this.emailInput?.value.trim() !== '';
  const phone = this.phoneInput?.value.trim() !== '';
  const selected = [...this.otpChannelInputs].find((input) => input.checked);

  if (!selected && email !== phone) {
    const preferred = email ? 'email' : phone ? 'phone' : '';
    this.otpChannelInputs.find((input) => input.value === preferred)?.click();
  }

  const channel = [...this.otpChannelInputs].find((input) => input.checked)?.value || '';
  if (this.emailInput) this.emailInput.required = channel === 'email';
  if (this.phoneInput) this.phoneInput.required = channel === 'phone';
}
```

Call `syncOtpChannel()` from contact input listeners and channel change listeners. Keep both contact fields visible so users can provide either or both, while browser validation enforces the selected destination.

- [ ] **Step 4: Run frontend tests and verify they pass**

Run:
```powershell
npm test -- --test-name-pattern="RegistrationPage"
```

Expected: all matching tests pass with zero failures.

- [ ] **Step 5: Commit the frontend selector**

```powershell
git add frontend/features/auth/pages/register/register.html frontend/features/auth/pages/register/register.page.js frontend/tests/auth-pages.test.js
git commit -m "feat: add OTP delivery choice to registration"
```

---

### Task 3: Make verification copy reflect the selected channel

**Files:**
- Modify: `frontend/features/auth/pages/otp/otp.html`
- Modify: `frontend/features/auth/pages/otp/otp.page.js`
- Modify: `frontend/features/auth/pages/register/register.page.js`
- Test: `frontend/tests/auth-pages.test.js`

**Interfaces:**
- Successful registration redirect becomes `/verify-otp?channel=email` or `/verify-otp?channel=phone`.
- `OtpPage.updateChannelCopy()` accepts only `email` and `phone`; unknown/missing values use generic “your selected contact method” copy.
- The verification page does not expose the full email address or phone number.

- [ ] **Step 1: Write failing verification-copy tests**

Add an `OtpPage` test whose fake window has `location.search = '?channel=email'` and whose document contains `#otp-channel-copy`; assert the copy says “email address”. Repeat with `?channel=phone` and assert “cellphone number”. Add a test that a registration success redirect includes `?channel=phone` when the API response returns `data.channel: 'phone'`.

- [ ] **Step 2: Run the frontend tests and verify they fail**

Run:
```powershell
npm test -- --test-name-pattern="OTP|OtpPage|RegistrationPage"
```

Expected: FAIL because the verification page has hard-coded phone copy and registration redirects do not append the selected channel.

- [ ] **Step 3: Implement redirect and channel-specific copy**

In `RegistrationPage.submit()`, build the redirect from `response.data.redirect` and append `?channel=${encodeURIComponent(response.data.channel)}` only when the channel is `email` or `phone`.

Replace the hard-coded verification copy with:
```html
Enter the 6-digit code sent to your <span id="otp-channel-copy">selected contact method</span>.
```

In `OtpPage`, call `updateChannelCopy()` during construction and set:
```js
const copy = {
  email: 'email address',
  phone: 'cellphone number',
};
node.textContent = copy[this.channel] || 'selected contact method';
```

Use `new URLSearchParams(this.window.location?.search || '').get('channel')` and accept only the two known channel values.

- [ ] **Step 4: Run frontend tests and verify they pass**

Run:
```powershell
npm test -- --test-name-pattern="OTP|OtpPage|RegistrationPage"
```

Expected: all matching tests pass with zero failures.

- [ ] **Step 5: Commit the verification copy**

```powershell
git add frontend/features/auth/pages/otp/otp.html frontend/features/auth/pages/otp/otp.page.js frontend/features/auth/pages/register/register.page.js frontend/tests/auth-pages.test.js
git commit -m "feat: show OTP delivery channel during verification"
```

---

### Task 4: Update documentation and verify the merged feature

**Files:**
- Modify: `README.md`
- Modify: `backend/src/Http/Documentation/ApiEndpointCatalog.php`
- Test: existing backend and frontend suites

- [ ] **Step 1: Update registration/API documentation**

Document that registration accepts `otp_channel=email|phone`, that the selected contact is required, and that the verification page reflects the selected channel. Update the registration endpoint body field list in `ApiEndpointCatalog.php` to include `otp_channel`.

- [ ] **Step 2: Run all frontend tests**

Run:
```powershell
npm test
```

Expected: all frontend tests pass with zero failures.

- [ ] **Step 3: Run all backend tests**

Run:
```powershell
Set-Location backend
composer test
```

Expected: the established baseline failures may remain only in `FrontendVisualSystemTest::testSharedStylesDefineACompleteHighContrastApplicationShell` and `TeacherBorrowHistoryRoutingTest::testTeacherApiAliasesReuseBorrowerHandlers`; no new OTP-related failures are allowed.

- [ ] **Step 4: Run static analysis**

Run:
```powershell
composer analyse
```

Expected: no new OTP-related PHPStan errors beyond the established baseline.

- [ ] **Step 5: Inspect the final diff and commit documentation**

```powershell
git diff master...HEAD --check
git status --short
git add README.md backend/src/Http/Documentation/ApiEndpointCatalog.php
git commit -m "docs: document OTP delivery channel choice"
```

Expected: only intended tracked files are changed; the unrelated upload remains untracked and unstaged.

- [ ] **Step 6: Review commit history**

```powershell
git log --oneline --decorate -8
```

Expected: the feature has separate backend, registration UI, verification UI, and documentation commits, all based on the approved design spec.

