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

  bookCard(book) {
    const column = super.bookCard(book);
    const availableQuantity = Number(book.available_quantity ?? (book.status === "Available" ? 1 : 0));
    if (availableQuantity <= 0 || Boolean(book.already_borrowed)) return column;

    const action = document.createElement("button");
    action.type = "button";
    action.className = "btn btn-accent teacher-search-card__action";
    action.dataset.bsToggle = "modal";
    action.dataset.bsTarget = "#borrowModal";
    action.dataset.titleId = String(book.title_id ?? book.id ?? "");
    action.dataset.title = book.title || "";
    action.dataset.author = book.author || "Unknown Author";
    action.dataset.availableQuantity = String(book.available_quantity ?? 1);
    action.dataset.bookBarcode = book.barcode || "";
    action.textContent = "Add to Borrow Cart";
    column.querySelector(".book-card-shell")?.appendChild(action);
    return column;
  }
}

if (typeof window !== "undefined") {
  window.addEventListener("DOMContentLoaded", () => new TeacherBorrowPage());
}
