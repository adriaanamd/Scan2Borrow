class GuestProfileController {
    constructor() { this.form = document.getElementById("guest-profile-form"); this.load(); this.form?.addEventListener("submit", (event) => this.submit(event)); }
    load() { fetch("/scan2borrow/api/guest/profile", { headers: { "X-Requested-With": "fetch" } }).then((response) => response.json()).then((response) => { if (!response.ok) return; Object.entries(response.data || {}).forEach(([key, value]) => { if (this.form.elements[key]) this.form.elements[key].value = value || ""; }); }); }
    submit(event) { event.preventDefault(); fetch("/scan2borrow/api/guest/profile", { method: "POST", body: new FormData(this.form) }).then((response) => response.json()).then((response) => { if (!response.ok) throw new Error(response.errors?.[0] || "Profile update failed."); const box = document.getElementById("form-success"); box.hidden = false; box.textContent = response.data?.message || "Profile updated."; }).catch((error) => { const box = document.getElementById("form-error"); box.hidden = false; box.textContent = error.message; }); }
}
window.addEventListener("DOMContentLoaded", () => new GuestProfileController());
