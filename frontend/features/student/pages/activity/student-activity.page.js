import { BorrowerActivityPage } from "../../../../app/shared/pages/borrower-activity.page.js";

if (typeof window !== "undefined") {
  window.addEventListener("DOMContentLoaded", () => new BorrowerActivityPage({
    api: "/scan2borrow/api/student/activity",
    role: "Student",
    title: "Activity Logs",
    description: "Your complete account activity timeline.",
    classPrefix: "student-activity",
  }).start());
}
