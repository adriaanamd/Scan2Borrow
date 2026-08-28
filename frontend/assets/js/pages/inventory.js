class InventoryPageController {
  constructor() {
    this.api = "/scan2borrow/api/books";
    const csrfMeta = document.querySelector('meta[name="csrf"]');
    this.csrf = csrfMeta ? csrfMeta.content : "";
    this.state = {
      search: "",
      status: "",
      archived: false,
      sort: "created_at",
      dir: "desc",
      page: 1,
      per_page: 10,
      selected: new Set(),
    };
    this.$ = (id) => document.getElementById(id);
    this.tbody = this.$("inv-body");
    this.searchInput = this.$("inv-search");
    this.statusFilter = this.$("inv-status");
    this.viewToggle = this.$("inv-view");
    this.pager = this.$("inv-pager");
    this.countLabel = this.$("inv-count");
    this.selectAll = this.$("inv-select-all");
    this.bulkBar = this.$("inv-bulkbar");
    this.bulkCount = this.$("inv-bulkcount");
    this.offcanvasEl = this.$("bookDrawer");
    this.drawer = new bootstrap.Offcanvas(this.offcanvasEl);
    this.form = this.$("book-form");
    this.coverFileInput = this.$("cover-file");
    this.coverPreview = this.$("cover-preview");
    this.coverPreviewWrap = this.$("cover-preview-wrap");
    this.coverObjectUrl = null;
    this.searchTimer = null;
    this.bindEvents();
    this.load();
  }

  escapeHtml(value) {
    return String(value == null ? "" : value).replace(
      /[&<>"']/g,
      (character) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[character],
    );
  }

  toast(message, ok) {
    const host = this.$("toast-host");
    const element = document.createElement("div");
    element.className =
      "toast align-items-center text-white border-0 show mb-2 bg-" +
      (ok ? "success" : "danger");
    element.role = "alert";
    element.innerHTML =
      '<div class="d-flex"><div class="toast-body">' +
      this.escapeHtml(message) +
      '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
    host.appendChild(element);
    const toast = new bootstrap.Toast(element, { delay: 3500 });
    toast.show();
    element.addEventListener("hidden.bs.toast", () => element.remove());
  }

  resolveCoverUrl(value) {
    if (!value) return "";
    if (/^(https?:)?\/\//i.test(value) || value.indexOf("data:image/") === 0)
      return value;
    try {
      return new URL(value, window.location.href).toString();
    } catch (error) {
      return value;
    }
  }

  showCoverPreview(url) {
    if (!this.coverPreview || !this.coverPreviewWrap) return;
    if (this.coverObjectUrl) {
      URL.revokeObjectURL(this.coverObjectUrl);
      this.coverObjectUrl = null;
    }
    if (url) {
      this.coverPreview.src = url;
      this.coverPreviewWrap.style.display = "block";
    } else {
      this.coverPreview.src = "";
      this.coverPreviewWrap.style.display = "none";
    }
  }

  previewSelectedCover(input) {
    if (!input || !input.files || !input.files[0]) {
      this.showCoverPreview("");
      return;
    }
    const file = input.files[0];
    if (!file.type || !file.type.startsWith("image/")) {
      this.showCoverPreview("");
      return;
    }
    if (this.coverObjectUrl) URL.revokeObjectURL(this.coverObjectUrl);
    this.coverObjectUrl = URL.createObjectURL(file);
    this.showCoverPreview(this.coverObjectUrl);
  }

  badge(status) {
    const map = {
      Available: "success",
      Borrowed: "danger",
      Reserved: "warning text-dark",
    };
    return (
      '<span class="badge bg-' +
      (map[status] || "secondary") +
      '">' +
      this.escapeHtml(status) +
      "</span>"
    );
  }

  apiGet(params) {
    const query = new URLSearchParams(params).toString();
    return fetch(this.api + "?" + query, {
      headers: { "X-Requested-With": "fetch" },
    }).then((response) => response.json());
  }

  apiPost(action, data) {
    const body = new FormData();
    body.append("action", action);
    body.append("csrf", this.csrf);
    Object.keys(data).forEach((key) => {
      if (Array.isArray(data[key]))
        data[key].forEach((value) => body.append(key + "[]", value));
      else body.append(key, data[key]);
    });
    return fetch(this.api, { method: "POST", body }).then((response) =>
      response.json(),
    );
  }

  load() {
    this.apiGet({
      action: "list",
      search: this.state.search,
      status: this.state.status,
      archived: this.state.archived ? 1 : 0,
      sort: this.state.sort,
      dir: this.state.dir,
      page: this.state.page,
      per_page: this.state.per_page,
    })
      .then((response) => this.render(response))
      .catch((error) => {
        console.error("Load error:", error);
        this.toast(
          "Failed to load inventory. Check console for details.",
          false,
        );
      });
  }

  render(response) {
    if (!response.ok) {
      this.toast(response.message || "Error", false);
      return;
    }
    this.state.selected.clear();
    this.updateBulkBar();
    this.selectAll.checked = false;
    this.tbody.innerHTML = "";
    if (!response.data.length)
      this.tbody.innerHTML =
        '<tr><td colspan="10" class="text-center text-muted py-4">No books found.</td></tr>';
    response.data.forEach((book) => {
      const row = document.createElement("tr");
      const actions = this.state.archived
        ? '<button class="btn btn-success btn-sm" data-act="restore" data-id="' +
          book.id +
          '">Restore</button> ' +
          '<button class="btn btn-outline-danger btn-sm" data-act="delete" data-id="' +
          book.id +
          '">Delete</button>'
        : '<button class="btn btn-outline-primary btn-sm" data-act="edit" data-id="' +
          book.id +
          '">Edit</button> ' +
          '<button class="btn btn-outline-warning btn-sm" data-act="archive" data-id="' +
          book.id +
          '">Archive</button>';
      const cell = (content, className, style) => {
        const element = document.createElement("td");
        if (className) element.className = className;
        if (style) element.style.cssText = style;
        element.innerHTML = content;
        return element;
      };
      row.appendChild(
        cell(
          '<input type="checkbox" class="form-check-input row-check" value="' +
            book.id +
            '">',
          "",
          "width:38px;",
        ),
      );
      row.appendChild(
        cell(this.escapeHtml(book.barcode || ""), "", "min-width:110px;"),
      );
      row.appendChild(
        cell(
          "<strong>" +
            this.escapeHtml(book.title || "") +
            "</strong>" +
            (book.isbn
              ? '<br><span class="text-muted small">ISBN ' +
                this.escapeHtml(book.isbn) +
                "</span>"
              : ""),
          "",
          "min-width:220px;",
        ),
      );
      row.appendChild(
        cell(this.escapeHtml(book.author || ""), "", "min-width:160px;"),
      );
      row.appendChild(
        cell(
          book.publisher
            ? this.escapeHtml(book.publisher)
            : '<span class="text-muted">&mdash;</span>',
          "",
          "min-width:140px;",
        ),
      );
      row.appendChild(
        cell(
          this.escapeHtml(book.description || "No description available"),
          "text-muted small",
          "min-width:220px;",
        ),
      );
      row.appendChild(
        cell(
          book.category_name
            ? this.escapeHtml(book.category_name)
            : '<span class="text-muted">&mdash;</span>',
          "",
          "min-width:120px;",
        ),
      );
      row.appendChild(cell(this.badge(book.status), "", "min-width:110px;"));
      row.appendChild(
        cell(
          (book.due_date
            ? "&#128197; Due " + this.escapeHtml(book.due_date)
            : '<span class="text-muted">&mdash;</span>') +
            (book.return_date
              ? "<br>&#8617;&#65039; Ret " + this.escapeHtml(book.return_date)
              : ""),
          "text-muted small",
          "min-width:140px;",
        ),
      );
      row.appendChild(
        cell(
          "&#128205; " +
            this.escapeHtml(book.floor_no || "") +
            (book.section_name
              ? " · " + this.escapeHtml(book.section_name)
              : "") +
            (book.shelf_no
              ? " · Shelf " + this.escapeHtml(book.shelf_no)
              : "") +
            (book.row_no ? " · Row " + this.escapeHtml(book.row_no) : ""),
          "text-muted small",
          "min-width:180px;",
        ),
      );
      row.appendChild(cell(actions, "text-nowrap", "min-width:120px;"));
      row.dataset.book = JSON.stringify(book);
      this.tbody.appendChild(row);
    });
    this.countLabel.textContent = response.total + " book(s)";
    this.renderPager(response.page, response.pages);
    this.renderSortIndicators();
  }

  renderPager(page, pages) {
    this.pager.innerHTML = "";
    if (pages <= 1) return;
    const item = (label, target, disabled, active) => {
      const element = document.createElement("li");
      element.className =
        "page-item" + (disabled ? " disabled" : "") + (active ? " active" : "");
      element.innerHTML = '<a class="page-link" href="#">' + label + "</a>";
      if (!disabled && !active)
        element.addEventListener("click", (event) => {
          event.preventDefault();
          this.state.page = target;
          this.load();
        });
      this.pager.appendChild(element);
    };
    item("&laquo;", page - 1, page <= 1, false);
    for (let index = 1; index <= pages; index++)
      item(index, index, false, index === page);
    item("&raquo;", page + 1, page >= pages, false);
  }

  renderSortIndicators() {
    document.querySelectorAll("th[data-sort]").forEach((header) => {
      const arrow = header.querySelector(".sort-arrow");
      if (arrow)
        arrow.textContent =
          header.dataset.sort === this.state.sort
            ? this.state.dir === "asc"
              ? " \u25B2"
              : " \u25BC"
            : "";
    });
  }

  updateBulkBar() {
    const count = this.state.selected.size;
    this.bulkCount.textContent = count;
    this.bulkBar.style.display = count ? "flex" : "none";
    document.querySelectorAll("[data-bulk]").forEach((button) => {
      const action = button.getAttribute("data-bulk");
      const showInArchived = action === "restore" || action === "delete";
      button.style.display =
        this.state.archived === showInArchived ? "" : "none";
    });
  }

  doAction(action, ids, confirmMessage) {
    if (confirmMessage && !window.confirm(confirmMessage)) return;
    this.apiPost(action, { ids })
      .then((response) => {
        this.toast(response.message, response.ok);
        if (response.ok) this.load();
      })
      .catch(() => this.toast("Request failed.", false));
  }

  openDrawer(book) {
    this.form.reset();
    this.showCoverPreview("");
    this.$("book-id").value = book ? book.id : "";
    this.$("drawer-title").textContent = book ? "Edit Book" : "Add New Book";
    if (book) {
      [
        "barcode",
        "isbn",
        "title",
        "author",
        "publisher",
        "description",
        "category_name",
        "keywords",
        "floor_no",
        "section_name",
        "shelf_no",
        "row_no",
        "due_date",
        "return_date",
        "status",
      ].forEach((field) => {
        if (this.form.elements[field])
          this.form.elements[field].value = book[field] || "";
      });
      if (book.cover_file || book.cover_image)
        this.showCoverPreview(
          this.resolveCoverUrl(book.cover_file || book.cover_image),
        );
    }
    this.drawer.show();
  }

  bindEvents() {
    if (this.coverFileInput)
      this.coverFileInput.addEventListener("change", () =>
        this.previewSelectedCover(this.coverFileInput),
      );
    this.searchInput.addEventListener("input", () => {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => {
        this.state.search = this.searchInput.value.trim();
        this.state.page = 1;
        this.load();
      }, 300);
    });
    this.statusFilter.addEventListener("change", () => {
      this.state.status = this.statusFilter.value;
      this.state.page = 1;
      this.load();
    });
    this.viewToggle.addEventListener("change", () => {
      this.state.archived = this.viewToggle.checked;
      this.state.page = 1;
      this.load();
    });
    document.querySelectorAll("th[data-sort]").forEach((header) => {
      header.style.cursor = "pointer";
      header.addEventListener("click", () => {
        const column = header.dataset.sort;
        if (this.state.sort === column)
          this.state.dir = this.state.dir === "asc" ? "desc" : "asc";
        else {
          this.state.sort = column;
          this.state.dir = "asc";
        }
        this.load();
      });
    });
    this.selectAll.addEventListener("change", () => {
      document.querySelectorAll(".row-check").forEach((checkbox) => {
        checkbox.checked = this.selectAll.checked;
        if (checkbox.checked) this.state.selected.add(checkbox.value);
        else this.state.selected.delete(checkbox.value);
      });
      this.updateBulkBar();
    });
    this.tbody.addEventListener("change", (event) => {
      if (event.target.classList.contains("row-check")) {
        if (event.target.checked) this.state.selected.add(event.target.value);
        else this.state.selected.delete(event.target.value);
        this.updateBulkBar();
      }
    });
    this.tbody.addEventListener("click", (event) => {
      const button = event.target.closest("button[data-act]");
      if (!button) return;
      const id = button.dataset.id;
      const action = button.dataset.act;
      if (action === "edit")
        this.openDrawer(JSON.parse(button.closest("tr").dataset.book));
      else if (action === "archive")
        this.doAction("archive", [id], "Archive this book?");
      else if (action === "restore") this.doAction("restore", [id]);
      else if (action === "delete")
        this.doAction(
          "delete",
          [id],
          "Permanently delete this archived book? This cannot be undone.",
        );
    });
    document.querySelectorAll("[data-bulk]").forEach((button) =>
      button.addEventListener("click", () => {
        const ids = Array.from(this.state.selected);
        if (!ids.length) return;
        const action = button.getAttribute("data-bulk");
        const message =
          action === "delete"
            ? "Permanently delete " + ids.length + " book(s)?"
            : action === "archive"
              ? "Archive " + ids.length + " book(s)?"
              : null;
        this.doAction(action, ids, message);
      }),
    );
    this.$("btn-add").addEventListener("click", () => this.openDrawer(null));
    this.form.addEventListener("submit", (event) => this.submitForm(event));
  }

  submitForm(event) {
    event.preventDefault();
    const id = this.$("book-id").value;
    const data = new FormData(this.form);
    data.append("action", id ? "update" : "create");
    data.append("csrf", this.csrf);
    if (id) data.append("id", id);
    fetch(this.api, { method: "POST", body: data })
      .then((response) =>
        response.text().then((text) => {
          let payload = null;
          try {
            payload = text ? JSON.parse(text) : null;
          } catch (error) {
            payload = null;
          }
          if (!response.ok)
            throw new Error(
              (payload && payload.message) || text || "Save failed.",
            );
          if (!payload || typeof payload !== "object")
            throw new Error(text || "Save failed.");
          return payload;
        }),
      )
      .then((response) => {
        this.toast(response.message, response.ok);
        if (response.ok) {
          this.drawer.hide();
          this.load();
        }
      })
      .catch((error) => this.toast(error.message || "Save failed.", false));
  }
}

document.addEventListener("DOMContentLoaded", () => {
  if (document.getElementById("bookDrawer")) new InventoryPageController();
});
