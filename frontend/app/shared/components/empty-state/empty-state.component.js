export class EmptyStateComponent {
  constructor(root) {
    this.root = root;
  }

  show(message) {
    this.root.hidden = false;
    this.root.textContent = message;
  }

  clear() {
    this.root.hidden = true;
    this.root.textContent = '';
  }

  destroy() {}
}
