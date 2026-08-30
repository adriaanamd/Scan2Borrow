import { BorrowerHistoryPage } from "../../../../app/shared/pages/borrower-history.page.js";

export class StudentHistoryPage extends BorrowerHistoryPage {
  constructor() {
    super({
      historyApi: "/scan2borrow/api/student/history",
      classPrefix: "student-history",
      copy: {
        topbar: "My History",
        title: "My History",
        description: "Your complete borrowing record.",
        role: "Student",
      },
    });
  }
}

if (typeof window !== "undefined") {
  window.addEventListener("DOMContentLoaded", () => new StudentHistoryPage());
}
