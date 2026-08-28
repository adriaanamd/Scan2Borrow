class StaffSidebar {
  constructor(root) {
    this.root = root;
  }

  start() {
    this.render();
    this.setActiveLink();
    this.revealAdminDocsLink();
  }

  render() {
    this.root.innerHTML = `
      <div class="sidebar-brand">
        <span class="brand-mark">&#128218;</span><span>Scan2Borrow</span>
      </div>
      <nav class="sidebar-nav">
        <a href="/scan2borrow/staff/dashboard" data-nav-path="/scan2borrow/staff/dashboard">
          <span class="nav-icon">&#128202;</span>Dashboard
        </a>
        <a href="/scan2borrow/staff/books" data-nav-path="/scan2borrow/staff/books">
          <span class="nav-icon">&#128218;</span>Book Inventory
        </a>
        <a href="/scan2borrow/staff/students" data-nav-path="/scan2borrow/staff/students">
          <span class="nav-icon">&#128100;</span>Borrowers
        </a>
        <a href="/scan2borrow/staff/overdue" data-nav-path="/scan2borrow/staff/overdue">
          <span class="nav-icon">&#9888;</span>Overdue
        </a>
        <a href="/scan2borrow/staff/reports" data-nav-path="/scan2borrow/staff/reports">
          <span class="nav-icon">&#128203;</span>Reports
        </a>
        <a href="/scan2borrow/staff/guest-requests" data-nav-path="/scan2borrow/staff/guest-requests">
          <span class="nav-icon">&#128203;</span>Guest Requests
        </a>
        <a href="/scan2borrow/admin/staff" data-nav-path="/scan2borrow/admin/staff">
          <span class="nav-icon">&#128081;</span>Staff
        </a>
        <a href="/scan2borrow/admin/api-docs" data-nav-path="/scan2borrow/admin/api-docs" data-admin-api-docs hidden>
          <span class="nav-icon">&#128196;</span>API Docs
        </a>
        <a href="/scan2borrow/logout" class="nav-logout">
          <span class="nav-icon">&#9211;</span>Logout
        </a>
      </nav>`;
  }

  setActiveLink() {
    const currentPath = window.location.pathname.replace(/\/$/, '') || '/';

    this.root.querySelectorAll('[data-nav-path]').forEach((link) => {
      const isActive = link.dataset.navPath === currentPath;
      link.classList.toggle('active', isActive);
      if (isActive) link.setAttribute('aria-current', 'page');
    });
  }

  async revealAdminDocsLink() {
    const link = this.root.querySelector('[data-admin-api-docs]');
    if (!link) return;

    try {
      const response = await fetch('/scan2borrow/api/auth/session', {
        headers: { Accept: 'application/json' },
      });
      const payload = await response.json();
      const isAdmin = payload.data?.role === 'admin';
      link.hidden = payload.ok !== true || !isAdmin;
    } catch {
      link.hidden = true;
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-staff-sidebar]').forEach((root) => {
    new StaffSidebar(root).start();
  });
});
