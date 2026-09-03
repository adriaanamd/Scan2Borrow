import { BorrowerSearchPage } from "../../../../app/shared/pages/borrower-search.page.js";
import { installTeacherBorrowModal } from "../../components/teacher-borrow-modal.js";

export class TeacherBorrowPage extends BorrowerSearchPage {
  constructor() {
    super({
      api: "/scan2borrow/api/teacher/books",
      lookupApi: "/scan2borrow/api/teacher/borrow/lookup",
      borrowApi: "/scan2borrow/api/teacher/borrow",
      dashboardPath: "/scan2borrow/teacher/dashboard",
      formAction: "/scan2borrow/teacher/borrow",
      classPrefix: "teacher",
      role: "teacher",
      copy: {
        topbar: "Borrow Books",
        eyebrow: "Faculty library",
        title: "Borrow Books",
        description: "Browse available books and add copies to your borrow cart.",
        role: "Teacher",
      },
    });
  }

  bindEvents() {
    super.bindEvents();
    this.borrowModal = installTeacherBorrowModal();
  }

  bookAction(book) {
    const availableQuantity = Number(book.available_quantity ?? (book.status === "Available" ? 1 : 0));
    if (Boolean(book.already_borrowed)) {
      return '<span class="badge bg-info w-100 py-2">&#128214; You have this</span>';
    }
    if (availableQuantity <= 0) {
      return this.waitlistAction(book);
    }
    return `<button type="button" class="btn btn-accent teacher-search-card__action w-100" data-bs-toggle="modal" data-bs-target="#borrowModal" data-title-id="${this.escapeHtml(book.title_id ?? book.id)}" data-title="${this.escapeHtml(book.title || "")}" data-author="${this.escapeHtml(book.author || "Unknown Author")}" data-available-quantity="${this.escapeHtml(book.available_quantity ?? 1)}" data-book-barcode="${this.escapeHtml(book.barcode || "")}" title="Add this title">Add to Borrow Cart</button>`;
  }
}

if (typeof window !== "undefined") {
  window.addEventListener("DOMContentLoaded", () => new TeacherBorrowPage());
}
