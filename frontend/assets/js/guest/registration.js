class GuestRegistrationController {
  constructor() {
    this.form = document.getElementById("guest-reg-form");
    this.csrf = document.querySelector('meta[name="csrf"]')?.content || "";
    this.camera = new CameraCapture();
    this.purpose = document.getElementById("purpose");
    this.otherPurpose = document.getElementById("otherPurposeWrap");
    this.bindEvents();
    this.bindStepNavigation();
    this.showStep("details");
  }
  bindEvents() {
    this.purpose?.addEventListener("change", () => this.togglePurpose());
    this.togglePurpose();
    this.form?.addEventListener("submit", (event) => this.submit(event));
  }
  bindStepNavigation() {
    document
      .getElementById("guest-details-continue")
      ?.addEventListener("click", () => {
        if (!this.form || !this.form.reportValidity()) return;
        this.showStep("photo");
      });
    document
      .getElementById("guest-photo-back")
      ?.addEventListener("click", () => {
        this.camera.stop();
        this.showStep("details");
      });
  }
  showStep(step) {
    document.querySelectorAll("[data-guest-registration-step]").forEach((section) => {
      section.hidden = section.dataset.guestRegistrationStep !== step;
    });
    document.querySelectorAll("[data-guest-progress-step]").forEach((indicator) => {
      const isDetails = indicator.dataset.guestProgressStep === "details";
      indicator.classList.toggle("is-current", indicator.dataset.guestProgressStep === step);
      indicator.classList.toggle("is-complete", step === "photo" && isDetails);
    });
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
