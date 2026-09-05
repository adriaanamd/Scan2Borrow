export class GuestBorrowedPage {
  constructor() {
    this.host = document.getElementById("borrowed-books");
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
    fetch("/scan2borrow/api/guest/borrowed", {
      headers: { "X-Requested-With": "fetch" },
    })
      .then((response) => response.json())
      .then((response) => {
        if (response.ok) this.render(response.data?.books || []);
      });
  }
  render(books) {
    if (!books.length) return;
    this.host.innerHTML = `<div class="row g-3">${books
      .map((book) => {
        const cover = Scan2BorrowMedia.resolve(book.cover_file || "");
        const status = book.status_label || book.request_status || "Released";
        const badge = status === "Return Verification Pending" ? "warning text-dark" : "primary";
        return `<div class="col-md-6"><div class="card h-100 shadow-sm"><div class="card-body d-flex gap-3">${cover ? `<img src="${this.escapeHtml(cover)}" style="width:72px;height:102px;object-fit:cover" class="rounded" alt="Book cover">` : '<div class="bg-success text-white rounded d-flex align-items-center justify-content-center" style="width:72px;height:102px">&#128218;</div>'}<div><h5>${this.escapeHtml(book.title)}</h5><div class="text-muted small mb-2">${this.escapeHtml(book.author)}</div><div class="small">Borrowed: ${this.escapeHtml(book.borrow_date)}<br>Due: ${this.escapeHtml(book.due_date)}<br><span class="text-success">${this.escapeHtml(book.remaining_label)}</span></div><span class="badge mt-2 bg-${badge}">${this.escapeHtml(status)}</span></div></div></div></div>`;
      })
      .join("")}</div>`;
  }
}
if (typeof window !== "undefined") {
  window.addEventListener("DOMContentLoaded", () => new GuestBorrowedPage());
}
