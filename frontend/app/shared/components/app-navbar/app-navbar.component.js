export class AppNavbarComponent {
  constructor(root, { session = null, window = globalThis.window } = {}) {
    this.root = root;
    this.session = session;
    this.window = window;
    this.roleHint = root.dataset.navbarRole || 'session';
  }

  async start() {
    const role = await this.resolveRole();
    this.render(role);
    this.setActiveLink();
  }

  async resolveRole() {
    if (this.roleHint === 'guest') return 'guest';

    try {
      const identity = this.session?.current?.() || await this.session?.load?.();
      if (identity?.role) return identity.role;
    } catch {
      // The protected page can still use its known role hint if the session request is unavailable.
    }

    return this.roleHint === 'session' ? '' : this.roleHint;
  }

  render(role) {
    switch (role) {
      case 'student':
        this.renderBorrower('student');
        break;
      case 'teacher':
        this.renderBorrower('teacher');
        break;
      case 'admin':
        this.renderStaff(true);
        break;
      case 'librarian':
        this.renderStaff(false);
        break;
      case 'guest':
        this.renderGuest();
        break;
      default:
        this.root.replaceChildren?.();
        this.root.innerHTML = '';
    }
  }

  renderBrand() {
    return '<div class="sidebar-brand"><span class="brand-mark">&#128218;</span><span>Scan2Borrow</span></div>';
  }

  renderBorrower(role) {
    const settingsPath = role === 'teacher'
      ? '/scan2borrow/teacher/settings'
      : '/scan2borrow/student/settings';
    const dashboardPath = role === 'teacher'
      ? '/scan2borrow/teacher/dashboard'
      : '/scan2borrow/student/dashboard';

    this.root.innerHTML = `${this.renderBrand()}
      <nav class="sidebar-nav">
        <a href="${settingsPath}" data-nav-path="${settingsPath}"><span class="nav-icon">&#9881;</span>Settings</a>
        <a href="${dashboardPath}" data-nav-path="${dashboardPath}"><span class="nav-icon">&#127968;</span>My Dashboard</a>
        <a href="/scan2borrow/student/search" data-nav-path="/scan2borrow/student/search"><span class="nav-icon">&#128269;</span>Search Books</a>
        <a href="/scan2borrow/student/history" data-nav-path="/scan2borrow/student/history"><span class="nav-icon">&#128220;</span>My History</a>
        ${this.logoutLink()}
      </nav>`;
  }

  renderStaff(isAdmin) {
    const apiDocs = isAdmin
      ? '<a href="/scan2borrow/admin/api-docs" data-nav-path="/scan2borrow/admin/api-docs"><span class="nav-icon">&#128196;</span>API Docs</a>'
      : '';

    this.root.innerHTML = `${this.renderBrand()}
      <nav class="sidebar-nav">
        <a href="/scan2borrow/staff/dashboard" data-nav-path="/scan2borrow/staff/dashboard"><span class="nav-icon">&#128202;</span>Dashboard</a>
        <a href="/scan2borrow/staff/books" data-nav-path="/scan2borrow/staff/books"><span class="nav-icon">&#128218;</span>Book Inventory</a>
        <a href="/scan2borrow/staff/students" data-nav-path="/scan2borrow/staff/students"><span class="nav-icon">&#128100;</span>Borrowers</a>
        <a href="/scan2borrow/staff/overdue" data-nav-path="/scan2borrow/staff/overdue"><span class="nav-icon">&#9888;</span>Overdue</a>
        <a href="/scan2borrow/staff/reports" data-nav-path="/scan2borrow/staff/reports"><span class="nav-icon">&#128203;</span>Reports</a>
        <a href="/scan2borrow/staff/guest-requests" data-nav-path="/scan2borrow/staff/guest-requests"><span class="nav-icon">&#128203;</span>Guest Requests</a>
        <a href="/scan2borrow/admin/staff" data-nav-path="/scan2borrow/admin/staff"><span class="nav-icon">&#128081;</span>Staff</a>
        ${apiDocs}
        ${this.logoutLink()}
      </nav>`;
  }

  renderGuest() {
    this.root.innerHTML = `${this.renderBrand()}
      <nav class="sidebar-nav">
        <a href="/scan2borrow/guest/dashboard" data-nav-path="/scan2borrow/guest/dashboard"><span class="nav-icon">&#127968;</span>My Dashboard</a>
        <a href="/scan2borrow/guest/profile" data-nav-path="/scan2borrow/guest/profile"><span class="nav-icon">&#128100;</span>Settings</a>
        <a href="/scan2borrow/guest/browse" data-nav-path="/scan2borrow/guest/browse"><span class="nav-icon">&#128269;</span>Browse Books</a>
        <a href="/scan2borrow/guest/borrowed" data-nav-path="/scan2borrow/guest/borrowed"><span class="nav-icon">&#128218;</span>Borrowed Books</a>
        <a href="/scan2borrow/guest/history" data-nav-path="/scan2borrow/guest/history"><span class="nav-icon">&#128220;</span>Borrowing History</a>
        <a href="/scan2borrow/guest/pass" data-nav-path="/scan2borrow/guest/pass"><span class="nav-icon">&#127903;</span>Government ID</a>
        ${this.logoutLink()}
      </nav>`;
  }

  logoutLink() {
    return '<a href="/scan2borrow/logout" class="nav-logout" data-confirm-action="logout" data-confirm-title="Log out?" data-confirm-message="Are you sure you want to log out?" data-confirm-label="Log out" data-confirm-class="btn-danger"><span class="nav-icon">&#9211;</span>Logout</a>';
  }

  setActiveLink() {
    const currentPath = (this.window.location?.pathname || '/').replace(/\/$/, '') || '/';
    this.root.querySelectorAll?.('[data-nav-path]').forEach((link) => {
      const active = link.dataset.navPath === currentPath;
      link.classList.toggle('active', active);
      if (active) link.setAttribute('aria-current', 'page');
    });
  }

  destroy() {}
}
