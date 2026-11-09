# Mobile Camera Support Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Make barcode/QR scanning and photo capture usable on mobile by retaining live camera access where available and adding native camera/file fallbacks.

**Architecture:** Keep all behavior in the existing shared CameraCaptureComponent and BarcodeScannerComponent. Photo pages expose a shared native image input and fallback action; barcode scanner roots create their fallback input/action dynamically so all existing scanner surfaces inherit the behavior without page-by-page controller changes. The fallback barcode image is decoded locally by the existing html5-qrcode 2.3.8 instance.

**Tech Stack:** Vanilla JavaScript ES modules, static HTML, Node built-in test runner, html5-qrcode@2.3.8, PHP Composer quality scripts.

## Global Constraints

- No backend API or database changes.
- No replacement of the existing html5-qrcode dependency.
- Preserve live scanning and live photo capture on supported browsers.
- Native barcode capture uses accept="image/*" and capture="environment".
- Native photo capture uses accept="image/*" and capture="user".
- Barcode images are decoded locally; do not upload them or add a server endpoint.
- Preserve existing barcode input, callback, form-submit, toast, and cleanup behavior.
- Preserve existing frontend test coverage and do not use focused/skipped tests or weakened assertions.
- Run npm test after frontend edits and the repository backend quality gate before completion when dependencies are available.
- Preserve unrelated worktree changes in frontend/features/staff/services/index.js, frontend/tests/scanner.test.js, EXAMPLE.jpg, uploads/202310449-606bb73417da81df.jpg, and the existing 2026-11-05 docs; append only the new scanner coverage needed by this task.

---

## File map

- frontend/app/shared/components/camera-capture/camera-capture.component.js owns live photo capture, native photo input handling, preview state, retake behavior, and stream cleanup.
- frontend/app/shared/components/barcode-scanner/barcode-scanner.component.js owns live barcode scanning, native barcode image capture, local scanFile decoding, and scanner cleanup.
- frontend/features/auth/pages/register/register.page.js stops owning a duplicate inline camera implementation and receives the shared camera component.
- frontend/features/auth/pages/register/entry.js constructs and injects the shared camera component for registration.
- frontend/features/auth/pages/register/register.html, frontend/features/auth/pages/guest-registration/guest-registration.html, frontend/features/guest/pages/borrow-request/borrow-request.html, and frontend/features/guest/pages/return/return.html expose the native photo input and fallback action.
- frontend/tests/camera.test.js covers live and native photo capture.
- frontend/tests/scanner.test.js covers live and native barcode scanning.
- frontend/tests/auth-pages.test.js verifies registration wiring and photo fallback markup.

## Task 1: Add native fallback behavior to the shared photo component

**Files:**
- Modify: frontend/app/shared/components/camera-capture/camera-capture.component.js
- Modify: frontend/tests/camera.test.js

**Interfaces:**
- Add constructor options fileInputId = 'camera-file', nativeButtonId = 'btn-native-camera', and fileReaderFactory = () => new FileReader().
- Add methods handleFileChange(event), applyPhoto(dataUrl, message), and openNativeCapture().
- handleFileChange(event) returns a promise that resolves after the selected file is converted to a data URL and applied.
- applyPhoto(dataUrl, message = 'Photo captured.') owns field, preview, video, stream, and control-state updates for both live and native capture.

- [ ] **Step 1: Extend the photo test doubles.**

Update frontend/tests/camera.test.js with a file input that records click() and a native button with the same class-list/listener contract as the existing controls.

~~~
const fileInput = {
  value: '',
  files: [],
  addEventListener(name, listener) { this.listeners ??= {}; this.listeners[name] = listener; },
  removeEventListener() {},
  click() { this.clicked = true; },
};
const nativeButton = { classList: classes(), addEventListener() {}, removeEventListener() {} };
~~~

- [ ] **Step 2: Add failing native photo tests.**

Add one test for missing getUserMedia and one for a selected image file. Inject a deterministic reader:

~~~
const reader = {
  readAsDataURL() {
    this.onload({ target: { result: 'data:image/jpeg;base64,photo' } });
  },
};
const component = new CameraCaptureComponent({}, {
  document,
  window: { addEventListener() {}, removeEventListener() {} },
  navigator: {},
  fileReaderFactory: () => reader,
});
await component.handleFileChange({ target: { files: [{ name: 'portrait.jpg' }] } });
assert.equal(field.value, 'data:image/jpeg;base64,photo');
assert.equal(preview.src, field.value);
~~~

The missing-camera test must assert that await component.start() calls fileInput.click() and sets a message offering the phone-camera fallback. Run before implementation:

~~~powershell
node --test frontend/tests/camera.test.js
~~~

Expected: the new native-path tests fail while the existing live-camera test remains green.

- [ ] **Step 3: Implement the shared native photo path.**

Add the new options and bind the input/button through the existing listen helper:

~~~
this.fileInput = document.getElementById(fileInputId);
this.nativeButton = document.getElementById(nativeButtonId);
this.fileReaderFactory = fileReaderFactory;
this.listen(this.fileInput, 'change', (event) => this.handleFileChange(event));
this.listen(this.nativeButton, 'click', () => this.openNativeCapture());
~~~

Implement openNativeCapture() as this.fileInput?.click(). If getUserMedia is missing, start() must set the fallback message and open the native input immediately. If live start rejects, keep the live start control available and show the fallback action.

Move common state changes into this method and make both live and native capture call it:

~~~
applyPhoto(dataUrl, message = 'Photo captured.') {
  if (!dataUrl || !this.field || !this.preview) return;
  this.field.value = dataUrl;
  this.preview.src = dataUrl;
  this.preview.classList.remove('d-none');
  this.video?.classList.add('d-none');
  this.stop();
  this.captureButton?.classList.add('d-none');
  this.retakeButton?.classList.remove('d-none');
  this.setMessage(message);
}
~~~

handleFileChange(event) must read the first file with fileReaderFactory, apply only string data URLs, clear the input value after processing, and show a retryable error for missing files or reader failures. reset() must also clear fileInput.value. Set video.playsInline, video.muted, and video.autoplay before live start; clear video.srcObject after stopping tracks. Preserve { video: { facingMode: 'user' }, audio: false }.

- [ ] **Step 4: Run the focused photo tests.**

~~~powershell
node --test frontend/tests/camera.test.js
~~~

Expected: all live and native photo tests pass.

- [ ] **Step 5: Commit the shared photo fallback.**

~~~powershell
git add -- frontend/app/shared/components/camera-capture/camera-capture.component.js frontend/tests/camera.test.js
git commit -m "feat: add native photo capture fallback"
~~~

## Task 2: Use the shared photo component on every photo page

**Files:**
- Modify: frontend/features/auth/pages/register/register.page.js
- Modify: frontend/features/auth/pages/register/entry.js
- Modify: frontend/features/auth/pages/register/register.html
- Modify: frontend/features/auth/pages/guest-registration/guest-registration.html
- Modify: frontend/features/guest/pages/borrow-request/borrow-request.html
- Modify: frontend/features/guest/pages/return/return.html
- Modify: frontend/tests/auth-pages.test.js

**Interfaces:**
- RegistrationPage accepts an optional camera object with destroy() and no longer owns bindCamera() or cameraCleanup.
- register/entry.js constructs new CameraCaptureComponent(document, { document, window, navigator: window.navigator }) and passes it as camera.
- All four photo templates expose #camera-file and #btn-native-camera, matching shared component defaults.

- [ ] **Step 1: Add failing markup coverage.**

Add this test to frontend/tests/auth-pages.test.js:

~~~js
test('photo pages expose native camera fallbacks', () => {
  for (const template of [
    '../features/auth/pages/register/register.html',
    '../features/auth/pages/guest-registration/guest-registration.html',
    '../features/guest/pages/borrow-request/borrow-request.html',
    '../features/guest/pages/return/return.html',
  ]) {
    const source = readFileSync(new URL(template, import.meta.url), 'utf8');
    assert.match(source, /id="camera-file"[^>]*accept="image\/\*"[^>]*capture="user"/);
    assert.match(source, /id="btn-native-camera"/);
  }
});
~~~

Run before changing templates:

~~~powershell
node --test frontend/tests/auth-pages.test.js
~~~

Expected: the new markup test fails because the four templates do not yet expose native controls.

- [ ] **Step 2: Replace registration's duplicate inline camera implementation.**

In register.page.js, remove page-owned bindCamera() and cameraCleanup, accept camera = null, assign this.camera = camera, and call this.camera?.destroy?.() in destroy() after page listeners are removed.

In register/entry.js, import the shared component and inject it:

~~~js
import { CameraCaptureComponent } from '../../../../app/shared/components/camera-capture/camera-capture.component.js';

const camera = new CameraCaptureComponent(document, {
  document,
  window,
  navigator: window.navigator,
});
return new RegistrationPage(document, {
  api: context.api,
  auth: new AuthService({ api: context.api, window }),
  camera,
  document,
  window,
}).start();
~~~

Keep registration navigation, OTP selection, course loading, and form submission unchanged.

- [ ] **Step 3: Add native photo controls to all four templates.**

Place this pair in each existing photo control group without removing live video/canvas/preview controls:

~~~html
<input id="camera-file" type="file" accept="image/*" capture="user" class="d-none" aria-label="Take photo with phone camera" />
<button type="button" id="btn-native-camera" class="btn btn-outline-secondary btn-sm">Use phone camera</button>
~~~

The guest borrow and guest return controllers retain their custom IDs for existing buttons and rely on the shared default IDs for native controls.

- [ ] **Step 4: Run page tests and commit.**

~~~powershell
node --test frontend/tests/auth-pages.test.js
git add -- frontend/features/auth/pages/register/register.page.js frontend/features/auth/pages/register/entry.js frontend/features/auth/pages/register/register.html frontend/features/auth/pages/guest-registration/guest-registration.html frontend/features/guest/pages/borrow-request/borrow-request.html frontend/features/guest/pages/return/return.html frontend/tests/auth-pages.test.js
git commit -m "refactor: share mobile photo capture across pages"
~~~

Expected: all auth-page tests pass and registration no longer contains a duplicate stream implementation.

## Task 3: Add local native image decoding to the shared barcode scanner

**Files:**
- Modify: frontend/app/shared/components/barcode-scanner/barcode-scanner.component.js
- Modify: frontend/tests/scanner.test.js

**Interfaces:**
- Add constructor option navigator = globalThis.navigator.
- Add methods ensureNativeCapture(), openNativeCapture(), handleNativeFile(event), startFileScan(file), and handleDecoded(value, targetInput, autoSubmit).
- handleDecoded is the single success path for live and file scans.
- startFileScan(file) calls scanner.scanFile(file, true) and resolves after success or failure cleanup.

- [ ] **Step 1: Add failing native barcode tests.**

Extend scanner doubles with root.append, document.createElement, and a file-input change listener. Add tests for unsupported live camera, successful file decoding, and retryable failure:

~~~js
test('scanner uses a native rear-camera input when live camera is unavailable', async () => {
  const nativeInput = {
    click() { this.clicked = true; },
    addEventListener(name, listener) { this.change = listener; },
    removeEventListener() {},
  };
  const root = makeScannerRoot({ nativeInput });
  const scanner = new BarcodeScannerComponent(root, {
    document: makeScannerDocument(root, nativeInput),
    window: { Html5Qrcode: function Html5Qrcode() {} },
    navigator: {},
    scannerFactory: () => ({ scanFile: () => Promise.resolve('B-7'), stop: () => Promise.resolve() }),
  });

  await scanner.onClick({ target: { closest: () => makeButton('barcode-input') }, preventDefault() {} });

  assert.equal(nativeInput.accept, 'image/*');
  assert.equal(nativeInput.capture, 'environment');
  assert.equal(nativeInput.clicked, true);
});

test('scanner sends a native image through scanFile and the existing callback path', async () => {
  const input = makeInput();
  const fileInput = { files: [{ name: 'barcode.jpg' }], value: '', addEventListener() {}, removeEventListener() {} };
  const decoded = [];
  const scanner = makeFileScanner({ input, fileInput, onDecoded: (value) => decoded.push(value) });

  await scanner.handleNativeFile({ target: fileInput });

  assert.equal(input.value, 'B-7');
  assert.deepEqual(decoded, ['B-7']);
});

test('scanner keeps the native retry available when image decoding fails', async () => {
  const notifications = [];
  const fileInput = { files: [{ name: 'blurred.jpg' }], value: '', addEventListener() {}, removeEventListener() {} };
  const scanner = makeFileScanner({
    fileInput,
    scanFile: () => Promise.reject(new Error('No barcode found')),
    toastService: { show(message, type) { notifications.push({ message, type }); } },
  });

  await scanner.handleNativeFile({ target: fileInput });

  assert.deepEqual(notifications, [{ message: 'No barcode found', type: 'danger' }]);
  assert.equal(fileInput.value, '');
});
~~~

Run before implementation:

~~~powershell
node --test frontend/tests/scanner.test.js
~~~

Expected: the new fallback tests fail while existing listener, library URL, callback, and announcement tests remain intact.

- [ ] **Step 2: Create and own one native fallback input/action per scanner root.**

Implement ensureNativeCapture() with one hidden input and one hidden button appended to the root:

~~~js
const input = this.document.createElement('input');
input.type = 'file';
input.accept = 'image/*';
input.capture = 'environment';
input.className = 'd-none';
input.setAttribute('aria-label', 'Take a barcode photo');
const button = this.document.createElement('button');
button.type = 'button';
button.className = 'btn btn-outline-secondary mt-2';
button.textContent = 'Take photo instead';
button.hidden = true;
button.addEventListener('click', () => this.openNativeCapture());
input.addEventListener('change', (event) => this.handleNativeFile(event));
this.root.append(input, button);
this.nativeInput = input;
this.nativeButton = button;
~~~

Call it from start() and before the unsupported-camera branch. Store active target input, submit flag, and trigger button so file decoding uses the same destination as live scanning.

- [ ] **Step 3: Route unsupported live access directly to the native input.**

In onClick, ensure the fallback exists. If this.navigator.mediaDevices?.getUserMedia is missing, store the scan context and call openNativeCapture() synchronously while the original click is active; defer library loading until handleNativeFile. For live-capable browsers, preserve library-loading and startScanner. When live start rejects, call finishScan(), reveal the fallback button, and toast Unable to access live camera. Use the phone camera instead.

- [ ] **Step 4: Share decoded-value handling.**

Move the existing success callback body into:

~~~js
handleDecoded(decodedText, targetInput, autoSubmit) {
  const value = String(decodedText || '').trim();
  if (!value) return;
  targetInput.value = value;
  this.onDecoded?.(value, targetInput);
  if (autoSubmit && targetInput.form) targetInput.form.submit();
  else targetInput.focus?.();
}
~~~

Live scans must stop before invoking handleDecoded. Native scans must load the library if needed, create the scanner instance, call scanFile(file, true), pass the result through handleDecoded, and clean up. On rejection, clear the file input, keep the fallback button visible, and show error.message || 'No barcode found. Try another photo.'. Preserve view, button, root-listener, and scanner-stop cleanup. destroy() must remove native listeners/nodes as well as stopping the active scanner.

- [ ] **Step 5: Run focused scanner tests and commit.**

~~~powershell
node --test frontend/tests/scanner.test.js
git add -- frontend/app/shared/components/barcode-scanner/barcode-scanner.component.js frontend/tests/scanner.test.js
git commit -m "feat: add native barcode image scanning fallback"
~~~

Expected: all existing and new scanner tests pass.

## Task 4: Run complete quality gates and inspect the final diff

**Files:**
- Review only the files changed in Tasks 1–3; do not stage unrelated existing worktree changes.

- [ ] **Step 1: Run targeted frontend tests.**

~~~powershell
node --test frontend/tests/camera.test.js frontend/tests/scanner.test.js frontend/tests/auth-pages.test.js
~~~

Expected: all targeted tests pass in under 110 seconds.

- [ ] **Step 2: Run the complete frontend suite.**

~~~powershell
npm test
~~~

Expected: every frontend/tests/*.test.js test passes without skips or focused filters.

- [ ] **Step 3: Run backend CI quality when dependencies are installed.**

From backend, run:

~~~powershell
composer run quality
~~~

This must execute the repository’s PHPUnit and PHPStan quality scripts. If backend/vendor is unavailable, report the backend gate as unverified rather than changing dependencies or weakening the gate.

- [ ] **Step 4: Check whitespace, status, and intended diff.**

~~~powershell
git diff --check
git status --short
git diff HEAD~3..HEAD -- frontend/app/shared/components/camera-capture/camera-capture.component.js frontend/app/shared/components/barcode-scanner/barcode-scanner.component.js frontend/features/auth/pages/register/register.page.js frontend/features/auth/pages/register/entry.js frontend/features/auth/pages/register/register.html frontend/features/auth/pages/guest-registration/guest-registration.html frontend/features/guest/pages/borrow-request/borrow-request.html frontend/features/guest/pages/return/return.html frontend/tests/camera.test.js frontend/tests/scanner.test.js frontend/tests/auth-pages.test.js
~~~

Expected: no whitespace errors; only intended camera implementation, templates, and tests are in task commits; unrelated user changes remain untouched.

- [ ] **Step 5: Make a final test-only commit only if needed.**

If verification requires a small in-scope test correction, rerun its targeted test and commit only the correction:

~~~powershell
git add -- frontend/tests/camera.test.js frontend/tests/scanner.test.js frontend/tests/auth-pages.test.js
git commit -m "test: stabilize mobile camera coverage"
~~~

Do not create this commit when no correction is needed.

