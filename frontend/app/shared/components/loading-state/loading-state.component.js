export class LoadingStateComponent {
  constructor(root) {
    this.root = root;
  }

  show(message = 'Loading...') {
    this.root.hidden = false;
    this.root.textContent = message;
  }

  hide() {
    this.root.hidden = true;
  }

  destroy() {}
}
