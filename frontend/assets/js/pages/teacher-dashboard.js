class TeacherDashboardController {
    constructor() {
        this.api = "/scan2borrow/api/teacher/dashboard";
        this.borrowForm = document.getElementById("borrowForm");
        this.returnForm = document.getElementById("returnForm");
        this.bindEvents();
        this.load();
    }
    escapeHtml(value) { return String(value == null ? "" : value).replace(/[&<>"']/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[character])); }
    load() { fetch(this.api, { headers: { "X-Requested-With": "fetch" } }).then((response) => response.json()).then((response) => { if (!response.ok) throw new Error(response.message); this.render(response.data || {}); }).catch((error) => this.toast(error.message || "Unable to load your dashboard.")); }
    render(data) { const user = data.user || {}; const stats = data.stats || {}; document.getElementById("teacher-name").textContent = user.name || ""; document.getElementById("current-user-name").textContent = user.name || ""; document.getElementById("teacher-meta").textContent = [user.barcode, user.department ? ` · ${user.department}` : "", user.position ? ` · ${user.position}` : ""].join(""); document.getElementById("teacher-barcode").textContent = user.barcode || ""; document.getElementById("active-count").textContent = String(stats.active || 0); document.getElementById("overdue-count").textContent = String(stats.overdue || 0); document.getElementById("fine-total").textContent = `₱${Number(stats.fines || 0).toFixed(2)}`; document.getElementById("on-time-rate").textContent = `${Number(stats.on_time_rate ?? 100)}%`; this.renderBarcode(user.barcode || ""); }
    renderBarcode(value) { if (value && window.JsBarcode) window.JsBarcode("#lib-barcode", value, { format: "CODE128", width: 2, height: 40 }); }
    submit(form, action) { const body = new FormData(form); body.append("action", action); fetch("/scan2borrow/api/teacher/dashboard", { method: "POST", body }).then((response) => response.json()).then((response) => { if (!response.ok) throw new Error(response.message); window.location.reload(); }).catch((error) => this.toast(error.message || "Request failed.")); }
    toast(message) { const host = document.getElementById("toast-host"); if (!host) return; const element = document.createElement("div"); element.className = "toast show text-white bg-danger border-0"; element.innerHTML = `<div class="toast-body">${this.escapeHtml(message)}</div>`; host.appendChild(element); window.setTimeout(() => element.remove(), 3500); }
    bindEvents() { this.borrowForm?.addEventListener("submit", (event) => { event.preventDefault(); this.submit(this.borrowForm, "borrow"); }); this.returnForm?.addEventListener("submit", (event) => { event.preventDefault(); this.submit(this.returnForm, "return_unified"); }); window.addEventListener("load", () => this.renderBarcode(document.getElementById("teacher-barcode").textContent)); }
}
window.addEventListener("DOMContentLoaded", () => new TeacherDashboardController());
