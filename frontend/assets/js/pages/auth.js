class AuthPageController {
  constructor(form) {
    this.form = form;
    this.csrf = document.querySelector('meta[name="csrf"]')?.content || "";
    this.error = document.getElementById("login-error");
    form.addEventListener("submit", (event) => this.submit(event));
  }
  submit(event) {
    event.preventDefault();
    const body = new FormData(this.form);
    body.append("csrf", this.csrf);
    const endpoint =
      this.form.id === "staff-login-form"
        ? "/scan2borrow/api/auth/staff/login"
        : "/scan2borrow/api/auth/borrower/login";
    fetch(endpoint, { method: "POST", body })
      .then((response) => response.json())
      .then((response) => {
        if (!response.ok) {
          if (response.data?.registration_required) {
            const modalId =
              response.data.role === "teacher"
                ? "teacherRegisterModal"
                : "studentRegisterModal";
            bootstrap.Modal.getOrCreateInstance(
              document.getElementById(modalId),
            ).show();
            return;
          }
          throw new Error(response.errors?.[0] || "Login failed.");
        }
        window.location.href = response.data.redirect;
      })
      .catch((error) => {
        this.error.hidden = false;
        this.error.textContent = error.message;
      });
  }
}
window.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector(
    "#borrower-login-form, #staff-login-form",
  );
  if (form) new AuthPageController(form);
});
