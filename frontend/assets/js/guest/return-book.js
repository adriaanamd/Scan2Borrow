class GuestReturnBookController {
    constructor() { this.form = document.getElementById("returnForm"); this.camera = new CameraCapture({ fieldId: "return_photo", messageId: "camMsg", startId: "start", captureId: "capture", retakeId: "retake" }); this.form?.addEventListener("submit", (event) => this.submit(event)); }
    submit(event) { event.preventDefault(); fetch("/scan2borrow/api/guest/return", { method: "POST", body: new FormData(this.form) }).then((response) => response.json()).then((response) => { const box = document.getElementById(response.ok ? "form-success" : "form-error"); box.hidden = false; box.textContent = response.ok ? response.data.message : response.errors?.[0] || "Request failed."; }).catch(() => { const box = document.getElementById("form-error"); box.hidden = false; box.textContent = "Request failed."; }); }
}
window.addEventListener("DOMContentLoaded", () => { if (document.getElementById("returnForm")) new GuestReturnBookController(); });
