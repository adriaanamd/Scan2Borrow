# Mobile Camera Support Design

## Goal

Make every camera-backed workflow usable on mobile browsers while preserving the current live-camera experience on laptops and on mobile browsers that support it.

The scope covers both:

- Barcode and QR scanning in borrower, inventory, circulation, history, dashboard, and borrow flows.
- Photo capture in registration, guest registration, guest borrow requests, and guest returns.

## Current context

The frontend is a framework-free modular JavaScript application. Live photo capture is shared by `CameraCaptureComponent`, except for the older inline registration camera path. Barcode scanning is shared by `BarcodeScannerComponent`, which loads `html5-qrcode` 2.3.8 and currently starts a live environment-facing camera stream.

The application already uses `playsinline` video markup for the live photo flows. The mobile failure mode is that `getUserMedia` or the live scanner can be unavailable or denied, especially when the site is not served from a browser-permitted secure context. Mobile browsers still provide a native camera/file-input path that can be used without a live stream.

## Design

### 1. Adaptive photo capture

Extend `CameraCaptureComponent` so it has two equivalent input paths:

1. Live capture through `navigator.mediaDevices.getUserMedia` with the existing front-facing camera preference.
2. A native image input configured with `accept="image/*"` and `capture="user"`.

The native input is created or bound by the shared component. Selecting or taking a photo reads the file as a data URL, stores it in the same hidden form field used by live capture, updates the same preview, and exposes the same retake behavior. Live-stream cleanup remains responsible for stopping all active tracks and removing event listeners.

The component should expose a clear fallback action. If live access is unsupported or fails, the fallback is available immediately. The shared component must continue to work with the existing injected DOM/document/window/navigator test doubles.

The registration page’s duplicated inline camera behavior will be moved onto the shared component contract so registration, guest registration, guest borrowing, and guest returns receive identical fallback behavior.

### 2. Adaptive barcode and QR scanning

Extend `BarcodeScannerComponent` with a native image-capture fallback per scanner root:

1. When live scanning is available, preserve the existing `html5-qrcode` stream and environment-facing camera behavior.
2. When live camera access is unavailable or the live scanner fails to start, expose a fallback action that opens an image input configured with `accept="image/*"` and `capture="environment"`.
3. Pass the selected image file to the existing `html5-qrcode` instance through its local file-decoding API (`scanFile`).
4. Treat a successful decoded value exactly like a live decoded value: trim it, populate the target input, stop/clean up scanner UI, invoke `onDecoded`, submit when requested, or restore focus.

The fallback decodes locally in the browser. No image upload or new backend endpoint is required. A decode failure leaves the scanner usable and reports a retryable error. Existing listener ownership, scanner cleanup, toast behavior, and library URL remain intact.

### 3. Mobile interaction and accessibility

The scanner and photo controls will use explicit labels that distinguish live scanning from taking a photo when both are available. Native inputs remain visually hidden only when their associated action is available by keyboard and pointer. Video previews remain inline and muted to avoid mobile autoplay restrictions.

The implementation will avoid assuming that `window.isSecureContext`, `navigator.mediaDevices`, or optional browser APIs exist. Capability checks and failure handling will select the fallback without throwing. Existing forms and API payloads remain unchanged.

## Testing

Add focused frontend tests for:

- Photo fallback file selection producing the same JPEG/data-URL field and preview state as live capture.
- Photo fallback reset/retake and stream cleanup.
- Barcode fallback invoking `scanFile` with the selected file.
- Barcode fallback decoding success through the existing callback/form path.
- Barcode fallback decode failure preserving retryability and showing a toast/error.
- Capability/live-start failure selecting or exposing the native fallback.

Keep the current live-camera tests and existing scanner listener/cleanup tests unchanged in intent. Run `npm test` after frontend edits. Run the complete repository quality checks required by CI when the implementation is complete, including the backend Composer quality command where dependencies are available.

## Non-goals

- No backend API or database changes.
- No replacement of the existing `html5-qrcode` dependency.
- No removal of live scanning or live photo capture on supported browsers.
- No automatic image upload or server-side barcode decoding.
