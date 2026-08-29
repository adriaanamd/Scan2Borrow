export class AppShellComponent {
  constructor(root, { navbar }) {
    this.root = root;
    this.navbar = navbar;
  }

  start() {
    const navbarRoot = this.root.querySelector('[data-app-navbar]');
    if (!navbarRoot) return;
    return this.navbar.start(navbarRoot);
  }

  destroy() {
    this.navbar.destroy?.();
  }
}
