class GuestReceiptController {
  constructor() {
    this.host = document.getElementById("receipt-content");
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
  load() {
    const id = new URLSearchParams(window.location.search).get("id") || "";
    fetch("/scan2borrow/api/guest/receipt?id=" + encodeURIComponent(id), {
      headers: { "X-Requested-With": "fetch" },
    })
      .then((response) => response.json())
      .then((response) => {
        if (!response.ok) throw new Error("Receipt not found.");
        this.render(response.data || {});
      })
      .catch((error) => {
        this.host.innerHTML = `<div class="alert alert-danger">${this.escapeHtml(error.message)}</div>`;
      });
  }
  render(loan) {
    const row = (label, value) => `<tr><th>${label}</th><td>${value}</td></tr>`;
    this.host.innerHTML = `<div class="alert alert-${loan.request_status === "Returned" ? "success" : "info"} text-center">Status: <strong>${this.escapeHtml(loan.request_status)}</strong></div><table class="table table-sm">${row("Borrower", `${this.escapeHtml(loan.full_name)} (${this.escapeHtml(loan.visitor_number)})`)}${row("Book", `${this.escapeHtml(loan.title)}<br><span class="text-muted small">by ${this.escapeHtml(loan.author || "Unknown")}</span>`)}${row("Accession Number", this.escapeHtml(loan.accession_no || loan.barcode))}${row("ISBN", this.escapeHtml(loan.isbn || "—"))}${row("Location", `Floor ${this.escapeHtml(loan.floor_no)} · ${this.escapeHtml(loan.section_name)} · Shelf ${this.escapeHtml(loan.shelf_no)} · Row ${this.escapeHtml(loan.row_no)}`)}${row("Borrowed", this.escapeHtml(loan.borrowed_display))}${row("Due Date", this.escapeHtml(loan.due_display))}${row("Validity of the Book", this.escapeHtml(loan.validity_display))}</table>`;
  }
}
window.addEventListener("DOMContentLoaded", () => new GuestReceiptController());
