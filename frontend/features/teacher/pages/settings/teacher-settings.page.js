export class TeacherSettingsPage {
  constructor() {
    this.error = document.getElementById("student-settings-error");
    this.load();
  }

  load() {
    fetch("/scan2borrow/api/student/dashboard", {
      headers: { "X-Requested-With": "fetch" },
    })
      .then((response) => response.json())
      .then((response) => {
        if (!response.ok) throw new Error("Unable to load your account details.");
        this.render(response.data?.user || {});
      })
      .catch(() => {
        if (this.error) this.error.hidden = false;
      });
  }

  render(user) {
    const fields = {
      "student-name": user.name,
      "student-barcode": user.barcode,
      "student-course": user.course,
      "student-year-level": user.year_level,
      "student-department": user.department,
      "student-position": user.position,
      "current-user-name": user.name,
      "current-user-role": user.role || "Student",
    };

    Object.entries(fields).forEach(([id, value]) => {
      const element = document.getElementById(id);
      if (!element) return;

      if (element.value !== undefined) {
        element.value = value || "";
        return;
      }

      element.textContent = value || "";
    });
  }
}

if (typeof window !== "undefined") {
  window.addEventListener("DOMContentLoaded", () => new TeacherSettingsPage());
}
