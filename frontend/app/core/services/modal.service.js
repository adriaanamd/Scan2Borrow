export class ModalService {
  constructor({ document = globalThis.document, window = globalThis.window } = {}) {
    this.document = document;
    this.window = window;
  }

  instance(id) {
    const element = this.document.getElementById(id);
    const modal = this.window.bootstrap?.Modal;
    return element && modal?.getOrCreateInstance(element);
  }

  show(id) {
    this.instance(id)?.show();
  }

  hide(id) {
    this.instance(id)?.hide();
  }

  reset(id) {
    this.document.getElementById(id)?.reset?.();
  }
}
