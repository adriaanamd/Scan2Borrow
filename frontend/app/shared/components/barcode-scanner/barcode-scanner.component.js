import { ToastService } from '../../../core/services/toast.service.js';

export class BarcodeScannerComponent {
  static libraryUrl = 'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js';

  constructor(root, {
    document = globalThis.document,
    window = globalThis.window,
    navigator = globalThis.navigator,
    toastService = new ToastService({ document }),
    scannerFactory = (viewId) => new window.Html5Qrcode(viewId),
    onDecoded = null,
  } = {}) {
    this.root = root;
    this.document = document;
    this.window = window;
    this.navigator = navigator;
    this.toastService = toastService;
    this.scannerFactory = scannerFactory;
    this.onDecoded = onDecoded;
    this.libraryLoading = null;
    this.scanner = null;
    this.view = null;
    this.button = null;
    this.nativeInput = null;
    this.nativeButton = null;
    this.nativeTargetInput = null;
    this.nativeAutoSubmit = false;
    this.nativeTrigger = null;
    this.onNativeChange = (event) => this.handleNativeFile(event);
    this.onNativeButtonClick = () => this.openNativeCapture();
    this.onClick = this.onClick.bind(this);
  }

  start() {
    this.root.addEventListener('click', this.onClick);
    this.ensureNativeCapture();
    return this;
  }

  async onClick(event) {
    const button = event.target?.closest?.('[data-scan-target]');
    if (!button) return;
    event.preventDefault();

    const targetInput = this.document.getElementById(button.getAttribute('data-scan-target'));
    if (!targetInput) return;

    this.ensureNativeCapture();
    this.nativeTargetInput = targetInput;
    this.nativeAutoSubmit = button.hasAttribute('data-scan-submit');
    this.nativeTrigger = button;

    if (!this.navigator?.mediaDevices?.getUserMedia) {
      this.openNativeCapture();
      this.loadLibrary().catch((error) => this.showError(error.message));
      return;
    }

    try {
      await this.loadLibrary();
      this.startScanner(targetInput, this.nativeAutoSubmit, button);
    } catch (error) {
      this.showError(error.message);
    }
  }

  loadLibrary() {
    if (this.window.Html5Qrcode) return Promise.resolve();
    if (this.libraryLoading) return this.libraryLoading;

    this.libraryLoading = new Promise((resolve, reject) => {
      const script = this.document.createElement('script');
      script.src = BarcodeScannerComponent.libraryUrl;
      script.onload = resolve;
      script.onerror = () => reject(new Error('Failed to load scanner library'));
      this.document.head.appendChild(script);
    });

    return this.libraryLoading;
  }

  ensureNativeCapture() {
    if (this.nativeInput || !this.document?.createElement || !this.root?.append) return;
    const input = this.document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.capture = 'environment';
    input.className = 'd-none';
    input.setAttribute?.('aria-label', 'Take a barcode photo');
    input.addEventListener?.('change', this.onNativeChange);

    const button = this.document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-outline-secondary mt-2';
    button.textContent = 'Take photo instead';
    button.hidden = true;
    button.addEventListener?.('click', this.onNativeButtonClick);

    this.root.append(input, button);
    this.nativeInput = input;
    this.nativeButton = button;
  }

  openNativeCapture() {
    if (!this.nativeInput) return;
    if (this.nativeButton) this.nativeButton.hidden = false;
    this.nativeInput.click?.();
  }

  handleNativeFile(event) {
    const input = event?.target || this.nativeInput;
    const file = input?.files?.[0];
    if (!file || !this.nativeTargetInput) return Promise.resolve(null);

    return this.loadLibrary()
      .then(() => this.startFileScan(file))
      .catch((error) => {
        this.finishScan({ keepNativeFallback: true });
        this.showError(error.message || 'No barcode found. Try another photo.');
        if (input) input.value = '';
        return null;
      });
  }

  createScannerView(targetInput) {
    const viewId = this.root?.id ? `${this.root.id}-view` : 'scanner-view';
    let view = this.document.getElementById(viewId);
    if (!view) {
      view = this.document.createElement('div');
      view.id = viewId;
      targetInput.parentNode.parentNode.appendChild(view);
    }
    return { view, viewId };
  }

  startFileScan(file) {
    const targetInput = this.nativeTargetInput;
    const { view, viewId } = this.createScannerView(targetInput);
    const scanner = this.scannerFactory(viewId);
    this.scanner = scanner;
    this.view = view;
    this.button = this.nativeTrigger;
    if (this.button) {
      this.button.disabled = true;
      this.button.textContent = 'Reading barcode...';
    }

    return Promise.resolve(scanner.scanFile(file, true))
      .then((decodedText) => {
        this.finishScan();
        this.handleDecoded(decodedText, targetInput, this.nativeAutoSubmit);
        if (this.nativeInput) this.nativeInput.value = '';
        return decodedText;
      })
      .catch((error) => {
        this.finishScan({ keepNativeFallback: true });
        throw error;
      });
  }

  handleDecoded(decodedText, targetInput, autoSubmit) {
    const value = String(decodedText || '').trim();
    if (!value) return;
    targetInput.value = value;
    this.onDecoded?.(value, targetInput);
    if (autoSubmit && targetInput.form) targetInput.form.submit();
    else targetInput.focus?.();
  }

  startScanner(targetInput, autoSubmit, button) {
    const { view, viewId } = this.createScannerView(targetInput);
    const scanner = this.scannerFactory(viewId);
    this.scanner = scanner;
    this.view = view;
    this.button = button;
    button.disabled = true;
    button.textContent = 'Starting camera...';

    scanner.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 280, height: 140 } },
      (decodedText) => {
        Promise.resolve(scanner.stop()).then(() => {
          this.finishScan();
          this.handleDecoded(decodedText, targetInput, autoSubmit);
        });
      },
      () => {},
    ).then(() => {
      if (this.button === button) button.textContent = 'Scanning for barcode...';
    }).catch((error) => {
      this.finishScan({ keepNativeFallback: true });
      if (this.nativeButton) this.nativeButton.hidden = false;
      this.showError('Unable to access camera: ' + error);
    });

    view.onclick = () => scanner.stop().then(() => this.finishScan());
  }

  showError(message) {
    this.toastService?.show(message, 'danger');
  }

  finishScan({ keepNativeFallback = false } = {}) {
    if (this.view) this.view.innerHTML = '';
    if (this.button) {
      this.button.disabled = false;
      this.button.textContent = 'Scan with Camera';
    }
    this.scanner = null;
    this.view = null;
    this.button = null;
    if (!keepNativeFallback && this.nativeButton) this.nativeButton.hidden = true;
  }

  async stop() {
    if (!this.scanner) return;
    await this.scanner.stop();
    this.finishScan();
  }

  destroy() {
    this.root.removeEventListener('click', this.onClick);
    return this.stop().finally(() => {
      this.nativeInput?.removeEventListener?.('change', this.onNativeChange);
      this.nativeButton?.removeEventListener?.('click', this.onNativeButtonClick);
      this.nativeInput?.remove?.();
      this.nativeButton?.remove?.();
      this.nativeInput = null;
      this.nativeButton = null;
    });
  }
}
