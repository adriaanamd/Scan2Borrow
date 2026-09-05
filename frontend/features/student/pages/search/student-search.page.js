import { BorrowerSearchPage } from "../../../../app/shared/pages/borrower-search.page.js";

export class StudentSearchPage extends BorrowerSearchPage {
  constructor() {
    super({
      api: "/scan2borrow/api/student/books",
      recommendationApi: "/scan2borrow/api/student/recommendations",
      searchHistoryApi: "/scan2borrow/api/student/search-history",
      lookupApi: "/scan2borrow/api/student/borrow/lookup",
      borrowApi: "/scan2borrow/api/student/borrow",
      dashboardPath: "/scan2borrow/student/dashboard",
      formAction: "/scan2borrow/student/search",
      classPrefix: "student",
      role: "student",
      copy: {
        topbar: "Search Books",
        eyebrow: "Student library",
        title: "Book Catalog",
        description: "Search and discover available books.",
        role: "Student",
      },
    });
  }
}

if (typeof window !== "undefined") {
  window.addEventListener("DOMContentLoaded", () => new StudentSearchPage());
}
