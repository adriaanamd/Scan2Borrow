export class StudentReceiptPage {
  constructor({ service } = {}) {
    this.host = document.getElementById("receipt-content");
    this.service = service;
    this.load();
  }

  escapeHtml(value) {
    return String(value == null ? "" : value).replace(
      /[&<>"']/g,
      (character) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[character]),
    );
  }

  load() {
    const code = new URLSearchParams(window.location.search).get("code") || "";
    const request = this.service
      ? this.service.load(code)
      : fetch("/scan2borrow/api/receipt?code=" + encodeURIComponent(code), { headers: { "X-Requested-With": "fetch" } }).then((response) => response.json());
    request
      .then((response) => {
        if (response.ok === false) throw new Error(response.message);
        this.render(response.data || {});
      })
      .catch((error) => {
        this.host.innerHTML = `<div class="alert alert-danger">${this.escapeHtml(error.message || "Receipt not found. Invalid transaction code.")}</div>`;
      });
  }

  render(receipt) {
    const row = (label, value) => `<tr><th>${label}</th><td>${value}</td></tr>`;
    if (Array.isArray(receipt.books) && receipt.books.length) {
      const quantity = receipt.books.reduce((total, book) => total + Number(book.quantity || 1), 0);
      const books = receipt.books.map((book) => `${this.escapeHtml(book.title || "")}<br><span class="text-muted small">by ${this.escapeHtml(book.author || "")} · ${Number(book.quantity || 1)} copy/copies</span>`).join("<hr class=\"my-2\">");
      this.host.innerHTML = `<div class="alert alert-info text-center">Bulk borrowing receipt</div><table class="table table-sm">${row("Transaction", `<code>${this.escapeHtml(receipt.transaction_code)}</code>`)}${row("Books", books)}${row("Quantity", String(quantity))}</table>`;
      return;
    }

    const status = this.escapeHtml(receipt.status || "");
    const type = receipt.status === "Returned" ? "success" : receipt.status === "Overdue" ? "danger" : "info";
    this.host.innerHTML = `<div class="alert alert-${type} text-center">Status: <strong>${status}</strong></div><table class="table table-sm">${row("Transaction", `<code>${this.escapeHtml(receipt.transaction_code)}</code>`)}${row("Borrower", `${this.escapeHtml(receipt.full_name)} (${this.escapeHtml(receipt.id_barcode)})`)}${row("Book", `${this.escapeHtml(receipt.title)}<br><span class="text-muted small">by ${this.escapeHtml(receipt.author)}</span>`)}${row("Quantity", String(receipt.quantity || 1))}${row("Accession Number", this.escapeHtml(receipt.accession_no || receipt.book_barcode))}${row("ISBN", this.escapeHtml(receipt.isbn || "—"))}${row("Location", `Floor ${this.escapeHtml(receipt.floor_no)} · ${this.escapeHtml(receipt.section_name)} · Shelf ${this.escapeHtml(receipt.shelf_no)} · Row ${this.escapeHtml(receipt.row_no)}`)}${row("Borrowed", this.escapeHtml(receipt.borrowed_display || ""))}${row("Due Date", this.escapeHtml(receipt.due_display || ""))}${row("Validity of the Book", this.escapeHtml(receipt.validity_display || ""))}</table>`;
  }
}

if (typeof window !== "undefined") {
  window.addEventListener("DOMContentLoaded", () => new StudentReceiptPage());
}
