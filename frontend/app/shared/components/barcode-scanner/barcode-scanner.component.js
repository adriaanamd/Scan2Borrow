import { ToastService } from '../../../core/services/toast.service.js';

export class BarcodeScannerComponent {
  static libraryUrl = 'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js';

  constructor(root, {
    document = globalThis.document,
    window = globalThis.window,
    toastService = new ToastService({ document }),
    scannerFactory = (viewId) => new window.Html5Qrcode(viewId),
  } = {}) {
    this.root = root;
    this.document = document;
    this.window = window;
    this.toastService = toastService;
    this.scannerFactory = scannerFactory;
    this.libraryLoading = null;
    this.scanner = null;
    this.view = null;
    this.button = null;
    this.onClick = this.onClick.bind(this);
  }

  start() {
    this.root.addEventListener('click', this.onClick);
    return this;
  }

  async onClick(event) {
    const button = event.target?.closest?.('[data-scan-target]');
    if (!button) return;
    event.preventDefault();

    const targetInput = this.document.getElementById(button.getAttribute('data-scan-target'));
    if (!targetInput) return;

    try {
      await this.loadLibrary();
      this.startScanner(targetInput, button.hasAttribute('data-scan-submit'), button);
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

  startScanner(targetInput, autoSubmit, button) {
    const viewId = 'scanner-view';
    let view = this.document.getElementById(viewId);
    if (!view) {
      view = this.document.createElement('div');
      view.id = viewId;
      targetInput.parentNode.parentNode.appendChild(view);
    }

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
        targetInput.value = decodedText.trim();
        scanner.stop().then(() => {
          this.finishScan();
          if (autoSubmit && targetInput.form) targetInput.form.submit();
          else targetInput.focus();
        });
      },
      () => {},
    ).catch((error) => {
      this.finishScan();
      this.showError('Unable to access camera: ' + error);
    });

    view.onclick = () => scanner.stop().then(() => this.finishScan());
  }

  showError(message) {
    this.toastService?.show(message, 'danger');
  }

  finishScan() {
    if (this.view) this.view.innerHTML = '';
    if (this.button) {
      this.button.disabled = false;
      this.button.textContent = 'Scan with Camera';
    }
    this.scanner = null;
    this.view = null;
    this.button = null;
  }

  async stop() {
    if (!this.scanner) return;
    await this.scanner.stop();
    this.finishScan();
  }

  destroy() {
    this.root.removeEventListener('click', this.onClick);
    return this.stop();
  }
}
