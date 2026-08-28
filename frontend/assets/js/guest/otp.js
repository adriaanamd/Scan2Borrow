class GuestOtpController {
    constructor() { this.form = document.getElementById("guest-otp-form") || document.getElementById("profile-otp-form"); this.resend = document.getElementById("resend-form"); this.csrf = document.querySelector('meta[name="csrf"]')?.content || ""; this.bindEvents(); }
    submit(event) { event.preventDefault(); const body = new FormData(this.form); body.append("csrf", this.csrf); fetch("/scan2borrow/api/auth/guest/otp", { method: "POST", body }).then((response) => response.json()).then((response) => { if (!response.ok) throw new Error(response.errors?.[0] || "Invalid or expired OTP code."); window.location.href = "/scan2borrow/guest/dashboard"; }).catch((error) => this.message("form-error", error.message)); }
    resendOtp(event) { event.preventDefault(); const body = new FormData(this.resend); body.append("csrf", this.csrf); fetch("/scan2borrow/api/auth/guest/otp/resend", { method: "POST", body }).then((response) => response.json()).then((response) => this.message(response.ok ? "form-success" : "form-error", response.message || response.errors?.[0] || "Please wait before requesting another code.")); }
    message(id, text) { const box = document.getElementById(id); if (box) { box.hidden = false; box.textContent = text; } }
    bindEvents() { this.form?.addEventListener("submit", (event) => this.submit(event)); this.resend?.addEventListener("submit", (event) => this.resendOtp(event)); }
}
window.addEventListener("DOMContentLoaded", () => new GuestOtpController());
