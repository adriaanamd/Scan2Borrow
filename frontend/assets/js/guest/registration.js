class GuestRegistrationController {
  constructor() {
    this.form = document.getElementById("guest-reg-form");
    this.csrf = document.querySelector('meta[name="csrf"]')?.content || "";
    this.camera = new CameraCapture();
    this.purpose = document.getElementById("purpose");
    this.otherPurpose = document.getElementById("otherPurposeWrap");
    this.bindEvents();
  }
  bindEvents() {
    this.purpose?.addEventListener("change", () => this.togglePurpose());
    this.togglePurpose();
    this.form?.addEventListener("submit", (event) => this.submit(event));
  }
  togglePurpose() {
    this.otherPurpose?.classList.toggle(
      "d-none",
      this.purpose?.value !== "Others",
    );
  }
  submit(event) {
    event.preventDefault();
    const body = new FormData(this.form);
    body.append("csrf", this.csrf);
    fetch("/scan2borrow/api/auth/guest/register", { method: "POST", body })
      .then((response) => response.json())
      .then((response) => {
        if (!response.ok)
          throw new Error(response.errors?.[0] || "Registration failed.");
        window.location.href = "/scan2borrow/guest/verify-otp";
      })
      .catch((error) => {
        const errorBox = document.getElementById("form-error");
        errorBox.hidden = false;
        errorBox.textContent = error.message;
      });
  }
}
window.addEventListener(
  "DOMContentLoaded",
  () => new GuestRegistrationController(),
);
