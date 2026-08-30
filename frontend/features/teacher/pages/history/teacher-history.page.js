import { BorrowerHistoryPage } from "../../../../app/shared/pages/borrower-history.page.js";

export class TeacherHistoryPage extends BorrowerHistoryPage {
  constructor() {
    super({
      historyApi: "/scan2borrow/api/teacher/history",
      classPrefix: "teacher-history",
      surfacePrefix: "teacher",
      copy: {
        topbar: "Borrowing History",
        title: "Borrowing History",
        description: "Review your complete faculty borrowing record.",
        role: "Teacher",
      },
    });
  }
}

if (typeof window !== "undefined") {
  window.addEventListener("DOMContentLoaded", () => new TeacherHistoryPage());
}
