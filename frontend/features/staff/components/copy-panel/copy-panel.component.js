export class CopyPanelComponent {
  constructor({ modal, body, title, error, csrf = "", onChanged = () => {} }) {
    this.modal = modal;
    this.body = body;
    this.title = title;
    this.error = error;
    this.csrf = csrf;
    this.onChanged = onChanged;
    this.titleId = 0;
  }

  async open(titleId, titleName) {
    this.titleId = Number(titleId);
    this.title.textContent = titleName ? ` - ${titleName}` : "";
    this.showError("");
    this.body.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Loading copies...</td></tr>';
    bootstrap.Modal.getOrCreateInstance(this.modal).show();
    await this.load();
  }

  async load() {
    try {
      const response = await fetch(`/scan2borrow/api/book-copies?title_id=${encodeURIComponent(this.titleId)}`, { headers: { Accept: "application/json" } });
      const payload = await response.json();
      if (!response.ok || payload.ok === false) throw new Error(payload.message || payload.errors?.[0] || "Could not load copies.");
      this.render(payload.data || []);
    } catch (error) {
      this.showError(error.message);
      this.body.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No copies available.</td></tr>';
    }
  }

  render(copies) {
    if (!copies.length) {
      this.body.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No copies available.</td></tr>';
      return;
    }
    this.body.innerHTML = copies.map((copy) => {
      const archived = Boolean(copy.deleted_at);
      const disabled = archived ? " disabled" : "";
      const actionButton = archived
        ? '<button type="button" class="btn btn-outline-success btn-sm" data-copy-action="restore">Restore</button> <button type="button" class="btn btn-outline-danger btn-sm" data-copy-action="delete">Delete</button>'
        : '<button type="button" class="btn btn-outline-danger btn-sm" data-copy-action="archive">Archive</button>';
      return `<tr data-copy-id="${this.escape(copy.copy_id)}">
        <td><input class="form-control form-control-sm" data-copy-field="barcode" value="${this.escape(copy.barcode)}"${disabled}></td>
        <td><input class="form-control form-control-sm" data-copy-field="accession_no" value="${this.escape(copy.accession_no || "")}"${disabled}></td>
        <td><select class="form-select form-select-sm" data-copy-field="status"${disabled}><option value="Available"${copy.status === "Available" ? " selected" : ""}>Available</option><option value="Borrowed"${copy.status === "Borrowed" ? " selected" : ""}>Borrowed</option><option value="Reserved"${copy.status === "Reserved" ? " selected" : ""}>Reserved</option></select></td>
        <td><input class="form-control form-control-sm mb-1" data-copy-field="floor_no" placeholder="Floor" value="${this.escape(copy.floor_no || "")}"${disabled}><input class="form-control form-control-sm mb-1" data-copy-field="section_name" placeholder="Section" value="${this.escape(copy.section_name || "")}"${disabled}><input class="form-control form-control-sm mb-1" data-copy-field="shelf_no" placeholder="Shelf" value="${this.escape(copy.shelf_no || "")}"${disabled}><input class="form-control form-control-sm" data-copy-field="row_no" placeholder="Row" value="${this.escape(copy.row_no || "")}"${disabled}></td>
        <td class="small text-muted">${this.escape(copy.due_date || "-")}<br>${this.escape(copy.return_date || "-")}</td>
        <td class="text-nowrap"><button type="button" class="btn btn-primary btn-sm" data-copy-save${disabled}>Save</button> ${actionButton}</td>
      </tr>`;
    }).join("");
    this.body.querySelectorAll("[data-copy-save]").forEach((button) => button.addEventListener("click", () => this.save(button.closest("tr"))));
    this.body.querySelectorAll("[data-copy-action]").forEach((button) => button.addEventListener("click", () => this.action(button.closest("tr"), button.dataset.copyAction)));
  }

  async save(row) {
    try {
      await this.post("update", this.rowData(row));
      await this.load();
      this.onChanged();
    } catch (error) {
      this.showError(error.message);
    }
  }

  async action(row, action) {
    const execute = async () => {
      try {
        await this.post(action, { ids: [row.dataset.copyId] });
        await this.load();
        this.onChanged();
      } catch (error) {
        this.showError(error.message);
      }
    };
    if (action === "restore") return execute();
    return window.Scan2BorrowConfirmation.confirm({
      title: action === "delete" ? "Delete physical copy" : "Archive physical copy",
      message: action === "delete" ? "Permanently delete this archived copy?" : "Archive this physical copy?",
      confirmLabel: action === "delete" ? "Delete permanently" : "Archive",
      confirmClass: action === "delete" ? "btn-danger" : "btn-warning",
      onConfirm: execute,
    });
  }

  rowData(row) {
    const data = { copy_id: row.dataset.copyId };
    row.querySelectorAll("[data-copy-field]").forEach((field) => { data[field.dataset.copyField] = field.value; });
    return data;
  }

  async post(action, values) {
    const body = new URLSearchParams({ action, csrf: this.csrf });
    Object.entries(values).forEach(([key, value]) => {
      if (Array.isArray(value)) value.forEach((item) => body.append(`${key}[]`, item));
      else body.append(key, value == null ? "" : String(value));
    });
    const response = await fetch("/scan2borrow/api/book-copies", { method: "POST", body });
    const payload = await response.json();
    if (!response.ok || payload.ok === false) throw new Error(payload.message || payload.errors?.[0] || "Copy request failed.");
    return payload;
  }

  showError(message) {
    if (!this.error) return;
    this.error.textContent = message;
    this.error.classList.toggle("d-none", !message);
  }

  escape(value) {
    const node = document.createElement("span");
    node.textContent = value == null ? "" : String(value);
    return node.innerHTML;
  }
}
