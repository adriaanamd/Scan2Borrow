class AuthPageController {
  constructor() {
    this.csrf = document.querySelector('meta[name="csrf"]')?.content || "";
    this.loginForm = document.querySelector(
      "#borrower-login-form, #staff-login-form",
    );
    this.bindLogin();
  }

  bindLogin() {
    this.loginForm?.addEventListener("submit", (event) => {
      event.preventDefault();
      const formData = new FormData(this.loginForm);
      formData.append("csrf", this.csrf);
      const endpoint =
        this.loginForm.id === "staff-login-form"
          ? "/scan2borrow/api/auth/staff/login"
          : "/scan2borrow/api/auth/borrower/login";

      this.request(endpoint, formData)
        .then((response) => {
          if (!response.ok) {
            if (response.data?.registration_required) {
              this.redirectToRegistration(response.data.role);
              return;
            }
            throw new Error(response.errors?.[0] || "Login failed.");
          }
          window.location.href = response.data.redirect;
        })
        .catch((error) => this.showMessage("login-error", error.message));
    });
  }

  redirectToRegistration(role) {
    const selectedRole = role === "teacher" ? "teacher" : "student";
    window.location.href =
      `/scan2borrow/register?role=${encodeURIComponent(selectedRole)}`;
  }

  request(endpoint, body) {
    return fetch(endpoint, { method: "POST", body }).then((response) =>
      response.json(),
    );
  }

  showMessage(id, message) {
    const node = document.getElementById(id);
    if (!node) return;
    node.hidden = false;
    node.textContent = message;
  }
}

window.addEventListener("DOMContentLoaded", () => new AuthPageController());
