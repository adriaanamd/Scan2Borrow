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
    this.notificationRoot = null;
    this.notificationToggle = null;
    this.notificationPanel = null;
    this.notificationBadge = null;
    this.notificationList = null;
    this.notificationPath = "";
    this.notificationCsrf = "";
    this.handleNotificationToggle = null;
    this.handleNotificationClick = null;
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
      this.setupBorrowerNotifications(initialRole);
      this.setActiveLink();
      return;
    }

    const role = await this.resolveRole();
    this.cacheRole(role);
    this.render(role);
    this.setupResponsiveControls();
    this.setupBorrowerNotifications(role);
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
    if (path.includes("/student/activity")) return role === "student";
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

  setupBorrowerNotifications(role) {
    if (!["student", "teacher"].includes(role) || this.notificationRoot) return;

    const app = this.root.closest?.(".app");
    const topbar = app?.querySelector?.(".topbar") || this.document?.querySelector?.(".topbar");
    if (!topbar || !this.document?.createElement) return;

    this.notificationPath = "/scan2borrow/api/" + role + "/notifications";
    this.notificationCsrf = this.document.querySelector?.('meta[name="csrf"]')?.content || "";
    const wrapper = this.document.createElement("div");
    wrapper.className = "borrower-notifications";
    wrapper.innerHTML = [
      '<button type="button" class="borrower-notifications__toggle" aria-expanded="false" aria-controls="borrower-notifications-panel">',
      '<span aria-hidden="true">&#128276;</span><span class="borrower-notifications__label">Notifications</span>',
      '<span class="borrower-notifications__badge" hidden>0</span></button>',
      '<section id="borrower-notifications-panel" class="borrower-notifications__panel" hidden aria-label="Notifications">',
      '<div class="borrower-notifications__header"><strong>Notifications</strong>',
      '<button type="button" data-notification-action="read-all">Mark all as read</button></div>',
      '<div class="borrower-notifications__list"><p class="borrower-notifications__empty">No notifications yet.</p></div></section>',
    ].join("");
    topbar.appendChild(wrapper);
    this.notificationRoot = wrapper;
    this.notificationToggle = wrapper.querySelector?.(".borrower-notifications__toggle") || null;
    this.notificationPanel = wrapper.querySelector?.(".borrower-notifications__panel") || null;
    this.notificationBadge = wrapper.querySelector?.(".borrower-notifications__badge") || null;
    this.notificationList = wrapper.querySelector?.(".borrower-notifications__list") || null;
    if (!this.notificationToggle || !this.notificationPanel || !this.notificationList) return;

    this.handleNotificationToggle = () => this.toggleNotifications();
    this.handleNotificationClick = (event) => this.handleNotificationAction(event);
    this.notificationToggle.addEventListener("click", this.handleNotificationToggle);
    wrapper.addEventListener("click", this.handleNotificationClick);
  }

  toggleNotifications() {
    if (this.notificationPanel?.hidden) {
      this.openNotifications();
      return;
    }

    this.closeNotifications();
  }

  async openNotifications() {
    if (!this.notificationPanel) return;
    this.notificationPanel.hidden = false;
    this.notificationToggle?.setAttribute("aria-expanded", "true");
    await this.loadNotifications();
  }

  closeNotifications() {
    if (!this.notificationPanel) return;
    this.notificationPanel.hidden = true;
    this.notificationToggle?.setAttribute("aria-expanded", "false");
  }

  async loadNotifications() {
    if (!this.notificationPath || !this.notificationList) return;

    try {
      const response = await this.window.fetch(this.notificationPath, {
        headers: { Accept: "application/json", "X-Requested-With": "fetch" },
      });
      const payload = await response.json();
      this.renderNotifications(payload.ok === true ? payload.data : { notifications: [], unread_count: 0 });
    } catch {
      this.renderNotifications({ notifications: [], unread_count: 0, error: true });
    }
  }

  renderNotifications(data = {}) {
    const notifications = Array.isArray(data.notifications) ? data.notifications : [];
    const unreadCount = Number(data.unread_count) || 0;
    if (this.notificationBadge) {
      this.notificationBadge.textContent = String(unreadCount);
      this.notificationBadge.hidden = unreadCount < 1;
    }
    if (!this.notificationList) return;

    if (typeof this.notificationList.replaceChildren === "function") {
      this.notificationList.replaceChildren();
    } else {
      this.notificationList.innerHTML = "";
    }

    if (notifications.length === 0) {
      const empty = this.document.createElement("p");
      empty.className = "borrower-notifications__empty";
      empty.textContent = data.error ? "Notifications are temporarily unavailable." : "No notifications yet.";
      this.notificationList.appendChild(empty);
      return;
    }

    notifications.forEach((notification) => {
      const row = this.document.createElement("button");
      row.type = "button";
      row.className = "borrower-notification";
      row.dataset.notificationId = String(notification.id || "");
      row.classList.toggle("is-unread", Number(notification.is_read) !== 1);

      const title = this.document.createElement("span");
      title.className = "borrower-notification__title";
      title.textContent = String(notification.title || "Notification");
      const message = this.document.createElement("span");
      message.className = "borrower-notification__message";
      message.textContent = String(notification.message || "");
      const time = this.document.createElement("span");
      time.className = "borrower-notification__time";
      time.textContent = String(notification.created_at || "");
      row.append(title, message, time);
      this.notificationList.appendChild(row);
    });
  }

  handleNotificationAction(event) {
    const target = event.target?.closest?.("[data-notification-action]");
    if (target?.dataset.notificationAction === "read-all") {
      this.markAllNotificationsRead();
      return;
    }
    const row = event.target?.closest?.("[data-notification-id]");
    const notificationId = row?.dataset.notificationId || "";
    if (notificationId !== "") this.markNotificationRead(notificationId);
  }

  async postNotificationAction(action, notificationId = "") {
    const values = { csrf: this.notificationCsrf, action };
    if (notificationId !== "") values.notification_id = String(notificationId);
    await this.window.fetch(this.notificationPath, {
      method: "POST",
      headers: { Accept: "application/json", "X-Requested-With": "fetch" },
      body: new URLSearchParams(values),
    });
    await this.loadNotifications();
  }

  async markNotificationRead(notificationId) {
    await this.postNotificationAction("read", notificationId);
  }

  async markAllNotificationsRead() {
    await this.postNotificationAction("read_all");
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
    const activityPath = teacher
      ? "/scan2borrow/teacher/activity"
      : "/scan2borrow/student/activity";

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
        <a href="${activityPath}" data-nav-path="${activityPath}">
          <span class="nav-icon" aria-hidden="true"></span>Activity Logs
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
        <a href="/scan2borrow/staff/circulation" data-nav-path="/scan2borrow/staff/circulation">
          <span class="nav-icon">&#128179;</span>Circulation
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
    this.notificationToggle?.removeEventListener("click", this.handleNotificationToggle);
    this.notificationRoot?.removeEventListener?.("click", this.handleNotificationClick);
    this.notificationRoot?.remove?.();
    this.root.removeEventListener?.("click", this.handleNavigationClick);
    this.document?.removeEventListener?.("keydown", this.handleKeydown);
    this.closeDrawer();
    this.toggle = null;
    this.backdrop = null;
    this.notificationRoot = null;
    this.notificationToggle = null;
    this.notificationPanel = null;
    this.notificationBadge = null;
    this.notificationList = null;
    this.notificationPath = "";
    this.responsiveBound = false;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-app-navbar]").forEach((root) => {
    new AppNavbar(root).start();
  });
});
