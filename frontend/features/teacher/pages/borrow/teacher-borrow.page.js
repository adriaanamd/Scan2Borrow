import { BorrowerSearchPage } from "../../../../app/shared/pages/borrower-search.page.js";

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
}

if (typeof window !== "undefined") {
  window.addEventListener("DOMContentLoaded", () => new TeacherBorrowPage());
}
