class AppNavbar {
  constructor(root) {
    this.root = root;
    this.roleHint = root.dataset.navbarRole || "session";
    this.renderedRole = "";
  }

  async start() {
    const cachedRole = this.roleHint === "session" ? this.cachedRole() : "";
    const initialRole = this.roleHint === "session"
      ? (this.roleMatchesCurrentPath(cachedRole) ? cachedRole : "")
      : this.roleHint;
    if (initialRole) {
      this.render(initialRole);
      this.setActiveLink();
      return;
    }

    const role = await this.resolveRole();
    this.cacheRole(role);
    this.render(role);
    this.setActiveLink();
  }

  async resolveRole() {
    if (this.roleHint === "guest") return "guest";

    try {
      const response = await window.fetch("/scan2borrow/api/auth/session", {
        headers: { Accept: "application/json" },
      });
      const payload = await response.json();
      if (payload.ok === true && payload.data?.role) {
        return payload.data.role;
      }
    } catch {
      // The protected page can still use its known role hint if the session
      // request is temporarily unavailable.
    }

    return this.roleHint === "session" ? "" : this.roleHint;
  }

  render(role) {
    this.renderedRole = role;
    switch (role) {
      case "student":
        this.renderBorrower("student");
        break;
      case "teacher":
        this.renderBorrower("teacher");
        break;
      case "admin":
        this.renderStaff(true);
        break;
      case "librarian":
        this.renderStaff(false);
        break;
      case "guest":
        this.renderGuest();
        break;
      default:
        this.root.replaceChildren();
    }
  }

  cachedRole() {
    try {
      return window.sessionStorage?.getItem("scan2borrow.nav.role") || "";
    } catch {
      return "";
    }
  }

  roleMatchesCurrentPath(role) {
    if (!role) return false;
    const path = window.location.pathname.replace(/\/$/, "");
    if (path.includes("/staff/")) return role === "admin" || role === "librarian";
    if (path.includes("/admin/")) return role === "admin";
    if (path.includes("/student/settings")) return role === "student";
    if (path.includes("/student/")) return role === "student" || role === "teacher";
    if (path.includes("/teacher/")) return role === "teacher";
    if (path.includes("/guest/")) return role === "guest";
    return true;
  }

  cacheRole(role) {
    if (!role || role === "guest") return;
    try {
      window.sessionStorage?.setItem("scan2borrow.nav.role", role);
    } catch {
      // Storage may be unavailable in private browsing or restricted frames.
    }
  }

  renderBorrower(role) {
    const settingsPath =
      role === "teacher"
        ? "/scan2borrow/teacher/settings"
        : "/scan2borrow/student/settings";
    const dashboardPath =
      role === "teacher"
        ? "/scan2borrow/teacher/dashboard"
        : "/scan2borrow/student/dashboard";

    this.root.innerHTML = `
      <div class="sidebar-brand">
        <span class="brand-mark">&#128218;</span><span>Scan2Borrow</span>
      </div>
      <nav class="sidebar-nav">
        <a href="${settingsPath}" data-nav-path="${settingsPath}">
          <span class="nav-icon">&#9881;</span>Settings
        </a>
        <a href="${dashboardPath}" data-nav-path="${dashboardPath}">
          <span class="nav-icon">&#127968;</span>My Dashboard
        </a>
        <a href="/scan2borrow/student/search" data-nav-path="/scan2borrow/student/search">
          <span class="nav-icon">&#128269;</span>Search Books
        </a>
        <a href="/scan2borrow/student/history" data-nav-path="/scan2borrow/student/history">
          <span class="nav-icon">&#128220;</span>My History
        </a>
        ${this.logoutLink()}
      </nav>`;
  }

  renderStaff(isAdmin) {
    const apiDocs = isAdmin
      ? `<a href="/scan2borrow/admin/api-docs" data-nav-path="/scan2borrow/admin/api-docs">
          <span class="nav-icon">&#128196;</span>API Docs
        </a>`
      : "";

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
        ${apiDocs}
        ${this.logoutLink()}
      </nav>`;
  }

  renderGuest() {
    this.root.innerHTML = `
      <div class="sidebar-brand">
        <span class="brand-mark">&#128218;</span><span>Scan2Borrow</span>
      </div>
      <nav class="sidebar-nav">
        <a href="/scan2borrow/guest/dashboard" data-nav-path="/scan2borrow/guest/dashboard">
          <span class="nav-icon">&#127968;</span>My Dashboard
        </a>
        <a href="/scan2borrow/guest/profile" data-nav-path="/scan2borrow/guest/profile">
          <span class="nav-icon">&#128100;</span>Settings
        </a>
        <a href="/scan2borrow/guest/browse" data-nav-path="/scan2borrow/guest/browse">
          <span class="nav-icon">&#128269;</span>Browse Books
        </a>
        <a href="/scan2borrow/guest/borrowed" data-nav-path="/scan2borrow/guest/borrowed">
          <span class="nav-icon">&#128218;</span>Borrowed Books
        </a>
        <a href="/scan2borrow/guest/history" data-nav-path="/scan2borrow/guest/history">
          <span class="nav-icon">&#128220;</span>Borrowing History
        </a>
        <a href="/scan2borrow/guest/pass" data-nav-path="/scan2borrow/guest/pass">
          <span class="nav-icon">&#127903;</span>Government ID
        </a>
        ${this.logoutLink()}
      </nav>`;
  }

  logoutLink() {
    return `<a href="/scan2borrow/logout" class="nav-logout">
      <span class="nav-icon">&#9211;</span>Logout
    </a>`;
  }

  setActiveLink() {
    const currentPath = window.location.pathname.replace(/\/$/, "") || "/";

    this.root.querySelectorAll("[data-nav-path]").forEach((link) => {
      const isActive = link.dataset.navPath === currentPath;
      link.classList.toggle("active", isActive);
      if (isActive) link.setAttribute("aria-current", "page");
    });
  }
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-app-navbar]").forEach((root) => {
    new AppNavbar(root).start();
  });
});
