class OtpPageController {
  constructor() {
    this.csrf = document.querySelector('meta[name="csrf"]')?.content || "";
    this.form = document.getElementById("otpForm");
    this.resendForm = document.getElementById("resend-form");
    this.input = this.form?.querySelector('input[name="otp"]');
    this.expiresIn = 300;
    this.bindEvents();
    this.startCountdown();
  }

  bindEvents() {
    this.form?.addEventListener("submit", (event) => this.submit(event));
    this.resendForm?.addEventListener("submit", (event) => this.resend(event));
    this.input?.addEventListener("input", () => {
      this.input.value = this.input.value.replace(/[^0-9]/g, "");
      if (this.input.value.length === 6) this.form.requestSubmit();
    });
  }

  startCountdown() {
    const node = document.getElementById("countdown");
    window.setInterval(() => {
      if (this.expiresIn > 0) this.expiresIn -= 1;
      const minutes = Math.floor(this.expiresIn / 60);
      const seconds = String(this.expiresIn % 60).padStart(2, "0");
      if (node) node.textContent = `${minutes}:${seconds}`;
    }, 1000);
  }

  request(form, endpoint) {
    const body = new FormData(form);
    body.append("csrf", this.csrf);
    return fetch(endpoint, { method: "POST", body }).then((response) =>
      response.json(),
    );
  }

  submit(event) {
    event.preventDefault();
    this.request(this.form, "/scan2borrow/api/auth/otp")
      .then((response) => {
        if (!response.ok) {
          throw new Error(
            response.errors?.[0] || "Invalid or expired OTP code.",
          );
        }
        window.location.href = response.data.redirect || "/scan2borrow/login";
      })
      .catch((error) => this.message("form-error", error.message));
  }

  resend(event) {
    event.preventDefault();
    this.request(this.resendForm, "/scan2borrow/api/auth/otp/resend").then(
      (response) => {
        this.message(
          response.ok ? "form-success" : "form-error",
          response.message || response.errors?.[0] || "Unable to resend OTP.",
        );
      },
    );
  }

  message(id, text) {
    const node = document.getElementById(id);
    if (!node) return;
    node.hidden = false;
    node.textContent = text;
  }
}

window.addEventListener("DOMContentLoaded", () => new OtpPageController());
