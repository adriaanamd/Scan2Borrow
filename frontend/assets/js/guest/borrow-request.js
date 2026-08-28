class GuestBorrowRequestController {
  constructor() {
    this.form = document.getElementById("borrowForm");
    this.csrf = document.querySelector('meta[name="csrf"]')?.content || "";
    this.camera = new CameraCapture({
      messageId: "camMsg",
      startId: "start",
      captureId: "capture",
      retakeId: "retake",
      fieldId: "verification_photo",
    });
    this.bookId = document.getElementById("book_id");
    this.loadBook();
    this.form?.addEventListener("submit", (event) => this.submit(event));
  }
  loadBook() {
    const params = new URLSearchParams(window.location.search);
    this.bookId.value = params.get("book_id") || "";
    fetch(
      "/scan2borrow/api/guest/books?id=" +
        encodeURIComponent(this.bookId.value),
      { headers: { "X-Requested-With": "fetch" } },
    )
      .then((response) => response.json())
      .then((response) => {
        const book = response.data?.book || response.data;
        if (!book) return;
        document.getElementById("book-title").textContent = book.title || "";
        document.getElementById("book-meta").textContent =
          `${book.author || ""} · Accession ${book.accession_no || book.barcode || ""}`;
      })
      .catch(() => {});
  }
  submit(event) {
    event.preventDefault();
    const body = new FormData(this.form);
    body.append("csrf", this.csrf);
    fetch("/scan2borrow/api/guest/borrow", { method: "POST", body })
      .then((response) => response.json())
      .then((response) => {
        if (!response.ok)
          throw new Error(response.errors?.[0] || "Request failed.");
        window.location.href = "/scan2borrow/guest/history";
      })
      .catch((error) => {
        const box = document.getElementById("form-error");
        box.hidden = false;
        box.textContent = error.message;
      });
  }
}
window.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("borrowForm");
  if (form) new GuestBorrowRequestController();
});
