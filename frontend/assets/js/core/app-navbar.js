class AppNavbar {
  constructor(root, options = {}) {
    this.root = root;
    this.window = options.window || globalThis.window;
    this.document = options.document || root.ownerDocument || globalThis.document;
    this.roleHint = root.dataset.navbarRole || "session";
    this.renderedRole = "";
    this.toggle = null;
    this.backdrop = null;
    this.previousBodyOverflow = "";
    this.responsiveBound = false;
    this.handleKeydown = this.handleKeydown.bind(this);
    this.handleToggleClick = this.toggleDrawer.bind(this);
    this.handleBackdropClick = this.closeDrawer.bind(this);
    this.handleNavigationClick = this.handleNavigationClick.bind(this);
  }

  async start() {
    const cachedRole = this.roleHint === "session" ? this.cachedRole() : "";
    const initialRole = this.roleHint === "session"
      ? (this.roleMatchesCurrentPath(cachedRole) ? cachedRole : "")
      : this.roleHint;
    if (initialRole) {
      this.render(initialRole);
      this.setupResponsiveControls();
      this.setActiveLink();
      return;
    }

    const role = await this.resolveRole();
    this.cacheRole(role);
    this.render(role);
    this.setupResponsiveControls();
    this.setActiveLink();
  }

  async resolveRole() {
    if (this.roleHint === "guest") return "guest";

    try {
      const response = await this.window.fetch("/scan2borrow/api/auth/session", {
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
      return this.window.sessionStorage?.getItem("scan2borrow.nav.role") || "";
    } catch {
      return "";
    }
  }

  roleMatchesCurrentPath(role) {
    if (!role) return false;
    const path = this.window.location.pathname.replace(/\/$/, "");
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
      this.window.sessionStorage?.setItem("scan2borrow.nav.role", role);
    } catch {
      // Storage may be unavailable in private browsing or restricted frames.
    }
  }

  renderBorrower(role) {
    const teacher = role === "teacher";
    const settingsPath =
      teacher
        ? "/scan2borrow/teacher/settings"
        : "/scan2borrow/student/settings";
    const dashboardPath =
      teacher
        ? "/scan2borrow/teacher/dashboard"
        : "/scan2borrow/student/dashboard";
    const catalogPath = teacher
      ? "/scan2borrow/teacher/borrow"
      : "/scan2borrow/student/search";
    const historyPath = teacher
      ? "/scan2borrow/teacher/history"
      : "/scan2borrow/student/history";
    const catalogLabel = teacher ? "Borrow Books" : "Search Books";
    const historyLabel = teacher ? "Borrowing History" : "My History";

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
        <a href="${catalogPath}" data-nav-path="${catalogPath}">
          <span class="nav-icon">&#128269;</span>${catalogLabel}
        </a>
        <a href="${historyPath}" data-nav-path="${historyPath}">
          <span class="nav-icon">&#128220;</span>${historyLabel}
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
        <a href="/scan2borrow/staff/copy-history" data-nav-path="/scan2borrow/staff/copy-history">
          <span class="nav-icon" aria-hidden="true"></span>Copy History
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
    return `<a href="/scan2borrow/logout" class="nav-logout"
      data-confirm-action="logout"
      data-confirm-title="Log out?"
      data-confirm-message="Are you sure you want to log out?"
      data-confirm-label="Log out"
      data-confirm-class="btn-danger">
      <span class="nav-icon">&#9211;</span>Logout
    </a>`;
  }

  setActiveLink() {
    const currentPath = this.window.location.pathname.replace(/\/$/, "") || "/";

    this.root.querySelectorAll("[data-nav-path]").forEach((link) => {
      const isActive = link.dataset.navPath === currentPath;
      link.classList.toggle("active", isActive);
      if (isActive) link.setAttribute("aria-current", "page");
    });
  }

  setupResponsiveControls() {
    if (this.responsiveBound || !this.document?.createElement) return;

    this.root.id = this.root.id || "app-sidebar";
    const app = this.root.closest?.(".app");
    const topbar = app?.querySelector?.(".topbar") || this.document.querySelector?.(".topbar");
    if (!topbar) return;

    this.toggle = topbar.querySelector?.(".sidebar-toggle") || this.document.createElement("button");
    this.toggle.type = "button";
    this.toggle.className = "sidebar-toggle";
    this.toggle.classList.add?.("sidebar-toggle");
    this.toggle.setAttribute("aria-controls", this.root.id);
    this.toggle.setAttribute("aria-expanded", "false");
    this.toggle.setAttribute("aria-label", "Open navigation");
    this.toggle.innerHTML = '<span class="sidebar-toggle__bars" aria-hidden="true"></span>';
    if (!this.toggle.parentNode) topbar.insertBefore(this.toggle, topbar.firstChild || null);

    this.backdrop = app?.querySelector?.(".sidebar-backdrop") || this.document.createElement("button");
    this.backdrop.type = "button";
    this.backdrop.className = "sidebar-backdrop";
    this.backdrop.classList.add?.("sidebar-backdrop");
    this.backdrop.hidden = true;
    this.backdrop.setAttribute("aria-label", "Close navigation");
    if (!this.backdrop.parentNode) (app || this.document.body).appendChild(this.backdrop);

    this.toggle.addEventListener("click", this.handleToggleClick);
    this.backdrop.addEventListener("click", this.handleBackdropClick);
    this.root.addEventListener?.("click", this.handleNavigationClick);
    this.document.addEventListener("keydown", this.handleKeydown);
    this.responsiveBound = true;
  }

  openDrawer() {
    if (!this.toggle || !this.backdrop) return;
    this.previousBodyOverflow = this.document.body?.style.overflow || "";
    this.root.classList.add("is-open");
    this.backdrop.hidden = false;
    this.toggle.setAttribute("aria-expanded", "true");
    this.toggle.setAttribute("aria-label", "Close navigation");
    this.document.body?.classList.add("nav-drawer-open");
    if (this.document.body) this.document.body.style.overflow = "hidden";
  }

  closeDrawer() {
    if (!this.toggle || !this.backdrop) return;
    this.root.classList.remove("is-open");
    this.backdrop.hidden = true;
    this.toggle.setAttribute("aria-expanded", "false");
    this.toggle.setAttribute("aria-label", "Open navigation");
    this.document.body?.classList.remove("nav-drawer-open");
    if (this.document.body) this.document.body.style.overflow = this.previousBodyOverflow;
  }

  toggleDrawer() {
    if (this.root.classList.contains("is-open")) this.closeDrawer();
    else this.openDrawer();
  }

  handleKeydown(event) {
    if (event.key === "Escape" && this.root.classList.contains("is-open")) this.closeDrawer();
  }

  handleNavigationClick(event) {
    if (event.target.closest?.("[data-nav-path]")) this.closeDrawer();
  }

  destroy() {
    this.toggle?.removeEventListener("click", this.handleToggleClick);
    this.backdrop?.removeEventListener("click", this.handleBackdropClick);
    this.root.removeEventListener?.("click", this.handleNavigationClick);
    this.document?.removeEventListener?.("keydown", this.handleKeydown);
    this.closeDrawer();
    this.toggle = null;
    this.backdrop = null;
    this.responsiveBound = false;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-app-navbar]").forEach((root) => {
    new AppNavbar(root).start();
  });
});
