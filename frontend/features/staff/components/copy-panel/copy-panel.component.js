export class CopyPanelComponent {
  constructor({ modal, body, title, error, notice = null, csrf = "", exportButton = null, summary = null, history = null, onChanged = () => {} }) {
    this.modal = modal;
    this.body = body;
    this.title = title;
    this.error = error;
    this.notice = notice;
    this.csrf = csrf;
    this.exportButton = exportButton;
    this.summary = summary;
    this.history = history;
    this.onChanged = onChanged;
    this.titleId = 0;
    this.unprintedCount = 0;
    this.exportButton?.addEventListener("click", () => this.exportUnprinted());
  }

  async open(titleId, titleName) {
    this.titleId = Number(titleId);
    this.title.textContent = titleName ? ` - ${titleName}` : "";
    this.showError("");
    this.showNotice("");
    this.body.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Loading copies...</td></tr>';
    bootstrap.Modal.getOrCreateInstance(this.modal).show();
    await this.load();
  }

  async load() {
    try {
      const response = await fetch(`/scan2borrow/api/book-copies?title_id=${encodeURIComponent(this.titleId)}`, { headers: { Accept: "application/json" } });
      const payload = await response.json();
      if (!response.ok || payload.ok === false) throw new Error(payload.message || payload.errors?.[0] || "Could not load copies.");
      const copies = payload.data || [];
      this.render(copies);
      await this.loadHistory();
    } catch (error) {
      this.showError(error.message);
      this.body.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No copies available.</td></tr>';
    }
  }

  render(copies) {
    const unprinted = copies.filter((copy) => !copy.deleted_at && !copy.printed_at).length;
    this.unprintedCount = unprinted;
    if (this.summary) this.summary.textContent = `${unprinted} barcode${unprinted === 1 ? "" : "s"} ready to export · printed markers cannot be reversed.`;
    if (this.exportButton) this.exportButton.disabled = unprinted === 0;
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
      const historyUrl = `/scan2borrow/staff/copy-history?barcode=${encodeURIComponent(copy.barcode || "")}`;
      return `<tr data-copy-id="${this.escape(copy.copy_id)}">
        <td><input class="form-control form-control-sm" data-copy-field="barcode" value="${this.escape(copy.barcode)}"${disabled}><div class="mt-1">${copy.printed_at ? '<span class="badge text-bg-secondary">Exported</span>' : '<span class="badge text-bg-warning">Not exported</span>'}</div></td>
        <td><input class="form-control form-control-sm" data-copy-field="accession_no" value="${this.escape(copy.accession_no || "")}"${disabled}></td>
        <td><select class="form-select form-select-sm" data-copy-field="status"${disabled}><option value="Available"${copy.status === "Available" ? " selected" : ""}>Available</option><option value="Borrowed"${copy.status === "Borrowed" ? " selected" : ""}>Borrowed</option><option value="Reserved"${copy.status === "Reserved" ? " selected" : ""}>Reserved</option><option value="Lost"${copy.status === "Lost" ? " selected" : ""}>Lost</option><option value="Damaged"${copy.status === "Damaged" ? " selected" : ""}>Damaged</option></select><input class="form-control form-control-sm mt-1" data-copy-field="reason" placeholder="Reason when status changes" value=""${disabled}></td>
        <td><input class="form-control form-control-sm mb-1" data-copy-field="floor_no" placeholder="Floor" value="${this.escape(copy.floor_no || "")}"${disabled}><input class="form-control form-control-sm mb-1" data-copy-field="section_name" placeholder="Section" value="${this.escape(copy.section_name || "")}"${disabled}><input class="form-control form-control-sm mb-1" data-copy-field="shelf_no" placeholder="Shelf" value="${this.escape(copy.shelf_no || "")}"${disabled}><input class="form-control form-control-sm" data-copy-field="row_no" placeholder="Row" value="${this.escape(copy.row_no || "")}"${disabled}></td>
        <td class="small text-muted">${this.escape(copy.due_date || "-")}<br>${this.escape(copy.return_date || "-")}</td>
        <td class="text-nowrap"><button type="button" class="btn btn-primary btn-sm" data-copy-save${disabled}>Save</button> <a class="btn btn-outline-primary btn-sm" href="${this.escape(historyUrl)}">View history</a> ${actionButton}</td>
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

  async loadHistory() {
    if (!this.history) return;
    try {
      const response = await fetch(`/scan2borrow/api/barcode-print-batches?title_id=${encodeURIComponent(this.titleId)}`, { headers: { Accept: "application/json" } });
      const payload = await response.json();
      if (!response.ok || payload.ok === false) throw new Error(payload.message || payload.errors?.[0] || "Could not load export history.");
      const batches = payload.data?.history || [];
      this.history.innerHTML = batches.length
        ? batches.map((batch) => `<li class="list-group-item d-flex justify-content-between align-items-center"><span><strong>${this.escape(batch.label_count)} labels</strong><small class="d-block text-muted">${this.escape(batch.created_at || "")}</small></span><button type="button" class="btn btn-outline-primary btn-sm" data-print-batch="${this.escape(batch.batch_token)}">Export PDF again</button></li>`).join("")
        : '<li class="list-group-item text-muted">No previous barcode exports.</li>';
      this.history.querySelectorAll("[data-print-batch]").forEach((button) => button.addEventListener("click", () => this.openPrintPage(button.dataset.printBatch)));
    } catch (error) {
      this.history.innerHTML = '<li class="list-group-item text-muted">Export history unavailable.</li>';
    }
  }

  async exportUnprinted() {
    if (!this.titleId || !this.exportButton) return;
    this.exportButton.disabled = true;
    this.exportButton.textContent = "Preparing export...";
    const printWindow = window.open("", "_blank");
    try {
      const body = new URLSearchParams({ title_id: String(this.titleId), csrf: this.csrf });
      const response = await fetch("/scan2borrow/api/barcode-print-batches", { method: "POST", body });
      const payload = await response.json();
      if (!response.ok || payload.ok === false) throw new Error(payload.message || payload.errors?.[0] || "Could not prepare barcode export.");
      const batch = payload.data?.batch;
      if (batch?.batch_token) {
        this.showNotice(`${batch.label_count} barcode label${batch.label_count === 1 ? "" : "s"} prepared. Export generation is now marked printed.`);
        this.openPrintPage(batch.batch_token, printWindow);
      } else {
        if (printWindow) printWindow.close();
        this.showNotice(payload.message || "All active barcodes for this title were already exported.");
      }
      await this.load();
      this.onChanged();
    } catch (error) {
      if (printWindow) printWindow.close();
      this.showError(error.message);
    } finally {
      this.exportButton.disabled = this.unprintedCount === 0;
      this.exportButton.textContent = "Export unprinted barcodes";
    }
  }

  openPrintPage(token, printWindow = null) {
    const target = printWindow || window.open("", "_blank");
    if (!target) {
      this.showError("Your browser blocked the print page. Allow pop-ups and try again.");
      return;
    }
    target.location = `/scan2borrow/staff/barcodes/print?batch_token=${encodeURIComponent(token)}`;
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

  showNotice(message) {
    if (!this.notice) return;
    this.notice.textContent = message;
    this.notice.classList.toggle("d-none", !message);
  }

  escape(value) {
    const node = document.createElement("span");
    node.textContent = value == null ? "" : String(value);
    return node.innerHTML;
  }
}
