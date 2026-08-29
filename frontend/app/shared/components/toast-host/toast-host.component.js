export class ToastHostComponent {
  constructor(root, { toastService }) {
    this.root = root;
    this.toastService = toastService;
  }

  start() {}

  show(message, type = 'info') {
    return this.toastService.show(message, type);
  }

  destroy() {}
}
