(function () {
  "use strict";

  class IconSystem {
    constructor() {
      this.spriteId = "scan2borrow-icon-sprite";
      this.icons = {
        dashboard: '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>',
        books: '<path d="M5 5.5A2.5 2.5 0 0 1 7.5 3H19v16H7.5A2.5 2.5 0 0 0 5 21.5z"/><path d="M5 5.5v16M8 7h7M8 11h7"/>',
        users: '<path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20"/><circle cx="10" cy="7" r="3"/><path d="M17 11a3 3 0 1 0 0-6M16 15h1.5a3.5 3.5 0 0 1 3.5 3.5V20"/>',
        warning: '<path d="m12 4 8 15H4z"/><path d="M12 9v4M12 16.5h.01"/>',
        report: '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v5h4M9 12h6M9 16h6"/>',
        request: '<path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        staff: '<path d="M12 3 20 6v5c0 5-3.4 8.8-8 10-4.6-1.2-8-5-8-10V6z"/><circle cx="12" cy="10" r="2"/><path d="M8.5 16a3.5 3.5 0 0 1 7 0"/>',
        logout: '<path d="M10 5H5v14h5M14 8l4 4-4 4M18 12H9"/>',
        settings: '<path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"/><path d="m19 13 .1-1-.1-1 2-1.5-2-3.5-2.3.9a8 8 0 0 0-1.7-1L14.7 3h-4l-.3 2.9a8 8 0 0 0-1.7 1L6.4 6 4.4 9.5l2 1.5-.1 1 .1 1-2 1.5L6.4 18l2.3-.9a8 8 0 0 0 1.7 1l.3 2.9h4l.3-2.9a8 8 0 0 0 1.7-1l2.3.9 2-3.5z"/>',
        search: '<circle cx="10.8" cy="10.8" r="6.3"/><path d="m16 16 4.5 4.5"/>',
        history: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.5 2M3.5 8.5V4m0 0h4.5"/>',
        pass: '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M13 10h5M13 14h4"/>',
        plus: '<path d="M12 5v14M5 12h14"/>',
        filter: '<path d="M4 6h16M7 12h10M10 18h4"/>',
        download: '<path d="M12 4v11M8 11l4 4 4-4M5 20h14"/>',
        check: '<path d="m5 12 4 4L19 6"/>',
        borrow: '<path d="M5 5.5A2.5 2.5 0 0 1 7.5 3H19v16H7.5A2.5 2.5 0 0 0 5 21.5zM5 5.5v16M9 8h6"/>',
        return: '<path d="M19 7v5H7M11 17l-4-5 4-5M7 12h12"/>',
        login: '<path d="M14 5h5v14h-5M10 8l4 4-4 4M14 12H4"/>',
        trash: '<path d="M5 7h14M10 11v6M14 11v6M8 7l1-3h6l1 3M7 7l1 14h8l1-14"/>',
        key: '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8M16 7l2 2M14 9l2 2"/>',
      };
    }

    init() {
      this.mountSprite();
      this.replaceNavigationIcons();
      this.replaceBrandMarks();
      this.replaceStatIcons();
      this.replaceButtons();
      this.replaceBadges();
      this.observeDynamicContent();
    }

    mountSprite() {
      if (document.getElementById(this.spriteId)) return;
      const sprite = document.createElementNS("http://www.w3.org/2000/svg", "svg");
      sprite.id = this.spriteId;
      sprite.setAttribute("aria-hidden", "true");
      sprite.setAttribute("focusable", "false");
      sprite.style.cssText = "position:absolute;width:0;height:0;overflow:hidden";
      sprite.innerHTML = Object.entries(this.icons)
        .map(([name, content]) => `<symbol id="icon-${name}" viewBox="0 0 24 24">${content}</symbol>`)
        .join("");
      document.body.prepend(sprite);
    }

    createIcon(name, className = "ui-icon") {
      const icon = document.createElementNS("http://www.w3.org/2000/svg", "svg");
      className.split(/\s+/).filter(Boolean).forEach((name) => icon.classList.add(name));
      icon.setAttribute("aria-hidden", "true");
      icon.setAttribute("focusable", "false");
      icon.setAttribute("viewBox", "0 0 24 24");
      icon.innerHTML = `<use href="#icon-${name}"></use>`;
      return icon;
    }

    iconize(element, name, className = "ui-icon") {
      if (!element || element.dataset.iconized === "true" || !this.icons[name]) return;
      element.textContent = "";
      element.append(this.createIcon(name, className));
      element.dataset.iconized = "true";
    }

    replaceNavigationIcons() {
      document.querySelectorAll(".nav-icon").forEach((element) => {
        const href = element.closest("a")?.getAttribute("href") || "";
        let name = "dashboard";
        if (href.includes("logout")) name = "logout";
        else if (href.includes("settings") || href.includes("profile")) name = "settings";
        else if (href.includes("books") || href.includes("borrowed")) name = "books";
        else if (href.includes("search") || href.includes("browse")) name = "search";
        else if (href.includes("history")) name = "history";
        else if (href.includes("pass")) name = "pass";
        else if (href.includes("students") || href.includes("borrower")) name = "users";
        else if (href.includes("overdue")) name = "warning";
        else if (href.includes("reports")) name = "report";
        else if (href.includes("guest-requests")) name = "request";
        else if (href.includes("admin/staff")) name = "staff";
        this.iconize(element, name, "ui-icon ui-icon--nav");
      });
    }

    replaceBrandMarks() {
      document.querySelectorAll(".brand-mark, .auth-head .logo").forEach((element) => {
        this.iconize(element, element.classList.contains("logo") ? "books" : "books", "ui-icon ui-icon--brand");
      });
    }

    replaceStatIcons() {
      document.querySelectorAll(".stat-card .icon").forEach((element) => {
        const label = element.parentElement?.querySelector(".label")?.textContent.toLowerCase() || "";
        let name = "books";
        if (label.includes("borrower") || label.includes("student")) name = "users";
        else if (label.includes("overdue")) name = "warning";
        else if (label.includes("available")) name = "check";
        else if (label.includes("returned")) name = "return";
        else if (label.includes("fine") || label.includes("fee")) name = "key";
        this.iconize(element, name, "ui-icon ui-icon--stat");
      });
    }

    replaceButtons() {
      const actions = [
        [/add new book|add book/i, "plus"],
        [/generate report/i, "report"],
        [/export csv|download/i, "download"],
        [/filter/i, "filter"],
        [/search/i, "search"],
        [/borrow a book/i, "borrow"],
        [/return a book/i, "return"],
        [/login/i, "login"],
        [/promote/i, "staff"],
        [/reset password/i, "key"],
        [/archive|delete/i, "trash"],
        [/confirm|save|update|submit/i, "check"],
      ];
      document.querySelectorAll("button.btn, a.btn").forEach((element) => {
        if (element.dataset.iconized === "true") return;
        const match = actions.find(([pattern]) => pattern.test(element.textContent || ""));
        if (!match) return;
        element.textContent = (element.textContent || "").replace(/^[^\p{L}\p{N}]*/u, "").trim();
        element.prepend(this.createIcon(match[1], "ui-icon ui-icon--action"));
        element.dataset.iconized = "true";
      });
    }

    replaceBadges() {
      document.querySelectorAll(".badge").forEach((element) => {
        if (element.dataset.iconized === "true") return;
        const text = element.textContent || "";
        let name = null;
        if (/available/i.test(text)) name = "check";
        else if (/overdue|rejected/i.test(text)) name = "warning";
        else if (/borrowed|book/i.test(text)) name = "books";
        if (!name) return;
        element.textContent = text.replace(/^[^\p{L}\p{N}]*/u, "").trim();
        element.prepend(this.createIcon(name, "ui-icon ui-icon--badge"));
        element.dataset.iconized = "true";
      });
    }

    observeDynamicContent() {
      const observer = new MutationObserver(() => {
        this.replaceNavigationIcons();
        this.replaceBrandMarks();
        this.replaceStatIcons();
        this.replaceButtons();
        this.replaceBadges();
      });
      observer.observe(document.body, { childList: true, subtree: true });
    }
  }

  document.addEventListener("DOMContentLoaded", () => new IconSystem().init());
})();
