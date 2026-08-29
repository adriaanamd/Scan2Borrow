export class AuthBrandComponent {
  constructor(root) {
    this.root = root;
  }

  start() {
    this.root.innerHTML = `
      <img
        class="auth-brand-logo"
        src="/scan2borrow/public/logo.png"
        alt="Binalbagan Catholic College seal"
      />
      <div class="auth-brand-wordmark">Scan2Borrow</div>
      <p>School Library</p>`;
  }

  destroy() {}
}
